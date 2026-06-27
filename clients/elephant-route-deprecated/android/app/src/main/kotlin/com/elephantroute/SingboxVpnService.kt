package com.elephantroute

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.net.VpnService
import android.os.Build
import android.os.ParcelFileDescriptor
import android.util.Log
import androidx.core.app.NotificationCompat
import io.nekohasekai.libbox.CommandClient
import io.nekohasekai.libbox.CommandClientHandler
import io.nekohasekai.libbox.CommandClientOptions
import io.nekohasekai.libbox.Libbox
import io.nekohasekai.libbox.LogIterator
import io.nekohasekai.libbox.StatusMessage
import io.nekohasekai.libbox.TunOptions
import io.nekohasekai.libbox.OutboundGroupIterator
import io.nekohasekai.libbox.StringIterator
import android.os.Process
import java.io.File
import com.elephantroute.R

class SingboxVpnService : VpnService(), CommandClientHandler {
    private var vpnInterface: ParcelFileDescriptor? = null
    private var commandClient: CommandClient? = null
    
    // Bridge to PlatformInterface
    private val platform by lazy { PlatformInterfaceBridge(this, this) }
    
    private val TAG = "SingboxVpnService"
    private val ACTION_VPN_STATE = "com.elephant.network.VPN_STATE"
    private val CHANNEL_ID = "vpn_service_channel"
    private val NOTIFICATION_ID = 1

    private var currentStatus = "disconnected"
    private var upSpeed = 0L
    private var downSpeed = 0L
    private var totalUp = 0L
    private var totalDown = 0L

    private var activeProxyTag = "proxy"
    private var lastSanitizedConfig: String? = null
    private var isRestarting = false
    private var isSpeedTestRunning = false

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val action = intent?.action
        Log.d(TAG, "onStartCommand: action=$action, hasExtras=${intent?.extras != null}")
        if (action == "STOP") {
            stopVpn()
            return START_NOT_STICKY
        }
        if (action == "STOP_SPEED_TEST") {
            stopSpeedTest()
            return START_NOT_STICKY
        }
        if (action == "PREPARE_SPEED_TEST") {
            val config = intent?.getStringExtra("config") ?: ""
            if (config.isEmpty()) {
                Log.e(TAG, "Speed test config is empty")
                return START_NOT_STICKY
            }
            prepareSpeedTest(config)
            return START_NOT_STICKY
        }
        if (action == "SELECT_OUTBOUND") {
            var groupTag = intent?.getStringExtra("groupTag")
            if (groupTag.isNullOrEmpty() || groupTag == "proxy") {
                groupTag = activeProxyTag
            }
            val outboundTag = intent?.getStringExtra("outboundTag") ?: ""
            Log.d(TAG, "Select outbound $groupTag -> $outboundTag via RESTART")
            
            try {
                 // Fast-path for UI responsiveness if we have the last config
                 val currentConfig = lastSanitizedConfig
                 if (currentStatus == "connected" && currentConfig != null) {
                     // 1. Modify the config to set the new outbound as the selector's default
                     val configObj = org.json.JSONObject(currentConfig)
                     if (configObj.has("outbounds")) {
                         val outbounds = configObj.getJSONArray("outbounds")
                         for (i in 0 until outbounds.length()) {
                             val out = outbounds.getJSONObject(i)
                             if (out.optString("type") == "selector" && out.optString("tag") == groupTag) {
                                 out.put("default", outboundTag)
                                 Log.i(TAG, "Set selector '$groupTag' default to '$outboundTag'")
                                 break
                             }
                         }
                     }
                     
                     val updatedConfigStr = configObj.toString()
                     
                     // 2. Clear cache to prevent sing-box from restoring old selection
                     try {
                         val baseDir = filesDir.absolutePath
                         val workingDir = File(baseDir, "sing-box")
                         val filesToDelete = listOf("cache.db", "cache.db-wal", "cache.db-shm")
                         for (fileName in filesToDelete) {
                             val cacheFile = File(workingDir, fileName)
                             if (cacheFile.exists()) {
                                 cacheFile.delete()
                                 Log.d(TAG, "Deleted cache file: $fileName")
                             }
                         }
                     } catch (e: Exception) {
                         Log.e(TAG, "Failed to delete cache files", e)
                     }
                     
                     // 3. Restart VPN engine with updated config
                     isRestarting = true
                     
                     try {
                         commandClient?.disconnect()
                     } catch(e: Exception) {}
                     commandClient = null
                     
                     SingBoxEngine.stop()
                     
                     // Start again in background thread
                     Thread {
                         try {
                             Thread.sleep(300) // Brief wait for engine to fully stop
                             Log.i(TAG, "Restarting VPN engine with new outbound...")
                             startVpn(updatedConfigStr)
                         } catch (e: Exception) {
                             Log.e(TAG, "Failed to restart VPN", e)
                         } finally {
                             isRestarting = false
                         }
                     }.start()
                 } else {
                     Log.w(TAG, "No last config found, falling back to commandClient")
                     commandClient?.selectOutbound(groupTag, outboundTag)
                 }
            } catch (e: Exception) {
                Log.e(TAG, "Error selecting outbound", e)
            }
            return START_NOT_STICKY
        }

        if (action == "URL_TEST") {
            try {
                // If the client is null, it means VPN is not running.
                if (commandClient == null) {
                    Log.w(TAG, "Ignore URL_TEST: VPN not running (commandClient is null)")
                    return START_NOT_STICKY
                }
                
                var groupTag = intent?.getStringExtra("groupTag")
                // If tag is missing or generic "proxy", use the detected active tag
                if (groupTag.isNullOrEmpty() || groupTag == "proxy") {
                    groupTag = activeProxyTag
                }
                
                Log.d(TAG, "Triggering URL Test for group: $groupTag")
                commandClient?.urlTest(groupTag)
            } catch (e: Exception) {
                Log.e(TAG, "Error triggering URL test", e)
            }
            return START_NOT_STICKY
        }

        val config = intent?.getStringExtra("config") ?: ""
        Log.d(TAG, "onStartCommand: config length = ${config.length}")
        
        if (config.isEmpty()) {
            Log.e(TAG, "Config is empty! Cannot start VPN.")
            updateStatus("disconnected", "配置为空，无法启动VPN")
            stopSelf()
            return START_NOT_STICKY
        }
        
        // 验证是否是有效的JSON
        try {
            org.json.JSONObject(config)
            Log.d(TAG, "Config JSON validation passed")
        } catch (e: Exception) {
            Log.e(TAG, "Invalid JSON config!", e)
            updateStatus("disconnected", "配置格式错误: ${e.message}")
            stopSelf()
            return START_NOT_STICKY
        }
        
        startVpn(config)
        return START_NOT_STICKY
    }

    private fun startVpn(config: String) {
        try {
            Log.i(TAG, "========================================")
            Log.i(TAG, "VPN START REQUESTED")
            Log.i(TAG, "Config length: ${config.length} bytes")
            Log.i(TAG, "========================================")

            if (isSpeedTestRunning) {
                Log.i(TAG, "Stopping temporary speed-test core before starting VPN")
                stopSpeedTestCore()
            }
            
            updateStatus("connecting")
            Log.d(TAG, "Creating notification channel...")
            createNotificationChannel()
            
            Log.d(TAG, "Starting foreground service...")
            startForegroundServiceNotification()
            Log.d(TAG, "Foreground service started successfully")

            // [FIX] Fix ANR by moving heavy work to background thread
            Thread {
                Log.i(TAG, "========================================")
                Log.i(TAG, "VPN STARTUP SEQUENCE INITIATED")
                Log.i(TAG, "========================================")
                Log.d(TAG, "Background Thread Started. Preparing config...")
                try {
                    // Check and prepare assets
                    Log.i(TAG, "Step 1: Checking assets...")
                    checkAndPrepareAssets()
                    Log.i(TAG, "✓ Assets check completed")
                    // CRITICAL: Set cache_file to a writable path to avoid "read-only file system" error
                    val baseDir = filesDir.absolutePath
                    val workingDir = File(baseDir, "sing-box").apply { if (!exists()) mkdirs() }
                    
                    // Delete cache.db on completely fresh start (not restart) to avoid SQLite corruption
                    if (!isRestarting) {
                        try {
                            val filesToDelete = listOf("cache.db", "cache.db-wal", "cache.db-shm")
                            for (fileName in filesToDelete) {
                                val cacheFile = File(workingDir, fileName)
                                if (cacheFile.exists()) {
                                    cacheFile.delete()
                                    Log.d(TAG, "Deleted $fileName on fresh start")
                                }
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Failed to delete cache on startup", e)
                        }
                    }
                    val cacheFile = File(workingDir, "cache.db")
                    
                    val jsonConfig = org.json.JSONObject(config)
                    jsonConfig.remove("use_tun_mode")

                    // Experimental section
                    if (!jsonConfig.has("experimental")) {
                        jsonConfig.put("experimental", org.json.JSONObject())
                    }
                    val experimental = jsonConfig.getJSONObject("experimental")

                    val logObj = org.json.JSONObject()
                    logObj.put("level", "trace")
                    logObj.put("timestamp", true)
                    jsonConfig.put("log", logObj)
                    
                    // Find active proxy tag for fallback use
                    activeProxyTag = "proxy"
                    if (jsonConfig.has("outbounds")) {
                        val outbounds = jsonConfig.getJSONArray("outbounds")
                        for (i in 0 until outbounds.length()) {
                            val out = outbounds.getJSONObject(i)
                            if (out.optString("type") == "selector") {
                                activeProxyTag = out.optString("tag")
                                Log.i(TAG, "Found Selector Tag: $activeProxyTag")
                                break
                            }
                        }
                    }
                    
                    // [FIX] Gracefully Inject API DNS Rule without destroying the original DNS config
                    if (jsonConfig.has("dns")) {
                        try {
                            val dns = jsonConfig.getJSONObject("dns")
                            if (!dns.has("rules")) {
                                dns.put("rules", org.json.JSONArray())
                            }
                            val rules = dns.getJSONArray("rules")
                            
                            // Find a local DNS server tag
                            var localDnsTag = "local"
                            if (dns.has("servers")) {
                                val servers = dns.getJSONArray("servers")
                                for (i in 0 until servers.length()) {
                                    val s = servers.getJSONObject(i)
                                    val tag = s.optString("tag")
                                    val detour = s.optString("detour")
                                    // Try to auto-detect the local dns server tag used by this config
                                    if (tag.lowercase().contains("local") || detour == "direct") {
                                        localDnsTag = tag
                                        break
                                    }
                                }
                                // FORCE auto_detect_interface to false on all servers since we app-bypass
                                for (i in 0 until servers.length()) {
                                    val s = servers.getJSONObject(i)
                                    s.remove("auto_detect_interface")
                                }
                            }
                            
                            val apiRule = org.json.JSONObject()
                            val apiDomains = org.json.JSONArray()
                            apiDomains.put("www.elephant223.com")
                            apiRule.put("domain", apiDomains)
                            apiRule.put("server", localDnsTag)
                            
                            // Prepend rule to take highest priority
                            val newRules = org.json.JSONArray()
                            newRules.put(apiRule)
                            for (i in 0 until rules.length()) {
                                newRules.put(rules.get(i))
                            }
                            dns.put("rules", newRules)
                        } catch (e: Exception) {
                            Log.e(TAG, "Error injecting DNS rule", e)
                        }
                    }
                    
                    // Forcefully remove auto_detect_interface from all outbounds to prevent 
                    // Android "no available network interface" error now that we use App Bypass
                    if (jsonConfig.has("outbounds")) {
                        val outbounds = jsonConfig.getJSONArray("outbounds")
                        for (i in 0 until outbounds.length()) {
                            val out = outbounds.getJSONObject(i)
                            out.remove("auto_detect_interface")
                        }
                    }
        
                    if (!experimental.has("clash_api")) {
                         experimental.put("clash_api", org.json.JSONObject())
                    }
                    val clashApi = experimental.getJSONObject("clash_api")
                    clashApi.put("external_controller", "127.0.0.1:9090")
                    if (!clashApi.has("external_ui")) {
                        clashApi.put("external_ui", "ui")
                    }
        
                    val cacheFileObj = org.json.JSONObject()
                    cacheFileObj.put("enabled", true)
                    cacheFileObj.put("path", cacheFile.absolutePath)
                    experimental.put("cache_file", cacheFileObj)
        
                    if (jsonConfig.has("inbounds")) {
                        try {
                            val inbounds = jsonConfig.getJSONArray("inbounds")
                            for (i in 0 until inbounds.length()) {
                                 val inbound = inbounds.getJSONObject(i)
                                if (inbound.optString("type") == "tun" && inbound.has("address")) {
                                    val addresses = inbound.getJSONArray("address")
                                    val ipv4Addresses = org.json.JSONArray()
                                    for (j in 0 until addresses.length()) {
                                        val addr = addresses.getString(j)
                                        if (!addr.contains(":")) { 
                                            ipv4Addresses.put(addr)
                                        }
                                    }
                                    inbound.put("address", ipv4Addresses)
                                }
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error sanitizing Inbounds", e)
                        }
                    }
                    
                    fun sanitizeRuleSets(ruleSets: org.json.JSONArray) {
                        for (i in 0 until ruleSets.length()) {
                            val rs = ruleSets.getJSONObject(i)
                            val tag = rs.optString("tag")
                            
                            if (tag == "geosite-cn" || tag == "geoip-cn") {
                                rs.remove("url")
                                rs.remove("download_detour")
                                rs.remove("update_interval")
                                rs.put("type", "local")
                                rs.put("format", "binary")
                                val localPath = File(workingDir, "$tag.srs").absolutePath
                                rs.put("path", localPath)
                            }
                        }
                    }
        
                    if (jsonConfig.has("rule_set")) {
                        try {
                            sanitizeRuleSets(jsonConfig.getJSONArray("rule_set"))
                        } catch (e: Exception) {
                            Log.e(TAG, "Error switching Top-Level rule_set", e)
                        }
                    }
        
                    if (jsonConfig.has("route")) {
                        try {
                            val route = jsonConfig.getJSONObject("route")
                            if (route.has("rule_set")) {
                                sanitizeRuleSets(route.getJSONArray("rule_set"))
                            }
                            
                            // [FIX] Inject Direct Rule for App Backend API
                            if (!route.has("rules")) {
                                route.put("rules", org.json.JSONArray())
                            }
                            val rules = route.getJSONArray("rules")
                            
                            // 1. Domain-based direct rule
                            val domainDirectRule = org.json.JSONObject()
                            domainDirectRule.put("outbound", "direct")
                            val domains = org.json.JSONArray()
                            domains.put("www.elephant223.com")
                            domainDirectRule.put("domain", domains)
                            
                            // 2. IP-based direct rule
                            val ipDirectRule = org.json.JSONObject()
                            ipDirectRule.put("outbound", "direct")
                            val ipCidr = org.json.JSONArray()
                            ipCidr.put("192.168.11.227/32")
                            ipCidr.put("192.168.0.0/16")
                            ipCidr.put("10.0.0.0/8")
                            ipCidr.put("172.16.0.0/12")
                            ipCidr.put("127.0.0.1/32")
                            ipDirectRule.put("ip_cidr", ipCidr)
                            
                            // Prepend these rules to take highest priority
                            val newRules = org.json.JSONArray()
                            newRules.put(domainDirectRule)
                            newRules.put(ipDirectRule)
                            for (i in 0 until rules.length()) {
                                newRules.put(rules.get(i))
                            }
                            route.put("rules", newRules)
                        } catch (e: Exception) {
                            Log.e(TAG, "Error injecting route rules", e)
                        }
                    }
        
                    if (jsonConfig.has("experimental")) {
                        try {
                            val exp = jsonConfig.getJSONObject("experimental")
                            if (exp.has("rule_set")) {
                                sanitizeRuleSets(exp.getJSONArray("rule_set"))
                            }
                        } catch (e: Exception) {
                    Log.e(TAG, "Error switching Experimental-Level rule_set", e)
                        }
                    }
                    
                    val finalConfig = jsonConfig.toString()
        
                    Log.i(TAG, "Step 2: Final config generated (${finalConfig.length} bytes)")
                    if (finalConfig.length < 100) {
                        Log.e(TAG, "Config too short! Content: $finalConfig")
                        throw Exception("Generated config is too short")
                    }
                    // Output first 200 chars of config for debugging
                    Log.d(TAG, "Config preview: ${finalConfig.take(200)}...")
                    
                    Log.i(TAG, "Step 3: Initializing SingBoxEngine...")
                    Log.i(TAG, "Calling SingBoxEngine.start() - THIS IS THE CRITICAL POINT")
                    
                    SingBoxEngine.start(this@SingboxVpnService, finalConfig, platform)
                    
                    Log.i(TAG, "✓✓✓ SingBoxEngine started successfully! ✓✓✓")
                    
                    // Store the fully sanitized config for future restarts
                    lastSanitizedConfig = finalConfig
                    
                    Log.i(TAG, "Step 4: Updating status to connected...")
                    updateStatus("connected")
                    Log.i(TAG, "VPN is now CONNECTED")
                    
                    Log.i(TAG, "Step 5: Connecting CommandClient...")
                    connectCommandClient()
                    
                    Log.i(TAG, "========================================")
                    Log.i(TAG, "VPN STARTUP SEQUENCE COMPLETED ✓")
                    Log.i(TAG, "========================================")
                    
                } catch (e: Exception) {
                    Log.e(TAG, "========================================")
                    Log.e(TAG, "❌ VPN STARTUP FAILED ❌")
                    Log.e(TAG, "========================================")
                    Log.e(TAG, "Exception type: ${e.javaClass.name}", e)
                    Log.e(TAG, "Exception message: ${e.message}")
                    Log.e(TAG, "Stack trace:", e)
                    updateStatus("disconnected", e.message)
                    stopForeground(true)
                    stopSelf()
                }
            }.start()
        } catch (e: Exception) {
            Log.e(TAG, "Failed to start VPN (Outer)", e)
        }
    }

    private fun prepareSpeedTest(config: String) {
        if (currentStatus != "disconnected") {
            Log.i(TAG, "VPN status is $currentStatus; skip temporary speed-test core")
            return
        }

        Thread {
            try {
                Log.i(TAG, "========================================")
                Log.i(TAG, "SPEED TEST CORE START REQUESTED")
                Log.i(TAG, "Config length: ${config.length} bytes")
                Log.i(TAG, "========================================")

                checkAndPrepareAssets()
                stopSpeedTestCore()

                val finalConfig = buildSpeedTestConfig(config)
                Log.i(TAG, "Starting temporary speed-test core (${finalConfig.length} bytes)")
                SingBoxEngine.start(this@SingboxVpnService, finalConfig, platform)
                isSpeedTestRunning = true
                Log.i(TAG, "Temporary speed-test core is ready")
            } catch (e: Exception) {
                Log.e(TAG, "Failed to prepare speed-test core", e)
                stopSpeedTestCore()
            }
        }.start()
    }

    private fun stopSpeedTest() {
        Thread {
            stopSpeedTestCore()
        }.start()
    }

    private fun stopSpeedTestCore() {
        if (!isSpeedTestRunning) return
        if (currentStatus == "connected") return

        try {
            commandClient?.disconnect()
        } catch (e: Exception) {
            Log.w(TAG, "Error disconnecting speed-test command client: ${e.message}")
        }
        commandClient = null

        try {
            SingBoxEngine.stop()
        } catch (e: Exception) {
            Log.w(TAG, "Error stopping speed-test core: ${e.message}")
        }

        isSpeedTestRunning = false
    }

    private fun buildSpeedTestConfig(config: String): String {
        val baseDir = filesDir.absolutePath
        val workingDir = File(baseDir, "sing-box").apply { if (!exists()) mkdirs() }
        val cacheFile = File(workingDir, "cache-speed-test.db")
        val filesToDelete = listOf(
            "cache-speed-test.db",
            "cache-speed-test.db-wal",
            "cache-speed-test.db-shm"
        )
        for (fileName in filesToDelete) {
            val target = File(workingDir, fileName)
            if (target.exists()) target.delete()
        }

        val jsonConfig = org.json.JSONObject(config)
        jsonConfig.remove("use_tun_mode")

        val logObj = org.json.JSONObject()
        logObj.put("level", "warn")
        logObj.put("timestamp", true)
        jsonConfig.put("log", logObj)

        if (!jsonConfig.has("experimental")) {
            jsonConfig.put("experimental", org.json.JSONObject())
        }
        val experimental = jsonConfig.getJSONObject("experimental")
        if (!experimental.has("clash_api")) {
            experimental.put("clash_api", org.json.JSONObject())
        }
        val clashApi = experimental.getJSONObject("clash_api")
        clashApi.put("external_controller", "127.0.0.1:9090")
        if (!clashApi.has("external_ui")) {
            clashApi.put("external_ui", "ui")
        }

        val cacheFileObj = org.json.JSONObject()
        cacheFileObj.put("enabled", true)
        cacheFileObj.put("path", cacheFile.absolutePath)
        experimental.put("cache_file", cacheFileObj)

        activeProxyTag = "proxy"
        if (jsonConfig.has("outbounds")) {
            val outbounds = jsonConfig.getJSONArray("outbounds")
            for (i in 0 until outbounds.length()) {
                val out = outbounds.getJSONObject(i)
                out.remove("auto_detect_interface")
                if (out.optString("type") == "selector") {
                    activeProxyTag = out.optString("tag")
                }
            }
        }

        if (jsonConfig.has("inbounds")) {
            val inbounds = jsonConfig.getJSONArray("inbounds")
            val filteredInbounds = org.json.JSONArray()
            for (i in 0 until inbounds.length()) {
                val inbound = inbounds.getJSONObject(i)
                if (inbound.optString("type") != "tun") {
                    filteredInbounds.put(inbound)
                }
            }
            jsonConfig.put("inbounds", filteredInbounds)
        }

        if (jsonConfig.has("dns")) {
            try {
                val dns = jsonConfig.getJSONObject("dns")
                if (dns.has("servers")) {
                    val servers = dns.getJSONArray("servers")
                    for (i in 0 until servers.length()) {
                        servers.getJSONObject(i).remove("auto_detect_interface")
                    }
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error sanitizing speed-test DNS config", e)
            }
        }

        if (jsonConfig.has("rule_set")) {
            try {
                sanitizeRuleSets(jsonConfig.getJSONArray("rule_set"), workingDir)
            } catch (e: Exception) {
                Log.e(TAG, "Error switching speed-test top-level rule_set", e)
            }
        }

        if (jsonConfig.has("route")) {
            try {
                val route = jsonConfig.getJSONObject("route")
                if (route.has("rule_set")) {
                    sanitizeRuleSets(route.getJSONArray("rule_set"), workingDir)
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error switching speed-test route rule_set", e)
            }
        }

        if (experimental.has("rule_set")) {
            try {
                sanitizeRuleSets(experimental.getJSONArray("rule_set"), workingDir)
            } catch (e: Exception) {
                Log.e(TAG, "Error switching speed-test experimental rule_set", e)
            }
        }

        return jsonConfig.toString()
    }

    private fun sanitizeRuleSets(ruleSets: org.json.JSONArray, workingDir: File) {
        for (i in 0 until ruleSets.length()) {
            val rs = ruleSets.getJSONObject(i)
            val tag = rs.optString("tag")

            if (tag == "geosite-cn" || tag == "geoip-cn") {
                rs.remove("url")
                rs.remove("download_detour")
                rs.remove("update_interval")
                rs.put("type", "local")
                rs.put("format", "binary")
                val localPath = File(workingDir, "$tag.srs").absolutePath
                rs.put("path", localPath)
            }
        }
    }
    
    private fun startForegroundServiceNotification() {
         val notificationIntent = Intent(this, MainActivity::class.java)
            val pendingIntent = PendingIntent.getActivity(
                this, 0, notificationIntent,
                PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
            )

            val notification: Notification = NotificationCompat.Builder(this, CHANNEL_ID)
                .setContentTitle(getString(R.string.app_name))
                .setContentText("正在保护您的网络连接...")
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentIntent(pendingIntent)
                .build()

            // VPN服务使用specialUse类型（Android 14+要求）
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                startForeground(
                    NOTIFICATION_ID, 
                    notification,
                    android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE
                )
            } else {
                startForeground(NOTIFICATION_ID, notification)
            }
    }

    private fun connectCommandClient() {
        Thread {
            try {
                Thread.sleep(1500) 
                Log.d(TAG, "Connecting CommandClient...")
                val options = CommandClientOptions()
                
                // [FIX] Subscribe to status and group updates to receive speed and urlTest results
                options.addCommand(Libbox.CommandStatus)
                options.addCommand(Libbox.CommandGroup)
                options.addCommand(Libbox.CommandLog)
                
                commandClient = Libbox.newCommandClient(this, options)
                commandClient?.connect()
                Log.d(TAG, "CommandClient connected")
            } catch (e: Exception) {
                Log.e(TAG, "Failed to connect CommandClient", e)
            }
        }.start()
    }

    private fun stopVpn() {
        Thread {
            try {
                updateStatus("disconnecting")
                try {
                    commandClient?.disconnect()
                } catch(e: Exception) {}
                commandClient = null
                
                SingBoxEngine.stop()
                
                try {
                    vpnInterface?.close()
                } catch(e: Exception) {}
                vpnInterface = null
                
                stopForeground(true)
                stopSelf()
                Log.i(TAG, "VPN Stopped")
            } catch (e: Exception) {
                Log.e(TAG, "Error stopping VPN", e)
            } finally {
                isSpeedTestRunning = false
                updateStatus("disconnected")
            }
        }.start()
    }
    
    // Called by PlatformInterfaceBridge
    fun openTunForCore(options: TunOptions): Int {
        Log.d(TAG, "opening TUN via Bridge...")
        val builder = Builder()
            .setSession(getString(R.string.app_name))
            .setMtu(if (options.mtu > 0) options.mtu else 1500)
            
        // [FIX] Exclude Local Development LAN and Backend API
        try {
            builder.addRoute("192.168.11.227", 32)
            builder.addRoute("10.0.0.0", 8)
            builder.addRoute("172.16.0.0", 12)
            builder.addRoute("192.168.0.0", 16)
        } catch (e: Exception) {
            Log.e(TAG, "Error adding bypass routes map", e)
        }
        
        // [CRITICAL FIX] Ensure the VPN app itself completely bypasses the VPN tunnel.
        // This is standard practice to prevent routing loops and ensure the app's own API
        // requests (like fetchNodes and login) go straight to the physical network.
        try {
            builder.addDisallowedApplication(packageName)
            Log.i(TAG, "Successfully added $packageName to disallowed applications (App Bypass)")
        } catch (e: Exception) {
            Log.e(TAG, "Error setting disallowed application for self", e)
        }
            
        val inet4 = options.inet4Address
        if (inet4 != null && inet4.hasNext()) {
            while (inet4.hasNext()) {
                val addr = inet4.next() ?: continue
                val ip = addr.address()
                val prefix = addr.prefix()
                builder.addAddress(ip, prefix)
                Log.d(TAG, "Added IPv4 address: $ip/$prefix")
            }
        } else {
            builder.addAddress("172.19.0.1", 30)
        }
        
        builder.addRoute("0.0.0.0", 0)
        builder.addDnsServer("8.8.8.8")
        builder.addDnsServer("1.1.1.1")
            
        vpnInterface = builder.establish()
        val fd = vpnInterface?.fd ?: -1
        Log.d(TAG, "TUN established, FD: $fd")
        return fd
    }

    private fun updateStatus(status: String, errorMessage: String? = null) {
        currentStatus = status
        sendStateBroadcast(errorMessage)
    }

    private fun sendStateBroadcast(errorMessage: String? = null) {
        val intent = Intent(ACTION_VPN_STATE).apply {
            putExtra("status", currentStatus)
            putExtra("up_speed", upSpeed)
            putExtra("down_speed", downSpeed)
            putExtra("total_up", totalUp)
            putExtra("total_down", totalDown)
            putExtra("error_message", errorMessage ?: "")
        }
        sendBroadcast(intent)
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val serviceChannel = NotificationChannel(
                CHANNEL_ID,
                "VPN Service Channel",
                NotificationManager.IMPORTANCE_DEFAULT
            )
            val manager = getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(serviceChannel)
        }
    }
    
    private fun updateNotification() {
        val upFormatted = formatSpeed(upSpeed)
        val downFormatted = formatSpeed(downSpeed)
        
        val notificationIntent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this, 0, notificationIntent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.app_name))
            .setContentText("↑ $upFormatted  ↓ $downFormatted")
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setOngoing(true)
            .setContentIntent(pendingIntent)
            .setOnlyAlertOnce(true) 
            .build()
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.notify(NOTIFICATION_ID, notification)
    }
    
    private fun formatSpeed(bytes: Long): String {
        return when {
            bytes < 1024 -> "${bytes} B/s"
            bytes < 1024 * 1024 -> String.format("%.1f KB/s", bytes / 1024.0)
            else -> String.format("%.1f MB/s", bytes / (1024.0 * 1024.0))
        }
    }

    // CommandClientHandler Implementation
    override fun clearLogs() {}
    override fun connected() {}
    override fun disconnected(message: String?) {}
    override fun initializeClashMode(modes: StringIterator?, current: String?) {}
    override fun updateClashMode(mode: String?) {}

    override fun writeGroups(groups: OutboundGroupIterator?) {
        Log.d(TAG, "CommandClientHandler: writeGroups called")
        if (groups == null) {
            Log.d(TAG, "writeGroups: groups is null")
            return
        }
        val latencyMap = org.json.JSONObject()

        try {
            while (groups.hasNext()) {
                val group = groups.next()
                if (group == null) continue
                
                Log.d(TAG, "writeGroups: Process group tag=${group.tag}")
                val items = group.items
                if (items != null) {
                    while (items.hasNext()) {
                        val item = items.next()
                        if (item == null) continue
                        
                        val delay = item.urlTestDelay
                        val tag = item.tag
                        
                        Log.d(TAG, "writeGroups: item tag=$tag, delay=$delay")
                        // delay == 0 means not yet tested, not timeout - filter it out
                        if (tag != null && delay > 0) {
                            latencyMap.put(tag, delay)
                        }
                    }
                }
            }

            Log.d(TAG, "writeGroups: Completed parsing, latencyMap size=${latencyMap.length()}")
            if (latencyMap.length() > 0) {
                val intent = Intent(ACTION_VPN_STATE)
                // Must include status to prevent BroadcastReceiver defaulting to "disconnected"
                intent.putExtra("status", currentStatus)
                intent.putExtra("latency_update", latencyMap.toString())
                sendBroadcast(intent)
                Log.d(TAG, "writeGroups: Broadcasted latency_update")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error parsing groups", e)
        }
    }
    
    // Updated signature for new API
    override fun writeLogs(messageList: LogIterator?) {
        if (messageList == null) return
        while (messageList.hasNext()) {
            val log = messageList.next()
            if (log != null) {
                Log.d("SingBoxCore", log.message ?: "null")
            }
        }
    }
    
    override fun writeStatus(message: StatusMessage?) {
        if (message == null) return
        try {
            upSpeed = message.uplink
            downSpeed = message.downlink
            totalUp = message.uplinkTotal
            totalDown = message.downlinkTotal
            
            updateNotification()
            sendStateBroadcast()
        } catch (e: Exception) {
            Log.e(TAG, "Error parsing status inside writeStatus", e)
        }
    }
    
    override fun setDefaultLogLevel(level: Int) {}
    override fun writeConnectionEvents(events: io.nekohasekai.libbox.ConnectionEvents?) {}

    
    private fun checkAndPrepareAssets() {
        val workingDir = File(filesDir, "sing-box")
        if (!workingDir.exists()) {
            Log.d(TAG, "Creating working directory: ${workingDir.absolutePath}")
            workingDir.mkdirs()
        }
        
        val requiredAssets = listOf("geosite-cn.srs", "geoip-cn.srs")
        
        for (assetName in requiredAssets) {
            val targetFile = File(workingDir, assetName)
            
            if (!targetFile.exists()) {
                Log.w(TAG, "Asset file missing: $assetName, attempting to copy from assets...")
                
                try {
                    // Try to copy from assets
                    assets.open(assetName).use { input ->
                        targetFile.outputStream().use { output ->
                            input.copyTo(output)
                        }
                    }
                    Log.i(TAG, "✓ Copied $assetName from assets (${targetFile.length()} bytes)")
                } catch (e: Exception) {
                    Log.e(TAG, "❌ Failed to copy $assetName from assets", e)
                    Log.w(TAG, "Continuing without $assetName - this may cause issues")
                    // Don't throw - libbox might work without these files in some configs
                }
            } else {
                Log.d(TAG, "✓ Asset file exists: $assetName (${targetFile.length()} bytes)")
            }
        }
    }
    
    override fun onDestroy() {
        super.onDestroy()
        stopVpn()
    }
}
