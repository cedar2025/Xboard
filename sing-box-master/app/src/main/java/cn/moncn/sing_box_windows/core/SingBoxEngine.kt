package cn.moncn.sing_box_windows.core

import io.nekohasekai.libbox.BoxService
import io.nekohasekai.libbox.Libbox
import io.nekohasekai.libbox.PlatformInterface

object SingBoxEngine {
    private var started = false
    private var service: BoxService? = null

    fun start(configJson: String, platform: PlatformInterface): CoreStartResult {
        if (started) {
            return CoreStartResult(ok = true)
        }
        return try {
            val instance = Libbox.newService(configJson, platform)
            instance.start()
            CommandServerHolder.start(instance)
            ClashApiClient.configureFromConfig(configJson)
            CoreStatusManager.start()
            OutboundGroupManager.start()
            CoreInfoManager.start()
            service = instance
            LibboxServiceHolder.service = instance
            started = true
            CoreStartResult(ok = true)
        } catch (e: Exception) {
            CoreStartResult(ok = false, error = e.message ?: "core start failed")
        }
    }

    fun stop() {
        if (!started) {
            return
        }
        OutboundGroupManager.stop()
        CoreStatusManager.stop()
        CoreInfoManager.stop()
        CommandServerHolder.stop()
        try {
            service?.close()
        } catch (_: Exception) {
            // Ignore close errors.
        }
        LibboxServiceHolder.service = null
        service = null
        ClashApiClient.reset()
        started = false
    }

    fun isRunning(): Boolean = started
}
