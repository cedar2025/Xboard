package cn.moncn.sing_box_windows.vpn

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.net.ProxyInfo
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.net.wifi.WifiManager
import androidx.core.app.NotificationCompat
import cn.moncn.sing_box_windows.MainActivity
import cn.moncn.sing_box_windows.R
import cn.moncn.sing_box_windows.config.ConfigSettingsApplier
import cn.moncn.sing_box_windows.config.ConfigRepository
import cn.moncn.sing_box_windows.config.AppSettingsDefaults
import cn.moncn.sing_box_windows.config.ClashApiDefaults
import cn.moncn.sing_box_windows.config.SettingsRepository
import cn.moncn.sing_box_windows.core.CoreStartResult
import cn.moncn.sing_box_windows.core.ClashApiClient
import cn.moncn.sing_box_windows.core.LibboxManager
import cn.moncn.sing_box_windows.core.SingBoxEngine
import io.nekohasekai.libbox.Notification as LibboxNotification
import io.nekohasekai.libbox.RoutePrefixIterator
import io.nekohasekai.libbox.TunOptions
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import java.util.concurrent.atomic.AtomicBoolean

class AppVpnService : android.net.VpnService() {
    private var vpnInterface: android.os.ParcelFileDescriptor? = null
    private val platform by lazy { PlatformInterfaceBridge(this, this) }
    val wifiManager: WifiManager? by lazy { getSystemService(WifiManager::class.java) }
    private val mainHandler = Handler(Looper.getMainLooper())
    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val isStarting = AtomicBoolean(false)

    override fun onBind(intent: Intent?): IBinder? {
        return super.onBind(intent)
    }

    override fun onCreate() {
        super.onCreate()
        LibboxManager.initialize(this)
        DefaultNetworkMonitor.initialize(this)
    }

    override fun onDestroy() {
        super.onDestroy()
        serviceScope.cancel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_START -> startTunnel()
            ACTION_STOP -> stopTunnel()
        }
        return START_STICKY
    }

    private fun startTunnel() {
        if (vpnInterface != null || SingBoxEngine.isRunning() || isStarting.get()) {
            return
        }
        if (!isStarting.compareAndSet(false, true)) {
            return
        }
        VpnStateStore.update(VpnState.CONNECTING)
        val notification = buildNotification("连接中")
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            startForeground(
                NOTIFICATION_ID,
                notification,
                ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC
            )
        } else {
            startForeground(NOTIFICATION_ID, notification)
        }

        // 首次启动可能伴随规则下载，放到后台避免阻塞 UI。
        serviceScope.launch {
            val result = runCatching {
                val rawConfig = ConfigRepository.loadOrCreateConfig(this@AppVpnService)
                val settings = SettingsRepository.load(this@AppVpnService)
                // 默认启用 Clash API 用于节点管理
                ClashApiClient.configure(ClashApiDefaults.ADDRESS, ClashApiDefaults.SECRET)
                val appliedConfig = ConfigSettingsApplier.applySettings(rawConfig, settings)
                ConfigRepository.saveConfig(this@AppVpnService, appliedConfig)
                SingBoxEngine.start(appliedConfig, platform)
            }.getOrElse { error ->
                CoreStartResult(
                    ok = false,
                    error = error.message ?: "core start failed"
                )
            }
            mainHandler.post {
                isStarting.set(false)
                if (!result.ok) {
                    VpnStateStore.update(VpnState.ERROR, result.error)
                    stopTunnel(resetState = false)
                    return@post
                }
                VpnStateStore.update(VpnState.CONNECTED)
                notifyStatus("已连接")
            }
        }
    }

    private fun stopTunnel(resetState: Boolean = true) {
        isStarting.set(false)
        SingBoxEngine.stop()
        vpnInterface?.close()
        vpnInterface = null
        if (resetState) {
            VpnStateStore.update(VpnState.IDLE)
        }
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    private fun buildNotification(status: String): Notification {
        val channelId = ensureChannel()
        val intent = Intent(this, MainActivity::class.java)
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        } else {
            PendingIntent.FLAG_UPDATE_CURRENT
        }
        val pendingIntent = PendingIntent.getActivity(this, 0, intent, flags)
        return NotificationCompat.Builder(this, channelId)
            .setContentTitle("SingBox VPN")
            .setContentText(status)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .build()
    }

    private fun notifyStatus(status: String) {
        val manager = getSystemService(NotificationManager::class.java)
        manager?.notify(NOTIFICATION_ID, buildNotification(status))
    }

    private fun ensureChannel(): String {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(NotificationManager::class.java)
            val channel = NotificationChannel(
                CHANNEL_ID,
                "VPN",
                NotificationManager.IMPORTANCE_DEFAULT
            )
            manager?.createNotificationChannel(channel)
        }
        return CHANNEL_ID
    }

    fun openTunForCore(options: TunOptions): Int {
        vpnInterface?.close()
        vpnInterface = null

        val builder = Builder()
            .setSession(VpnDefaults.SESSION_NAME)
            .setMtu(options.mtu)

        var addressCount = 0
        addressCount += addAddresses(builder, options.inet4Address)
        addressCount += addAddresses(builder, options.inet6Address)
        if (addressCount == 0) {
            builder.addAddress(VpnDefaults.ADDRESS, VpnDefaults.PREFIX)
        }

        val dnsAddress = runCatching { options.dnsServerAddress?.value }.getOrNull()
        if (!dnsAddress.isNullOrBlank()) {
            builder.addDnsServer(dnsAddress)
        } else {
            builder.addDnsServer(VpnDefaults.DNS)
        }

        var routeCount = 0
        routeCount += addRoutes(builder, options.inet4RouteAddress)
        routeCount += addRoutes(builder, options.inet6RouteAddress)
        routeCount += addRoutes(builder, options.inet4RouteRange)
        routeCount += addRoutes(builder, options.inet6RouteRange)
        if (routeCount == 0) {
            builder.addRoute("0.0.0.0", 0)
            builder.addRoute("::", 0)
        }

        logRouteExcludes(options)
        applyPackageRules(builder, options)
        applyHttpProxy(builder, options)
        applyUnderlyingNetworks(builder)

        vpnInterface = builder.establish()
        if (vpnInterface == null) {
            error("vpn establish failed")
        }

        return vpnInterface?.fd ?: error("tun fd unavailable")
    }

    fun notifyCoreNotification(notification: LibboxNotification) {
        Log.i("Libbox", "notification: ${notification.title} ${notification.body}")
    }

    private fun addAddresses(builder: Builder, iterator: RoutePrefixIterator): Int {
        var count = 0
        while (iterator.hasNext()) {
            val prefix = iterator.next()
            builder.addAddress(prefix.address(), prefix.prefix())
            count++
        }
        return count
    }

    private fun addRoutes(builder: Builder, iterator: RoutePrefixIterator): Int {
        var count = 0
        while (iterator.hasNext()) {
            val prefix = iterator.next()
            builder.addRoute(prefix.address(), prefix.prefix())
            count++
        }
        return count
    }

    private fun logRouteExcludes(options: TunOptions) {
        val entries = mutableListOf<String>()
        val ipv4 = options.inet4RouteExcludeAddress
        while (ipv4.hasNext()) {
            entries.add(ipv4.next().string())
        }
        val ipv6 = options.inet6RouteExcludeAddress
        while (ipv6.hasNext()) {
            entries.add(ipv6.next().string())
        }
        if (entries.isNotEmpty()) {
            Log.i("AppVpnService", "route excludes: ${entries.joinToString()}")
        }
    }

    private fun applyPackageRules(builder: Builder, options: TunOptions) {
        val include = options.includePackage
        if (include.hasNext()) {
            while (include.hasNext()) {
                runCatching { builder.addAllowedApplication(include.next()) }
                    .onFailure { Log.w("AppVpnService", "allow app failed", it) }
            }
            return
        }

        // 排除自身进程，避免核心连接被 VPN 回环捕获导致无法联网。
        runCatching { builder.addDisallowedApplication(packageName) }
            .onFailure { Log.w("AppVpnService", "disallow self failed", it) }

        val exclude = options.excludePackage
        while (exclude.hasNext()) {
            runCatching { builder.addDisallowedApplication(exclude.next()) }
                .onFailure { Log.w("AppVpnService", "disallow app failed", it) }
        }
    }

    private fun applyHttpProxy(builder: Builder, options: TunOptions) {
        if (!options.isHTTPProxyEnabled) {
            return
        }
        val host = options.httpProxyServer
        val port = options.httpProxyServerPort
        if (host.isNullOrBlank() || port <= 0) {
            return
        }

        val bypass = mutableListOf<String>()
        val bypassIterator = options.httpProxyBypassDomain
        while (bypassIterator.hasNext()) {
            bypass.add(bypassIterator.next())
        }

        val proxy = ProxyInfo.buildDirectProxy(host, port, bypass)
        builder.setHttpProxy(proxy)
    }

    private fun applyUnderlyingNetworks(builder: Builder) {
        val connectivity = getSystemService(ConnectivityManager::class.java) ?: return
        val networks = connectivity.allNetworksCompat().filter { network ->
            val caps = connectivity.getNetworkCapabilities(network) ?: return@filter false
            !caps.hasTransport(NetworkCapabilities.TRANSPORT_VPN)
        }
        if (networks.isNotEmpty()) {
            builder.setUnderlyingNetworks(networks.toTypedArray())
        }
    }

    companion object {
        const val ACTION_START = "cn.moncn.sing_box_windows.vpn.ACTION_START"
        const val ACTION_STOP = "cn.moncn.sing_box_windows.vpn.ACTION_STOP"
        private const val CHANNEL_ID = "singbox_vpn"
        private const val NOTIFICATION_ID = 2001
    }
}
