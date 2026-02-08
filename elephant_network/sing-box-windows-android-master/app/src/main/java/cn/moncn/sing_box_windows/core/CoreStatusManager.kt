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
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject

/**
 * 核心状态管理器 - 使用 WebSocket 获取实时流量，HTTP API 获取其他数据
 */
object CoreStatusManager {
    private val mainHandler = Handler(Looper.getMainLooper())
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var job: Job? = null

    // 累计流量统计
    private var totalUplinkBytes = 0L
    private var totalDownlinkBytes = 0L
    private var lastUpdateTime = 0L

    // 缓存的静态数据（不需要频繁更新）
    private var cachedMode: String? = null
    private var cachedRulesCount: Int? = null
    private var lastStaticDataFetchTime = 0L
    private const val STATIC_DATA_FETCH_INTERVAL = 5000L // 静态数据每5秒更新一次

    fun start() {
        if (job != null) return
        job = scope.launch {
            while (isActive) {
                if (!ClashApiClient.isConfigured()) {
                    ClashApiStreamManager.stop()
                    mainHandler.post { CoreStatusStore.reset() }
                    // 重置所有缓存
                    resetStats()
                    delay(1000)
                    continue
                }
                ClashApiStreamManager.start()
                val snapshot = fetchStatus()
                if (snapshot != null) {
                    mainHandler.post { CoreStatusStore.update(snapshot) }
                }
                delay(1000)
            }
        }
    }

    fun stop() {
        job?.cancel()
        job = null
        ClashApiStreamManager.stop()
        mainHandler.post { CoreStatusStore.reset() }
    }

    private fun resetStats() {
        totalUplinkBytes = 0L
        totalDownlinkBytes = 0L
        lastUpdateTime = 0L
        cachedMode = null
        cachedRulesCount = null
        lastStaticDataFetchTime = 0L
    }

    private suspend fun fetchStatus(): CoreStatus? = withContext(Dispatchers.IO) {
        val trafficSnapshot = ClashApiStreamManager.getTrafficSnapshot()
        val memorySnapshot = ClashApiStreamManager.getMemorySnapshot()

        // 只要有 WebSocket 数据就返回状态
        if (trafficSnapshot == null && memorySnapshot == null) {
            return@withContext null
        }

        val now = System.currentTimeMillis()
        val memoryBytes = memorySnapshot?.memoryBytes ?: 0L
        val uplinkSpeed = trafficSnapshot?.uplinkBytes ?: 0L
        val downlinkSpeed = trafficSnapshot?.downlinkBytes ?: 0L

        // 累计流量计算
        if (lastUpdateTime > 0 && trafficSnapshot != null) {
            val deltaTime = (now - lastUpdateTime) / 1000.0
            if (deltaTime > 0) {
                val uplinkDelta = (uplinkSpeed * deltaTime).toLong()
                val downlinkDelta = (downlinkSpeed * deltaTime).toLong()
                totalUplinkBytes += uplinkDelta
                totalDownlinkBytes += downlinkDelta
            }
        }
        lastUpdateTime = now

        // 获取连接数
        var connectionsCount = 0
        runCatching {
            val connResult = ClashApiClient.getJsonValue("/connections")
            if (connResult is JSONArray) {
                connectionsCount = connResult.length()
            } else if (connResult is JSONObject) {
                val list = connResult.optJSONArray("connections")
                connectionsCount = list?.length() ?: 0
            }
        }.getOrNull()

        // 获取静态数据（模式、规则数）- 降低频率
        if (now - lastStaticDataFetchTime > STATIC_DATA_FETCH_INTERVAL) {
            lastStaticDataFetchTime = now
            runCatching {
                val configs = ClashApiClient.getJsonObject("/configs")
                cachedMode = configs?.optString("mode")
            }.getOrNull()

            runCatching {
                val rules = ClashApiClient.getJsonValue("/rules")
                cachedRulesCount = when (rules) {
                    is JSONArray -> rules.length()
                    is JSONObject -> rules.optJSONArray("rules")?.length()
                    else -> null
                }
            }.getOrNull()
        }

        CoreStatus(
            memoryBytes = memoryBytes,
            goroutines = 0,
            connectionsIn = connectionsCount,
            connectionsOut = 0,
            trafficAvailable = trafficSnapshot != null,
            uplinkBytes = uplinkSpeed,
            downlinkBytes = downlinkSpeed,
            uplinkTotalBytes = totalUplinkBytes,
            downlinkTotalBytes = totalDownlinkBytes,
            mode = cachedMode,
            rulesCount = cachedRulesCount,
            updatedAt = now
        )
    }
}
