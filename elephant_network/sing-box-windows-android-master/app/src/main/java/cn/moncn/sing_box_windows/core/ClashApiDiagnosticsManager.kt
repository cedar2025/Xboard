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
import org.json.JSONObject

data class EndpointStatus(
    val name: String,
    val path: String,
    val ok: Boolean,
    val code: Int?,
    val message: String?,
    val sample: String?,
    val updatedAt: Long
)

data class ClashApiDiagnostics(
    val baseUrl: String?,
    val hasSecret: Boolean,
    val endpoints: List<EndpointStatus>,
    val trafficSocket: ClashApiStreamManager.SocketStatus?,
    val memorySocket: ClashApiStreamManager.SocketStatus?,
    val updatedAt: Long
)

object ClashApiDiagnosticsStore {
    var state by mutableStateOf<ClashApiDiagnostics?>(null)
        private set

    fun update(state: ClashApiDiagnostics) {
        this.state = state
    }

    fun reset() {
        state = null
    }
}

object ClashApiDiagnosticsManager {
    private val mainHandler = Handler(Looper.getMainLooper())
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var job: Job? = null

    private val endpoints = listOf(
        EndpointSpec("版本", "/version"),
        EndpointSpec("配置", "/configs"),
        EndpointSpec("规则", "/rules"),
        EndpointSpec("节点", "/proxies"),
        EndpointSpec("连接", "/connections"),
        EndpointSpec("流量", "/traffic"),
        EndpointSpec("内存", "/memory")
    )

    fun start() {
        if (job != null) return
        job = scope.launch {
            while (isActive) {
                val snapshot = collectDiagnostics()
                mainHandler.post { ClashApiDiagnosticsStore.update(snapshot) }
                delay(3000)
            }
        }
    }

    fun stop() {
        job?.cancel()
        job = null
        mainHandler.post { ClashApiDiagnosticsStore.reset() }
    }

    private suspend fun collectDiagnostics(): ClashApiDiagnostics {
        val baseUrl = ClashApiClient.baseUrl()
        val hasSecret = ClashApiClient.hasSecret()
        if (!ClashApiClient.isConfigured()) {
            val now = System.currentTimeMillis()
            val results = endpoints.map { spec ->
                EndpointStatus(
                    name = spec.name,
                    path = spec.path,
                    ok = false,
                    code = null,
                    message = "未配置",
                    sample = null,
                    updatedAt = now
                )
            }
            val socketStatus = ClashApiStreamManager.SocketStatus(
                connected = false,
                lastMessageAt = null,
                lastError = "未配置"
            )
            return ClashApiDiagnostics(
                baseUrl = baseUrl,
                hasSecret = hasSecret,
                endpoints = results,
                trafficSocket = socketStatus,
                memorySocket = socketStatus,
                updatedAt = now
            )
        }
        val results = endpoints.map { probe(it) }
        val trafficSocket = ClashApiStreamManager.getTrafficSocketStatus()
        val memorySocket = ClashApiStreamManager.getMemorySocketStatus()
        return ClashApiDiagnostics(
            baseUrl = baseUrl,
            hasSecret = hasSecret,
            endpoints = results,
            trafficSocket = trafficSocket,
            memorySocket = memorySocket,
            updatedAt = System.currentTimeMillis()
        )
    }

    private suspend fun probe(spec: EndpointSpec): EndpointStatus {
        val updatedAt = System.currentTimeMillis()
        return runCatching {
            val response = ClashApiClient.getRaw(spec.path)
            val ok = response.code in 200..299
            EndpointStatus(
                name = spec.name,
                path = spec.path,
                ok = ok,
                code = response.code,
                message = if (ok) "OK" else "HTTP ${response.code}",
                sample = buildSample(spec.path, response.body),
                updatedAt = updatedAt
            )
        }.getOrElse { error ->
            EndpointStatus(
                name = spec.name,
                path = spec.path,
                ok = false,
                code = null,
                message = error.message ?: "请求失败",
                sample = null,
                updatedAt = updatedAt
            )
        }
    }

    private fun buildSample(path: String, body: String): String? {
        val trimmed = body.trim()
        if (trimmed.isBlank()) return null
        if (trimmed.startsWith("{")) {
            val json = runCatching { JSONObject(trimmed) }.getOrNull()
            if (json != null) {
                when {
                    json.has("version") -> return "version=${json.optString("version")}"
                    json.has("mode") -> return "mode=${json.optString("mode")}"
                    json.has("upload_speed") || json.has("download_speed") -> {
                        return "upload_speed=${json.opt("upload_speed")} download_speed=${json.opt("download_speed")}"
                    }
                    json.has("inuse") -> return "inuse=${json.opt("inuse")} oslimit=${json.opt("oslimit")}"
                }
            }
        }
        return if (trimmed.length > MAX_SAMPLE_LENGTH) {
            trimmed.take(MAX_SAMPLE_LENGTH) + "..."
        } else {
            trimmed
        }
    }

    private data class EndpointSpec(
        val name: String,
        val path: String
    )

    private const val MAX_SAMPLE_LENGTH = 140
}
