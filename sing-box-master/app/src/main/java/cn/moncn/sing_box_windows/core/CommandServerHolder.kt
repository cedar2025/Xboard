package cn.moncn.sing_box_windows.core

import io.nekohasekai.libbox.BoxService
import io.nekohasekai.libbox.CommandServer
import io.nekohasekai.libbox.CommandServerHandler
import io.nekohasekai.libbox.Libbox
import io.nekohasekai.libbox.SystemProxyStatus

object CommandServerHolder {
    private var server: CommandServer? = null

    private val handler = object : CommandServerHandler {
        override fun serviceReload() {
            throw UnsupportedOperationException("service reload not supported")
        }

        override fun postServiceClose() = Unit

        override fun getSystemProxyStatus(): SystemProxyStatus {
            return SystemProxyStatus().apply {
                available = false
                enabled = false
            }
        }

        override fun setSystemProxyEnabled(isEnabled: Boolean) = Unit
    }

    fun start(service: BoxService) {
        if (server == null) {
            server = Libbox.newCommandServer(handler, 128)
            runCatching { server?.start() }
        }
        runCatching { server?.setService(service) }
    }

    fun stop() {
        runCatching { server?.close() }
        server = null
    }
}
