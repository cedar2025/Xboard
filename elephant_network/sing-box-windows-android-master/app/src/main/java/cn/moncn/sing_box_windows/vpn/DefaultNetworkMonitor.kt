package cn.moncn.sing_box_windows.vpn

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.util.Log
import io.nekohasekai.libbox.InterfaceUpdateListener
import java.net.NetworkInterface

object DefaultNetworkMonitor {
    private var appContext: Context? = null
    private var listener: InterfaceUpdateListener? = null
    private var callback: ConnectivityManager.NetworkCallback? = null

    fun initialize(context: Context) {
        appContext = context.applicationContext
    }

    fun setListener(newListener: InterfaceUpdateListener?) {
        val context = appContext
        if (context == null) {
            Log.w("DefaultNetworkMonitor", "context not initialized")
            return
        }
        val connectivity = context.getSystemService(ConnectivityManager::class.java) ?: return

        if (newListener == null) {
            listener = null
            callback?.let { connectivity.unregisterNetworkCallback(it) }
            callback = null
            return
        }

        listener = newListener
        if (callback != null) {
            update(connectivity, connectivity.activeNetwork)
            return
        }

        val networkCallback = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                update(connectivity, network)
            }

            override fun onCapabilitiesChanged(network: Network, caps: NetworkCapabilities) {
                update(connectivity, network, caps)
            }

            override fun onLost(network: Network) {
                update(connectivity, connectivity.activeNetwork)
            }
        }
        callback = networkCallback
        connectivity.registerDefaultNetworkCallback(networkCallback)
        update(connectivity, connectivity.activeNetwork)
    }

    private fun update(
        connectivity: ConnectivityManager,
        network: Network?,
        caps: NetworkCapabilities? = null
    ) {
        val resolved = resolveNetwork(connectivity, network)
        if (resolved == null) {
            listener?.updateDefaultInterface("", -1, false, false)
            return
        }

        val linkProperties = connectivity.getLinkProperties(resolved)
        val capabilities = if (resolved == network) {
            caps ?: connectivity.getNetworkCapabilities(resolved)
        } else {
            connectivity.getNetworkCapabilities(resolved)
        }
        val interfaceName = linkProperties?.interfaceName.orEmpty()
        val interfaceIndex = NetworkInterface.getByName(interfaceName)?.index ?: -1
        val isExpensive = capabilities?.hasCapability(
            NetworkCapabilities.NET_CAPABILITY_NOT_METERED
        ) == false
        val isConstrained = capabilities?.hasCapability(
            NetworkCapabilities.NET_CAPABILITY_NOT_RESTRICTED
        ) == false

        listener?.updateDefaultInterface(interfaceName, interfaceIndex, isExpensive, isConstrained)
    }

    private fun resolveNetwork(connectivity: ConnectivityManager, network: Network?): Network? {
        if (network == null) {
            return null
        }
        val candidates = connectivity.allNetworksCompat().mapNotNull { candidate ->
            val caps = connectivity.getNetworkCapabilities(candidate) ?: return@mapNotNull null
            if (caps.hasTransport(NetworkCapabilities.TRANSPORT_VPN)) return@mapNotNull null
            if (!caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)) return@mapNotNull null
            candidate to caps
        }
        val preferred = candidates.firstOrNull { it.second.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) }
            ?: candidates.firstOrNull { it.second.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) }
            ?: candidates.firstOrNull { it.second.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) }
            ?: candidates.firstOrNull()
        return preferred?.first ?: network
    }
}
