package com.elephantroute

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.ActivityNotFoundException
import android.net.Uri
import android.os.Build
import android.provider.Settings
import android.net.VpnService
import androidx.core.content.FileProvider
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodChannel
import java.io.File

class MainActivity : FlutterActivity() {
    private val CHANNEL = "com.elephant.network/vpn"
    private val UPDATE_CHANNEL = "com.elephant.network/update"
    private val SHARE_CHANNEL = "com.elephant.network/share"
    private val EVENT_CHANNEL = "com.elephant.network/vpn_state"
    private val VPN_REQUEST_CODE = 100
    private var pendingResult: MethodChannel.Result? = null
    private var eventSink: EventChannel.EventSink? = null

    private val vpnStateReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            intent?.let {
                val stateMap = HashMap<String, Any>()
                stateMap["status"] = it.getStringExtra("status") ?: "disconnected"
                stateMap["up_speed"] = it.getLongExtra("up_speed", 0L)
                stateMap["down_speed"] = it.getLongExtra("down_speed", 0L)
                stateMap["total_up"] = it.getLongExtra("total_up", 0L)
                stateMap["total_down"] = it.getLongExtra("total_down", 0L)
                stateMap["error_message"] = it.getStringExtra("error_message") ?: ""
                
                // Handle latency update JSON string
                val latencyUpdate = it.getStringExtra("latency_update")
                if (latencyUpdate != null) {
                    stateMap["latency_update"] = latencyUpdate
                }
                
                runOnUiThread {
                    eventSink?.success(stateMap)
                }
            }
        }
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        // MethodChannel
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL).setMethodCallHandler { call, result ->
            when (call.method) {
                "requestPermission" -> {
                    val intent = VpnService.prepare(this)
                    if (intent != null) {
                        pendingResult = result
                        startActivityForResult(intent, VPN_REQUEST_CODE)
                    } else {
                        result.success(true)
                    }
                }
                "start" -> {
                    val config = call.argument<String>("config")
                    val intent = Intent(this, SingboxVpnService::class.java).apply {
                        putExtra("config", config)
                    }
                    // [FIX] Use startForegroundService for Android O and above
                    if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                        startForegroundService(intent)
                    } else {
                        startService(intent)
                    }
                    result.success(null)
                }
                "stop" -> {
                    val intent = Intent(this, SingboxVpnService::class.java).apply {
                        action = "STOP"
                    }
                    if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                        startForegroundService(intent)
                    } else {
                        startService(intent)
                    }
                    result.success(null)
                }
                "prepareSpeedTest" -> {
                    val config = call.argument<String>("config")
                    val intent = Intent(this, SingboxVpnService::class.java).apply {
                        action = "PREPARE_SPEED_TEST"
                        putExtra("config", config)
                    }
                    startService(intent)
                    result.success(null)
                }
                "stopSpeedTest" -> {
                    val intent = Intent(this, SingboxVpnService::class.java).apply {
                        action = "STOP_SPEED_TEST"
                    }
                    startService(intent)
                    result.success(null)
                }
                "urlTest" -> {
                    val groupTag = call.argument<String>("groupTag")
                    // Send URL_TEST action to service
                    val intent = Intent(this, SingboxVpnService::class.java).apply {
                        action = "URL_TEST"
                        putExtra("groupTag", groupTag)
                    }
                    startService(intent)
                    // Result is returned asynchronously via EventChannel
                    result.success(0) 
                }
                "selectOutbound" -> {
                    val groupTag = call.argument<String>("groupTag") ?: ""
                    val outboundTag = call.argument<String>("outboundTag") ?: ""
                    val intent = Intent(this, SingboxVpnService::class.java).apply {
                        action = "SELECT_OUTBOUND"
                        putExtra("groupTag", groupTag)
                        putExtra("outboundTag", outboundTag)
                    }
                    startService(intent)
                    result.success(null)
                }
                else -> {
                    result.notImplemented()
                }
            }
        }

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, UPDATE_CHANNEL).setMethodCallHandler { call, result ->
            when (call.method) {
                "primaryAbi" -> {
                    result.success(Build.SUPPORTED_ABIS.firstOrNull() ?: "")
                }
                "installApk" -> {
                    val path = call.argument<String>("path")
                    if (path.isNullOrBlank()) {
                        result.error("INVALID_ARGUMENT", "APK path is required", null)
                        return@setMethodCallHandler
                    }
                    installApk(path, result)
                }
                else -> {
                    result.notImplemented()
                }
            }
        }

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, SHARE_CHANNEL).setMethodCallHandler { call, result ->
            when (call.method) {
                "shareText" -> shareText(call.arguments, result)
                else -> result.notImplemented()
            }
        }

        // EventChannel
        EventChannel(flutterEngine.dartExecutor.binaryMessenger, EVENT_CHANNEL).setStreamHandler(
            object : EventChannel.StreamHandler {
                override fun onListen(arguments: Any?, events: EventChannel.EventSink?) {
                    eventSink = events
                    if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                        registerReceiver(vpnStateReceiver, IntentFilter("com.elephant.network.VPN_STATE"), Context.RECEIVER_EXPORTED)
                    } else {
                        registerReceiver(vpnStateReceiver, IntentFilter("com.elephant.network.VPN_STATE"))
                    }
                }

                override fun onCancel(arguments: Any?) {
                    unregisterReceiver(vpnStateReceiver)
                    eventSink = null
                }
            }
        )
    }

    private fun installApk(path: String, result: MethodChannel.Result) {
        val apkFile = File(path)
        if (!apkFile.exists()) {
            result.error("FILE_NOT_FOUND", "APK file does not exist", null)
            return
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !packageManager.canRequestPackageInstalls()) {
            val settingsIntent = Intent(
                Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                Uri.parse("package:$packageName")
            ).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            startActivity(settingsIntent)
            result.error(
                "INSTALL_PERMISSION_REQUIRED",
                "Please allow this app to install unknown apps, then try again",
                null
            )
            return
        }

        val apkUri = FileProvider.getUriForFile(
            this,
            "$packageName.fileprovider",
            apkFile
        )
        val installIntent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(apkUri, "application/vnd.android.package-archive")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        startActivity(installIntent)
        result.success(null)
    }

    private fun shareText(arguments: Any?, result: MethodChannel.Result) {
        val args = arguments as? Map<*, *>
        val text = args?.get("text") as? String
        val packageNames = (args?.get("packageNames") as? List<*>)
            ?.mapNotNull { it as? String }
            .orEmpty()

        if (text.isNullOrBlank() || packageNames.isEmpty()) {
            result.error("INVALID_ARGUMENT", "Share text and package names are required", null)
            return
        }

        for (targetPackage in packageNames) {
            val shareIntent = Intent(Intent.ACTION_SEND).apply {
                type = "text/plain"
                setPackage(targetPackage)
                putExtra(Intent.EXTRA_TEXT, text)
            }

            try {
                if (shareIntent.resolveActivity(packageManager) != null) {
                    startActivity(shareIntent)
                    result.success(null)
                    return
                }
            } catch (_: ActivityNotFoundException) {
                // Try the next compatible package, such as WhatsApp Business.
            } catch (_: SecurityException) {
                // Treat blocked package launches the same as an unavailable app.
            }
        }

        result.error("APP_NOT_INSTALLED", "Target share app is unavailable", null)
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode == VPN_REQUEST_CODE) {
            pendingResult?.success(resultCode == RESULT_OK)
            pendingResult = null
        }
    }
}
