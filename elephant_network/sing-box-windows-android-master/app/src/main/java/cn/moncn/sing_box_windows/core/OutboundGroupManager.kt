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
import org.json.JSONArray
import org.json.JSONObject
import java.net.URLEncoder
import java.time.Instant

data class OutboundItemModel(
    val tag: String,
    val type: String,
    val delayMs: Int?,
    val lastTestAt: Long?,
    val isTesting: Boolean = false
)

data class OutboundGroupModel(
    val tag: String,
    val type: String,
    val selectable: Boolean,
    val selected: String,
    val items: List<OutboundItemModel>
)

object OutboundGroupManager {
    private val mainHandler = Handler(Looper.getMainLooper())
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var refreshJob: Job? = null
    private var lastTestSnapshot: Map<String, TestSnapshot> = emptyMap()
    private var testingTags: Map<String, TestingState> = emptyMap()

    var groups by mutableStateOf<List<OutboundGroupModel>>(emptyList())
        private set

    data class TestSnapshot(
        val delayMs: Int?,
        val lastTestAt: Long?
    )

    data class TestingState(
        val baseline: TestSnapshot?,
        val startedAt: Long
    )

    fun start() {
        if (refreshJob != null) return
        refreshJob = scope.launch {
            while (isActive) {
                if (!ClashApiClient.isConfigured()) {
                    mainHandler.post { groups = emptyList() }
                    delay(1000)
                    continue
                }
                refreshGroups()
                delay(3000)
            }
        }
    }

    fun stop() {
        refreshJob?.cancel()
        refreshJob = null
        mainHandler.post { groups = emptyList() }
    }

    fun select(groupTag: String, outboundTag: String) {
        scope.launch {
            runCatching {
                val encodedGroup = encodePathSegment(groupTag)
                val body = JSONObject().put("name", outboundTag)
                ClashApiClient.putJson("/proxies/$encodedGroup", body)
            }
            runCatching { ClashApiClient.delete("/connections") }
            mainHandler.post {
                groups = groups.map { group ->
                    if (group.tag == groupTag) {
                        group.copy(selected = outboundTag)
                    } else {
                        group
                    }
                }
            }
        }
    }

    suspend fun urlTest(outboundTag: String): Result<Unit> {
        if (!ClashApiClient.isConfigured()) {
            return Result.failure(IllegalStateException("核心未运行"))
        }
        val encodedProxy = encodePathSegment(outboundTag)
        val result = runCatching {
            ClashApiClient.getJsonObject(
                "/proxies/$encodedProxy/delay",
                mapOf("url" to TEST_URL, "timeout" to TEST_TIMEOUT_MS.toString())
            )
        }.map { response ->
            val delay = readDelay(response)
            if (delay != null) {
                recordTestResult(outboundTag, delay)
            } else {
                markTesting(outboundTag)
            }
        }
        return result
    }

    private fun buildTestSnapshot(
        groupList: List<OutboundGroupModel>
    ): Map<String, TestSnapshot> {
        val snapshot = mutableMapOf<String, TestSnapshot>()
        groupList.forEach { group ->
            group.items.forEach { item ->
                if (item.delayMs == null && item.lastTestAt == null) return@forEach
                val current = snapshot[item.tag]
                if (current == null || (item.lastTestAt ?: 0L) > (current.lastTestAt ?: 0L)) {
                    snapshot[item.tag] = TestSnapshot(item.delayMs, item.lastTestAt)
                }
            }
        }
        return snapshot
    }

    private fun normalizeTestResults(
        groupList: List<OutboundGroupModel>,
        snapshot: Map<String, TestSnapshot>
    ): List<OutboundGroupModel> {
        return groupList.map { group ->
            val items = group.items.map { item ->
                val resolved = snapshot[item.tag]
                val delayMs = item.delayMs ?: resolved?.delayMs
                val lastTestAt = item.lastTestAt ?: resolved?.lastTestAt
                item.copy(delayMs = delayMs, lastTestAt = lastTestAt)
            }
            group.copy(items = items)
        }
    }

    private fun markTesting(tag: String) {
        mainHandler.post {
            val baseline = lastTestSnapshot[tag]
            val startedAt = System.currentTimeMillis()
            testingTags = testingTags + (tag to TestingState(baseline, startedAt))
            refreshTestingState()
            mainHandler.postDelayed({
                val current = testingTags[tag] ?: return@postDelayed
                if (current.startedAt == startedAt) {
                    testingTags = testingTags - tag
                    refreshTestingState()
                }
            }, 15_000)
        }
    }

    private fun refreshTestingState() {
        val testingSet = testingTags.keys
        mainHandler.post {
            groups = groups.map { group ->
                val items = group.items.map { item ->
                    item.copy(isTesting = testingSet.contains(item.tag))
                }
                group.copy(items = items)
            }
        }
    }

    private suspend fun refreshGroups() {
        val response = runCatching { ClashApiClient.getJsonObject("/proxies") }.getOrNull()
            ?: return
        val proxies = response.optJSONObject("proxies") ?: return
        val detailMap = mutableMapOf<String, JSONObject>()
        val keys = proxies.keys()
        while (keys.hasNext()) {
            val key = keys.next()
            proxies.optJSONObject(key)?.let { detailMap[key] = it }
        }
        val groupList = mutableListOf<OutboundGroupModel>()
        detailMap.forEach { (groupName, detail) ->
            val all = detail.optJSONArray("all") ?: return@forEach
            if (all.length() == 0) return@forEach
            val selected = detail.optString("now", detail.optString("selected", ""))
                .ifBlank { all.optString(0) }
            val type = detail.optString("type", "unknown")
            val normalizedType = normalizeType(type)
            val selectable = isSelectableGroup(normalizedType)
            val items = buildItems(all, detailMap)
            groupList.add(
                OutboundGroupModel(
                    tag = groupName,
                    type = normalizedType,
                    selectable = selectable,
                    selected = selected,
                    items = items
                )
            )
        }
        val snapshot = buildTestSnapshot(groupList)
        val updatedTesting = testingTags.filterNot { (tag, state) ->
            val current = snapshot[tag]
            current != null && current != state.baseline
        }
        val normalizedGroups = normalizeTestResults(groupList, snapshot)
        val testingSet = updatedTesting.keys
        val displayedGroups = normalizedGroups.map { group ->
            val updatedItems = group.items.map { item ->
                item.copy(isTesting = testingSet.contains(item.tag))
            }
            group.copy(items = updatedItems)
        }
        mainHandler.post {
            lastTestSnapshot = snapshot
            testingTags = updatedTesting
            groups = displayedGroups
        }
    }

    private fun buildItems(
        items: JSONArray,
        detailMap: Map<String, JSONObject>
    ): List<OutboundItemModel> {
        val result = mutableListOf<OutboundItemModel>()
        for (index in 0 until items.length()) {
            val tag = items.optString(index)
            if (tag.isBlank()) continue
            val detail = detailMap[tag]
            val type = normalizeType(detail?.optString("type", "unknown") ?: "unknown")
            val history = detail?.optJSONArray("history")
            val snapshot = resolveHistory(history)
            result.add(
                OutboundItemModel(
                    tag = tag,
                    type = type,
                    delayMs = snapshot?.delayMs,
                    lastTestAt = snapshot?.lastTestAt
                )
            )
        }
        return result
    }

    private fun resolveHistory(history: JSONArray?): TestSnapshot? {
        if (history == null || history.length() == 0) return null
        val last = history.optJSONObject(history.length() - 1) ?: return null
        val delay = readDelay(last)
        val testTime = readHistoryTime(last)
        return TestSnapshot(delay, testTime)
    }

    private fun isSelectableGroup(type: String): Boolean {
        return type in setOf("selector", "urltest", "fallback", "loadbalance")
    }

    private fun normalizeType(value: String): String {
        return value.trim().lowercase().replace("-", "").replace("_", "").replace(" ", "")
    }

    private fun readDelay(value: JSONObject): Int? {
        val delay = value.optInt("delay", -1)
        if (delay > 0) return delay
        val latency = value.optInt("latency", -1)
        return latency.takeIf { it > 0 }
    }

    private fun readHistoryTime(value: JSONObject): Long? {
        val timeText = value.optString("time", "")
        if (timeText.isNotBlank()) {
            return runCatching { Instant.parse(timeText).toEpochMilli() }.getOrNull()
        }
        val numeric = value.optLong("time", 0L)
        if (numeric <= 0L) return null
        return if (numeric > 1_000_000_000_000L) numeric else numeric * 1000L
    }

    private fun recordTestResult(tag: String, delayMs: Int) {
        val now = System.currentTimeMillis()
        mainHandler.post {
            lastTestSnapshot = lastTestSnapshot + (tag to TestSnapshot(delayMs, now))
            testingTags = testingTags - tag
            groups = groups.map { group ->
                val updatedItems = group.items.map { item ->
                    if (item.tag == tag) {
                        item.copy(delayMs = delayMs, lastTestAt = now, isTesting = false)
                    } else {
                        item
                    }
                }
                group.copy(items = updatedItems)
            }
        }
    }

    private fun encodePathSegment(value: String): String {
        return URLEncoder.encode(value, "UTF-8").replace("+", "%20")
    }

    private const val TEST_URL = "http://www.gstatic.com/generate_204"
    private const val TEST_TIMEOUT_MS = 5000
}
