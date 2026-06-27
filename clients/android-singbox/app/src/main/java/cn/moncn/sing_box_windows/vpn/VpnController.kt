package cn.moncn.sing_box_windows.vpn

import android.content.Context
import android.content.Intent
import androidx.core.content.ContextCompat

object VpnController {
    fun start(context: Context) {
        val intent = Intent(context, AppVpnService::class.java).apply {
            action = AppVpnService.ACTION_START
        }
        ContextCompat.startForegroundService(context, intent)
    }

    fun stop(context: Context) {
        val intent = Intent(context, AppVpnService::class.java).apply {
            action = AppVpnService.ACTION_STOP
        }
        context.startService(intent)
    }
}
