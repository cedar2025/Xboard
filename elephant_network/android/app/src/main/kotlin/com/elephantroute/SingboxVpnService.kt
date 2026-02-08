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

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val action = intent?.action
        Log.d(TAG, "onStartCommand: action=$action, hasExtras=${intent?.extras != null}")
        if (action == "STOP") {
            stopVpn()
            return START_NOT_STICKY
        }
        
        if (action == "SELECT_OUTBOUND") {
            val groupTag = intent?.getStringExtra("groupTag") ?: ""
            val outboundTag = intent?.getStringExtra("outboundTag") ?: ""
            try {
                 Log.d(TAG, "Select outbound $groupTag -> $outboundTag")
                 commandClient?.selectOutbound(groupTag, outboundTag)
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
                    val cacheFile = File(workingDir, "cache.db")
                    
                    val jsonConfig = org.json.JSONObject(config)

                    // Experimental section
                    if (!jsonConfig.has("experimental")) {
                        jsonConfig.put("experimental", org.json.JSONObject())
                    }
                    val experimental = jsonConfig.getJSONObject("experimental")

                    val logObj = org.json.JSONObject()
                    logObj.put("level", "trace")
                    logObj.put("timestamp", true)
                    jsonConfig.put("log", logObj)
                    
                    // [FIX] Inject DNS Fallback Rule
                    val dnsServers = org.json.JSONArray()
                    
                    // Reset to default
                    activeProxyTag = "proxy"
                    
                    if (jsonConfig.has("outbounds")) {
                        val outbounds = jsonConfig.getJSONArray("outbounds")
                        for (i in 0 until outbounds.length()) {
                            val out = outbounds.getJSONObject(i)
                            val type = out.optString("type")
                            val tag = out.optString("tag")
                            if (type == "selector") {
                                activeProxyTag = tag
                                Log.i(TAG, "Found Selector Tag: $activeProxyTag")
                                break
                            }
                        }
                    }
                    
                    val remoteServer = org.json.JSONObject()
                    remoteServer.put("tag", "remote")
                    remoteServer.put("address", "tcp://8.8.8.8")
                    remoteServer.put("detour", activeProxyTag)
                    remoteServer.put("strategy", "ipv4_only")
                    
                    val localServer = org.json.JSONObject()
                    localServer.put("tag", "local")
                    localServer.put("address", "223.5.5.5")
                    localServer.put("detour", "direct")
                    localServer.put("strategy", "ipv4_only")
                    
                    val blockServer = org.json.JSONObject()
                    blockServer.put("tag", "block")
                    blockServer.put("address", "rcode://success")
                    
                    dnsServers.put(remoteServer)
                    dnsServers.put(localServer)
                    dnsServers.put(blockServer)
                    
                    val dnsRules = org.json.JSONArray()
                    
                    val cnRule = org.json.JSONObject()
                    cnRule.put("rule_set", org.json.JSONArray().put("geosite-cn"))
                    cnRule.put("server", "local")
                    dnsRules.put(cnRule)
                    
                    val fallbackRule = org.json.JSONObject()
                    fallbackRule.put("server", "remote")
                    dnsRules.put(fallbackRule)
                    
                    val newDns = org.json.JSONObject()
                    newDns.put("servers", dnsServers)
                    newDns.put("rules", dnsRules)
                    newDns.put("strategy", "ipv4_only")
                    
                    jsonConfig.put("dns", newDns)
        
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
                        } catch (e: Exception) {
                            Log.e(TAG, "Error switching Route-Level rule_set", e)
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
                    
                    Log.i(TAG, "Step 4: Updating status to connected...")
                    updateStatus("connected")
                    
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
        if (groups == null) return
        val latencyMap = org.json.JSONObject()

        try {
            while (groups.hasNext()) {
                val group = groups.next()
                if (group == null) continue
                
                val items = group.items
                if (items != null) {
                    while (items.hasNext()) {
                        val item = items.next()
                        if (item == null) continue
                        
                        val delay = item.urlTestDelay
                        val tag = item.tag
                        
                        if (tag != null) {
                            latencyMap.put(tag, delay)
                        }
                    }
                }
            }

            if (latencyMap.length() > 0) {
                val intent = Intent(ACTION_VPN_STATE)
                intent.putExtra("latency_update", latencyMap.toString())
                sendBroadcast(intent)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error parsing groups", e)
        }
    }
    
    // Updated signature for new API
    override fun writeLogs(messageList: LogIterator?) {
         // Stub - ignore logs for now
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
