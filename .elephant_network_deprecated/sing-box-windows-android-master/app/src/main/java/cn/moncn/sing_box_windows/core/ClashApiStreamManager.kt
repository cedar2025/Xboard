package cn.moncn.sing_box_windows.core

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancelChildren
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import okio.ByteString
import org.json.JSONObject
import kotlin.math.roundToLong
import java.util.concurrent.TimeUnit

object ClashApiStreamManager {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val client = OkHttpClient.Builder()
        .readTimeout(0, TimeUnit.MILLISECONDS)
        .build()

    @Volatile
    private var trafficSocket: WebSocket? = null
    @Volatile
    private var memorySocket: WebSocket? = null
    @Volatile
    private var trafficConnected: Boolean = false
    @Volatile
    private var memoryConnected: Boolean = false
    @Volatile
    private var trafficError: String? = null
    @Volatile
    private var memoryError: String? = null
    @Volatile
    private var trafficSupported: Boolean? = null
    @Volatile
    private var memorySupported: Boolean? = null
    @Volatile
    private var trafficRetryAt: Long = 0L
    @Volatile
    private var memoryRetryAt: Long = 0L
    @Volatile
    private var trafficSnapshot: TrafficSnapshot? = null
    @Volatile
    private var memorySnapshot: MemorySnapshot? = null

    data class TrafficSnapshot(
        val uplinkBytes: Long,
        val downlinkBytes: Long,
        val updatedAt: Long
    )

    data class MemorySnapshot(
        val memoryBytes: Long,
        val updatedAt: Long
    )

    fun start() {
        if (!ClashApiClient.isConfigured()) return
        connectTraffic()
        connectMemory()
    }

    fun stop() {
        scope.coroutineContext.cancelChildren()
        trafficSocket?.close(1000, "stop")
        memorySocket?.close(1000, "stop")
        trafficSocket = null
        memorySocket = null
        trafficConnected = false
        memoryConnected = false
        trafficError = null
        memoryError = null
        trafficSnapshot = null
        memorySnapshot = null
        trafficSupported = null
        memorySupported = null
        trafficRetryAt = 0L
        memoryRetryAt = 0L
    }

    fun getTrafficSnapshot(): TrafficSnapshot? = trafficSnapshot

    fun getMemorySnapshot(): MemorySnapshot? = memorySnapshot

    fun getTrafficSocketStatus(): SocketStatus {
        return SocketStatus(
            connected = trafficConnected,
            lastMessageAt = trafficSnapshot?.updatedAt,
            lastError = trafficError
        )
    }

    fun getMemorySocketStatus(): SocketStatus {
        return SocketStatus(
            connected = memoryConnected,
            lastMessageAt = memorySnapshot?.updatedAt,
            lastError = memoryError
        )
    }

    private fun connectTraffic() {
        if (trafficSocket != null) return
        if (trafficSupported == false && System.currentTimeMillis() < trafficRetryAt) {
            return
        }
        if (trafficSupported == false) {
            trafficSupported = null
        }
        val request = buildRequest("/traffic") ?: return
        android.util.Log.d("ClashApiStream", "Connecting to /traffic WebSocket...")
        trafficSocket = client.newWebSocket(
            request,
            object : WebSocketListener() {
                override fun onOpen(webSocket: WebSocket, response: Response) {
                    android.util.Log.d("ClashApiStream", "/traffic WebSocket connected")
                    trafficSupported = true
                    trafficConnected = true
                    trafficError = null
                }

                override fun onMessage(webSocket: WebSocket, text: String) {
                    trafficConnected = true
                    val snapshot = parseTraffic(text)
                    if (snapshot != null) {
                        android.util.Log.v("ClashApiStream", "/traffic: up=${snapshot.uplinkBytes}, down=${snapshot.downlinkBytes}")
                    }
                    trafficSnapshot = snapshot
                }

                override fun onMessage(webSocket: WebSocket, bytes: ByteString) {
                    trafficConnected = true
                    val snapshot = parseTraffic(bytes.utf8())
                    if (snapshot != null) {
                        android.util.Log.v("ClashApiStream", "/traffic bytes: up=${snapshot.uplinkBytes}, down=${snapshot.downlinkBytes}")
                    }
                    trafficSnapshot = snapshot
                }

                override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                    android.util.Log.e("ClashApiStream", "/traffic WebSocket failed: ${t.message}, code=${response?.code}")
                    trafficSocket = null
                    trafficConnected = false
                    trafficError = t.message ?: "连接失败"
                    if (isUnsupported(response)) {
                        trafficSupported = false
                        trafficRetryAt = System.currentTimeMillis() + UNSUPPORTED_RETRY_MS
                    } else {
                        scheduleReconnect { connectTraffic() }
                    }
                }

                override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                    android.util.Log.d("ClashApiStream", "/traffic WebSocket closed: $code $reason")
                    trafficSocket = null
                    trafficConnected = false
                    trafficError = reason.takeIf { it.isNotBlank() }
                    if (trafficSupported != false) {
                        scheduleReconnect { connectTraffic() }
                    }
                }
            }
        )
    }

    private fun connectMemory() {
        if (memorySocket != null) return
        if (memorySupported == false && System.currentTimeMillis() < memoryRetryAt) {
            return
        }
        if (memorySupported == false) {
            memorySupported = null
        }
        val request = buildRequest("/memory") ?: return
        android.util.Log.d("ClashApiStream", "Connecting to /memory WebSocket...")
        memorySocket = client.newWebSocket(
            request,
            object : WebSocketListener() {
                override fun onOpen(webSocket: WebSocket, response: Response) {
                    android.util.Log.d("ClashApiStream", "/memory WebSocket connected")
                    memorySupported = true
                    memoryConnected = true
                    memoryError = null
                }

                override fun onMessage(webSocket: WebSocket, text: String) {
                    memoryConnected = true
                    val snapshot = parseMemory(text)
                    if (snapshot != null) {
                        android.util.Log.v("ClashApiStream", "/memory: ${snapshot.memoryBytes} bytes")
                    }
                    memorySnapshot = snapshot
                }

                override fun onMessage(webSocket: WebSocket, bytes: ByteString) {
                    memoryConnected = true
                    val snapshot = parseMemory(bytes.utf8())
                    if (snapshot != null) {
                        android.util.Log.v("ClashApiStream", "/memory bytes: ${snapshot.memoryBytes} bytes")
                    }
                    memorySnapshot = snapshot
                }

                override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                    android.util.Log.e("ClashApiStream", "/memory WebSocket failed: ${t.message}, code=${response?.code}")
                    memorySocket = null
                    memoryConnected = false
                    memoryError = t.message ?: "连接失败"
                    if (isUnsupported(response)) {
                        memorySupported = false
                        memoryRetryAt = System.currentTimeMillis() + UNSUPPORTED_RETRY_MS
                    } else {
                        scheduleReconnect { connectMemory() }
                    }
                }

                override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                    android.util.Log.d("ClashApiStream", "/memory WebSocket closed: $code $reason")
                    memorySocket = null
                    memoryConnected = false
                    memoryError = reason.takeIf { it.isNotBlank() }
                    if (memorySupported != false) {
                        scheduleReconnect { connectMemory() }
                    }
                }
            }
        )
    }

    private fun scheduleReconnect(action: () -> Unit) {
        scope.launch {
            delay(2000)
            action()
        }
    }

    private fun buildRequest(path: String): Request? {
        if (!ClashApiClient.isConfigured()) return null
        val url = runCatching { ClashApiClient.buildWebSocketUrl(path) }.getOrNull() ?: return null
        val builder = Request.Builder().url(url)
        ClashApiClient.authorizationHeader()?.let { builder.addHeader("Authorization", it) }
        return builder.build()
    }

    private fun parseTraffic(payload: String): TrafficSnapshot? {
        // 打印原始数据用于调试
        android.util.Log.v("ClashApiStream", "Traffic payload: $payload")
        val json = runCatching { JSONObject(payload) }.getOrNull() ?: return null
        val up = readNumber(json, "up", "upload", "uplink", "tx", "upload_speed")
        val down = readNumber(json, "down", "download", "downlink", "rx", "download_speed")
        android.util.Log.v("ClashApiStream", "Parsed: up=$up, down=$down")
        if (up == null && down == null) {
            // 部分内核会在流量通道返回内存数据，兼容该情况。
            val memory = parseMemory(payload)
            if (memory != null) {
                memorySnapshot = memory
            }
            return null
        }
        val usesKbps = json.has("upload_speed") || json.has("download_speed")
        val uplinkBytes = up?.let { if (usesKbps) kbpsToBytes(it) else it } ?: 0L
        val downlinkBytes = down?.let { if (usesKbps) kbpsToBytes(it) else it } ?: 0L
        return TrafficSnapshot(uplinkBytes, downlinkBytes, System.currentTimeMillis())
    }

    private fun parseMemory(payload: String): MemorySnapshot? {
        val json = runCatching { JSONObject(payload) }.getOrNull() ?: return null
        val inuse = readNumber(json, "inuse")
        if (inuse != null && inuse > 0) {
            return MemorySnapshot(inuse, System.currentTimeMillis())
        }
        val used = readNumber(json, "used_memory", "memory")
        if (used != null && used > 0) {
            return MemorySnapshot(used * 1024L, System.currentTimeMillis())
        }
        return null
    }

    private fun readNumber(json: JSONObject, vararg keys: String): Long? {
        for (key in keys) {
            if (json.has(key)) {
                val value = json.optDouble(key, Double.NaN)
                if (!value.isNaN()) {
                    return value.roundToLong()
                }
            }
        }
        return null
    }

    private fun kbpsToBytes(valueKbps: Long): Long {
        return (valueKbps * 1000.0 / 8.0).roundToLong().coerceAtLeast(0L)
    }

    private fun isUnsupported(response: Response?): Boolean {
        val code = response?.code ?: return false
        return code == 404 || code == 400
    }

    private const val UNSUPPORTED_RETRY_MS = 30_000L

    data class SocketStatus(
        val connected: Boolean,
        val lastMessageAt: Long?,
        val lastError: String?
    )
}
