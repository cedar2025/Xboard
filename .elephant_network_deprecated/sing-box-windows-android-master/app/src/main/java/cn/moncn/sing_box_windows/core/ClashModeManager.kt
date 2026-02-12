package cn.moncn.sing_box_windows.core

import android.os.Handler
import android.os.Looper
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

/**
 * Clash 运行模式管理器
 * 支持的模式: rule(规则模式), global(全局模式), direct(直连模式)
 */
object ClashModeManager {
    private val mainHandler = Handler(Looper.getMainLooper())
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var job: Job? = null

    // 当前模式
    var currentMode by mutableStateOf<ClashMode?>(null)
        private set

    // 是否支持模式切换
    var isModeSupported by mutableStateOf(false)
        private set

    sealed class ClashMode(val value: String, val displayName: String) {
        data object Rule : ClashMode("rule", "规则模式")
        data object Global : ClashMode("global", "全局模式")
        data object Direct : ClashMode("direct", "直连模式")

        companion object {
            fun fromValue(value: String): ClashMode? {
                return when (value.lowercase()) {
                    "rule" -> Rule
                    "global" -> Global
                    "direct" -> Direct
                    else -> null
                }
            }
        }
    }

    fun start() {
        if (job != null) return
        job = scope.launch {
            while (isActive) {
                if (!ClashApiClient.isConfigured()) {
                    mainHandler.post {
                        currentMode = null
                        isModeSupported = false
                    }
                    delay(3000)
                    continue
                }
                fetchMode()
                delay(5000)
            }
        }
    }

    fun stop() {
        job?.cancel()
        job = null
        mainHandler.post {
            currentMode = null
            isModeSupported = false
        }
    }

    /**
     * 切换运行模式
     */
    suspend fun switchMode(mode: ClashMode): Result<Unit> = withContext(Dispatchers.IO) {
        if (!ClashApiClient.isConfigured()) {
            return@withContext Result.failure(IllegalStateException("Clash API 未配置"))
        }

        runCatching {
            val body = JSONObject().put("mode", mode.value)
            ClashApiClient.putJson("/configs", body)
            // 立即更新本地状态
            mainHandler.post { currentMode = mode }
            Unit
        }
    }

    private suspend fun fetchMode() {
        val configs = runCatching {
            ClashApiClient.getJsonObject("/configs")
        }.getOrNull() ?: return

        val modeValue = configs.optString("mode")
        if (modeValue.isNotBlank()) {
            val mode = ClashMode.fromValue(modeValue)
            mainHandler.post {
                currentMode = mode
                isModeSupported = mode != null
            }
        }
    }
}
