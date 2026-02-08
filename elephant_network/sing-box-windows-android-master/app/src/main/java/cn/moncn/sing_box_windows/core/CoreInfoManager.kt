package cn.moncn.sing_box_windows.core

import android.os.Handler
import android.os.Looper
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

object CoreInfoManager {
    private val mainHandler = Handler(Looper.getMainLooper())
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var job: Job? = null

    fun start() {
        if (job != null) return
        job = scope.launch {
            while (isActive) {
                if (!ClashApiClient.isConfigured()) {
                    mainHandler.post { CoreInfoStore.reset() }
                    delay(2000)
                    continue
                }
                val version = runCatching {
                    ClashApiClient.getJsonObject("/version")
                }.getOrNull()?.optString("version")?.ifBlank { null }
                if (version != null) {
                    mainHandler.post { CoreInfoStore.updateVersion(version) }
                    delay(30_000)
                } else {
                    delay(2000)
                }
            }
        }
    }

    fun stop() {
        job?.cancel()
        job = null
        mainHandler.post { CoreInfoStore.reset() }
    }
}
