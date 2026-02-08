package cn.moncn.sing_box_windows.vpn

import android.net.ConnectivityManager
import android.net.Network

@Suppress("DEPRECATION")
fun ConnectivityManager.allNetworksCompat(): Array<Network> {
    // Legacy API kept in one place so the call sites stay warning-free.
    return allNetworks
}
