package com.elephantroute

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Build
import android.os.Process
import android.system.OsConstants
import android.util.Log
import androidx.annotation.RequiresApi
import io.nekohasekai.libbox.InterfaceUpdateListener
import io.nekohasekai.libbox.Libbox
import io.nekohasekai.libbox.LocalDNSTransport
import io.nekohasekai.libbox.NetworkInterfaceIterator
import io.nekohasekai.libbox.PlatformInterface
import io.nekohasekai.libbox.StringIterator
import io.nekohasekai.libbox.TunOptions
import io.nekohasekai.libbox.WIFIState
import io.nekohasekai.libbox.NetworkInterface as LibboxNetworkInterface
import java.net.Inet6Address
import java.net.InetSocketAddress
import java.net.InterfaceAddress
import java.net.NetworkInterface

class PlatformInterfaceBridge(
    private val context: Context,
    private val vpnService: SingboxVpnService
) : PlatformInterface {
    private val connectivity = context.getSystemService(ConnectivityManager::class.java)

    override fun usePlatformAutoDetectInterfaceControl(): Boolean = true

    override fun autoDetectInterfaceControl(fd: Int) {
        vpnService.protect(fd)
    }

    override fun openTun(options: TunOptions): Int {
        return vpnService.openTunForCore(options)
    }

    override fun useProcFS(): Boolean = Build.VERSION.SDK_INT < Build.VERSION_CODES.Q

    @RequiresApi(Build.VERSION_CODES.Q)
    override fun findConnectionOwner(
        ipProtocol: Int,
        sourceAddress: String,
        sourcePort: Int,
        destinationAddress: String,
        destinationPort: Int
    ): io.nekohasekai.libbox.ConnectionOwner? {
        return null
    }

    override fun startDefaultInterfaceMonitor(listener: InterfaceUpdateListener) {
    }

    override fun closeDefaultInterfaceMonitor(listener: InterfaceUpdateListener) {
    }

    override fun getInterfaces(): NetworkInterfaceIterator {
        val networks = connectivity?.allNetworks ?: emptyArray()
        val networkInterfaces = NetworkInterface.getNetworkInterfaces().toList()
        val interfaces = mutableListOf<LibboxNetworkInterface>()
        
        for (network in networks) {
            try {
                val boxInterface = LibboxNetworkInterface()
                val linkProperties = connectivity?.getLinkProperties(network) ?: continue
                val networkCapabilities = connectivity?.getNetworkCapabilities(network) ?: continue
                boxInterface.name = linkProperties.interfaceName ?: "unknown"
                
                val networkInterface = networkInterfaces.find { it.name == boxInterface.name }
                
                boxInterface.dnsServer = StringArray(linkProperties.dnsServers.mapNotNull { it.hostAddress })
                
                boxInterface.type = when {
                    networkCapabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> Libbox.InterfaceTypeWIFI
                    networkCapabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> Libbox.InterfaceTypeCellular
                    networkCapabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) -> Libbox.InterfaceTypeEthernet
                    else -> Libbox.InterfaceTypeOther
                }
                
                boxInterface.index = networkInterface?.index ?: -1
                
                if (networkInterface != null) {
                    boxInterface.addresses = StringArray(networkInterface.interfaceAddresses.map { it.toPrefix() })
                } else {
                    boxInterface.addresses = StringArray(emptyList())   
                }

                var flags = 0
                if (networkCapabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)) {
                    flags = OsConstants.IFF_UP or OsConstants.IFF_RUNNING
                }
                
                boxInterface.flags = flags
                interfaces.add(boxInterface)
            } catch (e: Exception) {
            }
        }
        return InterfaceArray(interfaces.iterator())
    }

    override fun underNetworkExtension(): Boolean = false

    override fun includeAllNetworks(): Boolean = false

    override fun clearDNSCache() = Unit

    override fun readWIFIState(): WIFIState? {
        return WIFIState("", "")
    }

    override fun localDNSTransport(): LocalDNSTransport? = null

    override fun systemCertificates(): StringIterator {
        return StringArray(emptyList())
    }

    override fun sendNotification(notification: io.nekohasekai.libbox.Notification) {
         Log.i("Libbox", "Notification: ${notification.title} - ${notification.body}")
    }

    private class InterfaceArray(private val iterator: Iterator<LibboxNetworkInterface>) :
        NetworkInterfaceIterator {
        override fun hasNext(): Boolean = iterator.hasNext()
        override fun next(): LibboxNetworkInterface = iterator.next()
    }

    private class StringArray(items: List<String>) : StringIterator {
        private val list = items
        private var index = 0

        override fun len(): Int = list.size
        override fun hasNext(): Boolean = index < list.size
        override fun next(): String = list[index++]
    }

    private fun InterfaceAddress.toPrefix(): String {
        return if (address is Inet6Address) {
            "${Inet6Address.getByAddress(address.address).hostAddress}/${networkPrefixLength}"
        } else {
            "${address.hostAddress}/${networkPrefixLength}"
        }
    }
}
