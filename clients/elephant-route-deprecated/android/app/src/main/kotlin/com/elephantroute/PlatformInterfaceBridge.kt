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
        // Libbox on Android explicitly blocks all 'direct' outbounds with 
        // "no available network interface" if it never receives a default interface update,
        // as a built-in protection against TUN routing loops.
        // We must provide a dummy or real active interface to unlock it.
        Thread {
            try {
                // Give it a short delay to ensure libbox is fully initialized listening
                Thread.sleep(500)
                val networkInterfaces = NetworkInterface.getNetworkInterfaces()?.toList() ?: emptyList()
                val activeInfo = networkInterfaces.find { !it.name.startsWith("lo") && it.name != "tun0" && it.isUp }
                if (activeInfo != null) {
                    Log.i("PlatformBridge", "Notifying Libbox of active default interface: ${activeInfo.name} idx ${activeInfo.index}")
                    listener.updateDefaultInterface(activeInfo.name, activeInfo.index, false, false)
                } else {
                    // Fallback to a safe guess if reflection fails
                    listener.updateDefaultInterface("wlan0", 0, false, false)
                }
            } catch (e: Exception) {
                Log.e("PlatformBridge", "Error in default interface monitor", e)
                listener.updateDefaultInterface("wlan0", 0, false, false)
            }
        }.start()
    }

    override fun closeDefaultInterfaceMonitor(listener: InterfaceUpdateListener) {
    }

    override fun getInterfaces(): NetworkInterfaceIterator {
        val networkInterfaces = NetworkInterface.getNetworkInterfaces()?.toList() ?: emptyList()
        val interfaces = mutableListOf<LibboxNetworkInterface>()
        
        for (networkInterface in networkInterfaces) {
            try {
                // Ignore empty or invalid interfaces like loopback for outbounds
                if (networkInterface.name.startsWith("lo")) continue

                val boxInterface = LibboxNetworkInterface()
                boxInterface.name = networkInterface.name
                boxInterface.index = networkInterface.index
                
                boxInterface.addresses = StringArray(networkInterface.interfaceAddresses.map { it.toPrefix() })
                boxInterface.dnsServer = StringArray(emptyList()) // DNS handled by Android Native usually
                
                // Set to generic type if we don't query ConnectivityManager
                boxInterface.type = Libbox.InterfaceTypeOther
                
                // FORCE the interface to be UP and RUNNING so Libbox doesn't block traffic
                // thinking the device is offline!
                var flags = 0
                if (networkInterface.isUp) {
                    flags = flags or OsConstants.IFF_UP or OsConstants.IFF_RUNNING
                }
                
                // If it has addresses and is not loopback, definitely assume it's capable
                if (networkInterface.interfaceAddresses.isNotEmpty()) {
                    flags = flags or OsConstants.IFF_UP or OsConstants.IFF_RUNNING
                }
                
                boxInterface.flags = flags
                interfaces.add(boxInterface)
            } catch (e: Exception) {
                // Ignore single interface errors
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
