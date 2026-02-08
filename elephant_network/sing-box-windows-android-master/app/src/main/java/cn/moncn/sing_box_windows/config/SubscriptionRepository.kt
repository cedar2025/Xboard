package cn.moncn.sing_box_windows.config

import android.content.Context
import android.net.Uri
import android.util.Base64
import org.json.JSONArray
import org.json.JSONObject
import java.io.ByteArrayInputStream
import java.io.File
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLDecoder
import java.util.UUID
import java.util.zip.GZIPInputStream

data class SubscriptionItem(
    val id: String,
    val name: String,
    val url: String,
    val lastUpdatedAt: Long? = null,
    val lastError: String? = null,
    val isLocal: Boolean = false,
    val content: String? = null
)

data class SubscriptionState(
    val items: List<SubscriptionItem>,
    val selectedId: String?
) {
    fun selected(): SubscriptionItem? = items.firstOrNull { it.id == selectedId }

    companion object {
        fun empty(): SubscriptionState = SubscriptionState(emptyList(), null)
    }
}

data class SubscriptionUpdateResult(
    val ok: Boolean,
    val message: String,
    val warnings: List<String>,
    val state: SubscriptionState
)

data class SubscriptionAddResult(
    val state: SubscriptionState,
    val item: SubscriptionItem
)

data class SubscriptionEditResult(
    val ok: Boolean,
    val message: String,
    val state: SubscriptionState,
    val item: SubscriptionItem? = null,
    val urlChanged: Boolean = false,
    val nameChanged: Boolean = false
)

object SubscriptionRepository {
    private const val FILE_NAME = "subscriptions.json"
    private const val LOCAL_URL = "本地节点列表"

    fun buildConfigFromContent(content: String): TemplateBuildResult {
        val decoded = decodeSubscriptionBody(content)
        return TemplateConfigBuilder.buildFromSubscription(decoded)
    }

    fun load(context: Context): SubscriptionState {
        val file = File(context.filesDir, FILE_NAME)
        if (!file.exists()) {
            return SubscriptionState.empty()
        }
        val parsed = runCatching { parseState(file.readText()) }.getOrNull()
            ?: return SubscriptionState.empty()
        if (parsed.shouldSave) {
            // 兼容旧格式或缺失字段时，回写规范化结果以避免再次丢失显示。
            runCatching { save(context, parsed.state) }
        }
        return parsed.state
    }

    fun add(context: Context, name: String, url: String): SubscriptionAddResult {
        val state = load(context)
        val cleanName = name.trim()
        val cleanUrl = url.trim()
        val displayName = cleanName.ifBlank { deriveName(cleanUrl, state.items.size + 1) }
        val newItem = SubscriptionItem(
            id = UUID.randomUUID().toString(),
            name = displayName,
            url = cleanUrl
        )
        val newState = SubscriptionState(state.items + newItem, state.selectedId)
        save(context, newState)
        return SubscriptionAddResult(newState, newItem)
    }

    fun importLocal(context: Context, name: String, content: String): SubscriptionUpdateResult {
        val parsed = buildConfigFromContent(content)
        if (!parsed.ok || parsed.configJson == null) {
            return SubscriptionUpdateResult(
                ok = false,
                message = parsed.error ?: "节点列表解析失败",
                warnings = emptyList(),
                state = load(context)
            )
        }
        saveConfigWithSettings(context, parsed.configJson)
        val state = load(context)
        val cleanName = name.trim()
        val displayName = cleanName.ifBlank { deriveLocalName(state.items.count { it.isLocal } + 1) }
        val newItem = SubscriptionItem(
            id = UUID.randomUUID().toString(),
            name = displayName,
            url = LOCAL_URL,
            lastUpdatedAt = System.currentTimeMillis(),
            lastError = null,
            isLocal = true,
            content = content.trim()
        )
        val newState = SubscriptionState(state.items + newItem, newItem.id)
        save(context, newState)
        val message = if (parsed.warnings.isNotEmpty()) {
            "本地节点已应用，但有 ${parsed.warnings.size} 条警告"
        } else {
            "本地节点已应用"
        }
        return SubscriptionUpdateResult(true, message, parsed.warnings, newState)
    }

    fun remove(context: Context, id: String): SubscriptionState {
        val state = load(context)
        val remaining = state.items.filterNot { it.id == id }
        val nextSelected = state.selectedId?.takeIf { it != id }
        val newState = SubscriptionState(remaining, nextSelected)
        save(context, newState)
        return newState
    }

    fun select(context: Context, id: String): SubscriptionState {
        val state = load(context)
        val newState = SubscriptionState(state.items, id)
        save(context, newState)
        return newState
    }

    fun edit(context: Context, id: String, name: String, url: String): SubscriptionEditResult {
        val state = load(context)
        val item = state.items.firstOrNull { it.id == id }
            ?: return SubscriptionEditResult(
                ok = false,
                message = "订阅不存在",
                state = state
            )
        if (item.isLocal) {
            val cleanName = name.trim()
            val displayName = if (cleanName.isBlank()) item.name else cleanName
            val nameChanged = displayName != item.name
            val updatedItem = item.copy(name = displayName)
            val items = state.items.map { if (it.id == id) updatedItem else it }
            val newState = SubscriptionState(items, state.selectedId)
            save(context, newState)
            return SubscriptionEditResult(
                ok = true,
                message = "本地订阅已更新",
                state = newState,
                item = updatedItem,
                urlChanged = false,
                nameChanged = nameChanged
            )
        }
        val cleanUrl = url.trim()
        if (cleanUrl.isBlank()) {
            return SubscriptionEditResult(
                ok = false,
                message = "订阅地址不能为空",
                state = state
            )
        }
        val cleanName = name.trim()
        val displayName = if (cleanName.isBlank()) item.name else cleanName
        val urlChanged = cleanUrl != item.url
        val nameChanged = displayName != item.name
        val updatedItem = item.copy(
            name = displayName,
            url = cleanUrl,
            lastError = if (urlChanged) null else item.lastError,
            lastUpdatedAt = if (urlChanged) null else item.lastUpdatedAt
        )
        val items = state.items.map { if (it.id == id) updatedItem else it }
        val newState = SubscriptionState(items, state.selectedId)
        save(context, newState)
        return SubscriptionEditResult(
            ok = true,
            message = "订阅信息已保存",
            state = newState,
            item = updatedItem,
            urlChanged = urlChanged,
            nameChanged = nameChanged
        )
    }

    fun activate(context: Context, id: String): SubscriptionUpdateResult {
        val state = load(context)
        val item = state.items.firstOrNull { it.id == id }
            ?: return SubscriptionUpdateResult(
                ok = false,
                message = "订阅不存在",
                warnings = emptyList(),
                state = state
            )
        val result = updateItem(context, item)
        if (!result.ok) {
            return result
        }
        // 仅在订阅成功同步后才标记为当前订阅，避免配置不一致。
        val activatedState = SubscriptionState(result.state.items, id)
        save(context, activatedState)
        val message = "已启用，${result.message}"
        return result.copy(message = message, state = activatedState)
    }

    fun updateSelected(context: Context): SubscriptionUpdateResult {
        val state = load(context)
        val selected = state.selected()
            ?: return SubscriptionUpdateResult(
                ok = false,
                message = "请先启用订阅",
                warnings = emptyList(),
                state = state
            )
        return updateItem(context, selected)
    }

    private fun updateItem(context: Context, item: SubscriptionItem): SubscriptionUpdateResult {
        return try {
            if (item.isLocal) {
                val content = item.content?.takeIf { it.isNotBlank() }
                    ?: throw IOException("本地节点内容为空")
                val result = buildConfigFromContent(content)
                if (!result.ok || result.configJson == null) {
                    throw IOException(result.error ?: "本地节点解析失败")
                }
                saveConfigWithSettings(context, result.configJson)
                val message = if (result.warnings.isNotEmpty()) {
                    "本地节点已应用，但有 ${result.warnings.size} 条警告"
                } else {
                    "本地节点已应用"
                }
                return buildUpdateResult(context, item, message, result.warnings)
            }
            val content = fetchSubscription(item.url)
            val result = TemplateConfigBuilder.buildFromSubscription(content)
            if (!result.ok || result.configJson == null) {
                throw IOException(result.error ?: "订阅解析失败")
            }
            saveConfigWithSettings(context, result.configJson)
            val message = if (result.warnings.isNotEmpty()) {
                "订阅同步完成，但有 ${result.warnings.size} 条警告"
            } else {
                "订阅同步成功"
            }
            buildUpdateResult(context, item, message, result.warnings)
        } catch (e: Exception) {
            val updatedItem = item.copy(lastError = e.message ?: "订阅同步失败")
            val state = replaceItem(context, updatedItem)
            SubscriptionUpdateResult(false, updatedItem.lastError ?: "订阅同步失败", emptyList(), state)
        }
    }

    private fun buildUpdateResult(
        context: Context,
        item: SubscriptionItem,
        message: String,
        warnings: List<String>
    ): SubscriptionUpdateResult {
        val updatedItem = item.copy(
            lastUpdatedAt = System.currentTimeMillis(),
            lastError = null
        )
        val state = replaceItem(context, updatedItem)
        return SubscriptionUpdateResult(true, message, warnings, state)
    }

    private fun replaceItem(context: Context, item: SubscriptionItem): SubscriptionState {
        val state = load(context)
        val items = state.items.map { if (it.id == item.id) item else it }
        val newState = SubscriptionState(items, state.selectedId)
        save(context, newState)
        return newState
    }

    private fun save(context: Context, state: SubscriptionState) {
        val file = File(context.filesDir, FILE_NAME)
        val root = JSONObject()
        root.put("selectedId", state.selectedId ?: "")
        val items = JSONArray()
        state.items.forEach { item ->
            val obj = JSONObject()
            obj.put("id", item.id)
            obj.put("name", item.name)
            obj.put("url", item.url)
            obj.put("lastUpdatedAt", item.lastUpdatedAt ?: 0)
            obj.put("lastError", item.lastError ?: "")
            obj.put("isLocal", item.isLocal)
            obj.put("content", item.content ?: "")
            items.put(obj)
        }
        root.put("items", items)
        file.writeText(root.toString(2))
    }

    private data class ParsedSubscriptionState(
        val state: SubscriptionState,
        val shouldSave: Boolean
    )

    private data class ParsedItems(
        val items: List<SubscriptionItem>,
        val changed: Boolean
    )

    private fun parseState(raw: String): ParsedSubscriptionState? {
        val trimmed = raw.trim()
        if (trimmed.isBlank()) {
            return null
        }
        return if (trimmed.startsWith("[")) {
            val items = parseItems(JSONArray(trimmed))
            ParsedSubscriptionState(SubscriptionState(items.items, null), true)
        } else {
            val root = JSONObject(trimmed)
            val selectedId = root.optString("selectedId").takeIf { it.isNotBlank() }
            val items = parseItems(root.optJSONArray("items") ?: JSONArray())
            val validSelected = selectedId?.takeIf { id -> items.items.any { it.id == id } }
            val shouldSave = items.changed || validSelected != selectedId
            ParsedSubscriptionState(SubscriptionState(items.items, validSelected), shouldSave)
        }
    }

    private fun parseItems(itemsJson: JSONArray): ParsedItems {
        var changed = false
        val items = mutableListOf<SubscriptionItem>()
        for (index in 0 until itemsJson.length()) {
            val obj = itemsJson.optJSONObject(index) ?: continue
            val isLocal = obj.optBoolean("isLocal", false)
            val url = obj.optString("url").ifBlank { if (isLocal) LOCAL_URL else "" }
            val id = obj.optString("id").ifBlank {
                changed = true
                UUID.randomUUID().toString()
            }
            items.add(
                SubscriptionItem(
                    id = id,
                    name = obj.optString("name"),
                    url = url,
                    lastUpdatedAt = obj.optLong("lastUpdatedAt").takeIf { it > 0 },
                    lastError = obj.optString("lastError").takeIf { it.isNotBlank() },
                    isLocal = isLocal,
                    content = obj.optString("content").takeIf { it.isNotBlank() }
                )
            )
        }
        return ParsedItems(items, changed)
    }

    private fun saveConfigWithSettings(context: Context, json: String) {
        val settings = SettingsRepository.load(context)
        ConfigRepository.saveConfigWithSettings(context, json, settings)
    }

    private fun deriveName(url: String, index: Int): String {
        val host = runCatching { Uri.parse(url).host }.getOrNull()
        return host ?: "订阅$index"
    }

    private fun deriveLocalName(index: Int): String {
        return "本地节点$index"
    }

    private fun fetchSubscription(url: String): String {
        val connection = URL(url).openConnection() as HttpURLConnection
        connection.instanceFollowRedirects = true
        connection.connectTimeout = 10_000
        connection.readTimeout = 15_000
        connection.requestMethod = "GET"
        connection.setRequestProperty("User-Agent", "SingBoxMobile/1.0")
        val code = connection.responseCode
        val stream = if (code in 200..299) {
            connection.inputStream
        } else {
            connection.errorStream
        }
        val body = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
        if (code !in 200..299) {
            throw IOException("订阅请求失败: HTTP $code")
        }
        if (body.isBlank()) {
            throw IOException("订阅内容为空")
        }
        return decodeSubscriptionBody(body)
    }

    private fun decodeIfBase64(raw: String): String {
        val trimmed = raw.trim()
        if (isLikelyYaml(trimmed) || isLikelyJson(trimmed)) {
            return raw
        }
        if (!looksLikeBase64(trimmed)) {
            return raw
        }
        val decodedBytes = decodeBase64(trimmed) ?: return raw
        val decodedText = decodedBytes.toString(Charsets.UTF_8)
        if (isLikelyYaml(decodedText) || isLikelyJson(decodedText) || isLikelyProxyUriList(decodedText)) {
            return decodedText
        }
        val decompressed = tryDecompress(decodedBytes)
        if (
            !decompressed.isNullOrBlank()
            && (isLikelyYaml(decompressed) || isLikelyJson(decompressed) || isLikelyProxyUriList(decompressed))
        ) {
            return decompressed
        }
        return raw
    }

    private fun decodeSubscriptionBody(raw: String): String {
        val decoded = decodeIfBase64(raw)
        return convertProxyUriListToJson(decoded) ?: decoded
    }

    private fun looksLikeBase64(text: String): Boolean {
        if (text.length < 16) {
            return false
        }
        val normalized = text.replace("\n", "").replace("\r", "")
        val remainder = normalized.length % 4
        if (remainder == 1) {
            return false
        }
        val base64Regex = Regex("^[A-Za-z0-9+/=_-]+$")
        return base64Regex.matches(normalized)
    }

    private fun decodeBase64(text: String): ByteArray? {
        val normalized = text.replace("\n", "").replace("\r", "")
        val padded = when (normalized.length % 4) {
            0 -> normalized
            2 -> "$normalized=="
            3 -> "$normalized="
            else -> normalized
        }
        return runCatching {
            Base64.decode(padded, Base64.NO_WRAP)
        }.getOrNull() ?: runCatching {
            Base64.decode(padded, Base64.NO_WRAP or Base64.URL_SAFE)
        }.getOrNull()
    }

    private fun tryDecompress(bytes: ByteArray): String? {
        return runCatching {
            GZIPInputStream(ByteArrayInputStream(bytes)).bufferedReader(Charsets.UTF_8).use {
                it.readText()
            }
        }.getOrNull()
    }

    private fun isLikelyYaml(text: String): Boolean {
        val sample = text.trimStart()
        if (sample.startsWith("---")) {
            return true
        }
        val keys = listOf(
            "proxies:",
            "proxy-providers:",
            "proxy-groups:",
            "rules:",
            "dns:",
            "mixed-port:",
            "port:",
            "mode:"
        )
        return keys.any { sample.startsWith(it) || text.contains("\n$it") }
    }

    private fun isLikelyJson(text: String): Boolean {
        val sample = text.trimStart()
        if (!(sample.startsWith("{") || sample.startsWith("["))) {
            return false
        }
        return sample.contains("\"outbounds\"") || sample.contains("\"inbounds\"")
    }

    private fun isLikelyProxyUriList(text: String): Boolean {
        val schemes = listOf("trojan://", "vmess://", "vless://", "ss://", "ssr://", "hysteria2://", "tuic://")
        return text.lineSequence()
            .map { it.trim() }
            .filter { it.isNotBlank() }
            .any { line -> schemes.any { scheme -> line.startsWith(scheme, ignoreCase = true) } }
    }

    private fun convertProxyUriListToJson(content: String): String? {
        val lines = content.lineSequence()
            .map { it.trim() }
            .filter { it.isNotBlank() }
            .toList()
        if (lines.isEmpty()) {
            return null
        }
        val proxies = mutableListOf<JSONObject>()
        lines.forEachIndexed { index, line ->
            parseProxyUri(line, "节点 ${index + 1}")?.let { proxies.add(it) }
        }
        if (proxies.isEmpty()) {
            return null
        }
        val array = JSONArray()
        proxies.forEach { array.put(it) }
        // 将订阅中的 URI 列表包装为 sing-box outbounds，便于后续解析
        return JSONObject().put("outbounds", array).toString()
    }

    private fun parseProxyUri(line: String, fallbackName: String): JSONObject? {
        return when {
            line.startsWith("trojan://", ignoreCase = true) -> parseTrojanUri(line, fallbackName)
            line.startsWith("vmess://", ignoreCase = true) -> parseVmessUri(line, fallbackName)
            line.startsWith("vless://", ignoreCase = true) -> parseVlessUri(line, fallbackName)
            line.startsWith("ss://", ignoreCase = true) -> parseShadowsocksUri(line, fallbackName)
            line.startsWith("ssr://", ignoreCase = true) -> parseShadowsocksrUri(line, fallbackName)
            line.startsWith("hysteria2://", ignoreCase = true) -> parseHysteria2Uri(line, fallbackName)
            line.startsWith("tuic://", ignoreCase = true) -> parseTuicUri(line, fallbackName)
            else -> null
        }
    }

    private fun parseTrojanUri(line: String, fallbackName: String): JSONObject? {
        val withoutScheme = line.substringAfter("://")
        val fragment = line.substringAfter("#", "")
        val name = decodeUriComponent(fragment).takeIf { it.isNotBlank() } ?: fallbackName
        val withoutFragment = withoutScheme.substringBefore("#")
        val hostPart = withoutFragment.substringBefore("?")
        val queryPart = withoutFragment.substringAfter("?", "")
        val atIndex = hostPart.indexOf('@')
        if (atIndex <= 0) {
            return null
        }
        val password = hostPart.substring(0, atIndex)
        val hostPort = hostPart.substring(atIndex + 1)
        val (host, port) = splitHostPort(hostPort, 443)
        val queryMap = parseQueryParameters(queryPart)
        val tls = JSONObject().put("enabled", true)
        queryMap["sni"]?.takeIf { it.isNotBlank() }?.let { tls.put("server_name", it) }
        parseBoolean(queryMap["allowInsecure"])?.let { tls.put("insecure", it) }
        queryMap["alpn"]?.takeIf { it.isNotBlank() }?.let { value ->
            val list = value.split(",").mapNotNull { it.trim().takeIf { t -> t.isNotBlank() } }
            if (list.isNotEmpty()) {
                val alpnArray = JSONArray()
                list.forEach { alpnArray.put(it) }
                tls.put("alpn", alpnArray)
            }
        }
        val node = JSONObject()
            .put("type", "trojan")
            .put("tag", name)
            .put("server", host)
            .put("server_port", port)
            .put("password", password)
            .put("tls", tls)
        return node
    }

    private fun parseVmessUri(line: String, fallbackName: String): JSONObject? {
        val payload = line.substringAfter("://")
        val fragment = payload.substringAfter("#", "")
        val encoded = payload.substringBefore("#")
        val decodedBytes = decodeBase64(encoded) ?: return null
        val jsonText = decodedBytes.toString(Charsets.UTF_8)
        val root = JSONObject(jsonText)
        val name = decodeUriComponent(fragment).takeIf { it.isNotBlank() }
            ?: root.optString("ps").takeIf { it.isNotBlank() }
            ?: fallbackName
        val node = JSONObject()
            .put("type", "vmess")
            .put("tag", name)
            .put("server", root.optString("add"))
            .put("server_port", root.optInt("port", 0))
            .put("uuid", root.optString("id"))
        root.optString("cipher")?.takeIf { it.isNotBlank() }?.let { node.put("security", it) }
        root.optInt("aid", 0).takeIf { it > 0 }?.let { node.put("alter_id", it) }
        if (root.optString("tls").equals("tls", true)) {
            val tls = JSONObject().put("enabled", true)
            root.optString("sni")?.takeIf { it.isNotBlank() }?.let { tls.put("server_name", it) }
            node.put("tls", tls)
        }
        when (root.optString("net")) {
            "ws" -> {
                val transport = JSONObject().put("type", "ws")
                transport.put("path", root.optString("path", "/"))
                root.optString("host")?.takeIf { it.isNotBlank() }?.let { host ->
                    transport.put("headers", JSONObject().put("Host", host))
                }
                node.put("transport", transport)
            }
            "grpc" -> {
                val transport = JSONObject().put("type", "grpc")
                    .put("service_name", root.optString("path", ""))
                node.put("transport", transport)
            }
        }
        return node
    }

    private fun parseVlessUri(line: String, fallbackName: String): JSONObject? {
        val withoutScheme = line.substringAfter("://")
        val fragment = withoutScheme.substringAfter("#", "")
        val name = decodeUriComponent(fragment).takeIf { it.isNotBlank() } ?: fallbackName
        val withoutFragment = withoutScheme.substringBefore("#")
        val hostPart = withoutFragment.substringBefore("?")
        val queryPart = withoutFragment.substringAfter("?", "")
        val atIndex = hostPart.indexOf('@')
        if (atIndex <= 0) {
            return null
        }
        val uuid = hostPart.substring(0, atIndex)
        val hostPort = hostPart.substring(atIndex + 1)
        val (host, port) = splitHostPort(hostPort, 443)
        val queryMap = parseQueryParameters(queryPart)
        val node = JSONObject()
            .put("type", "vless")
            .put("tag", name)
            .put("server", host)
            .put("server_port", port)
            .put("uuid", uuid)
        queryMap["flow"]?.takeIf { it.isNotBlank() }?.let { node.put("flow", it) }
        val security = queryMap["security"]?.lowercase()
        if (!security.isNullOrBlank() && security != "none") {
            val tls = JSONObject().put("enabled", true)
            queryMap["sni"]?.takeIf { it.isNotBlank() }?.let { tls.put("server_name", it) }
            parseBoolean(queryMap["allowInsecure"] ?: queryMap["allow_insecure"])
                ?.let { tls.put("insecure", it) }
            node.put("tls", tls)
        }
        val network = (queryMap["type"] ?: queryMap["network"])?.lowercase()
        when (network) {
            "ws" -> {
                val transport = JSONObject().put("type", "ws")
                queryMap["path"]?.takeIf { it.isNotBlank() }?.let { transport.put("path", it) }
                val hostHeader = queryMap["host"]?.takeIf { it.isNotBlank() }
                if (!hostHeader.isNullOrBlank()) {
                    transport.put("headers", JSONObject().put("Host", hostHeader))
                }
                node.put("transport", transport)
            }
            "grpc" -> {
                val transport = JSONObject().put("type", "grpc")
                queryMap["serviceName"]?.takeIf { it.isNotBlank() }?.let { transport.put("service_name", it) }
                queryMap["service_name"]?.takeIf { it.isNotBlank() }?.let { transport.put("service_name", it) }
                node.put("transport", transport)
            }
        }
        return node
    }

    private fun parseShadowsocksUri(line: String, fallbackName: String): JSONObject? {
        val withoutScheme = line.substringAfter("://")
        val fragment = withoutScheme.substringAfter("#", "")
        val name = decodeUriComponent(fragment).takeIf { it.isNotBlank() } ?: fallbackName
        val withoutFragment = withoutScheme.substringBefore("#")
        val mainPart = withoutFragment.substringBefore("?")
        val queryPart = withoutFragment.substringAfter("?", "")
        val userInfoHost = if (mainPart.contains("@")) {
            mainPart
        } else {
            val decoded = decodeBase64(mainPart) ?: return null
            decoded.toString(Charsets.UTF_8)
        }
        val atIndex = userInfoHost.lastIndexOf('@')
        if (atIndex <= 0) {
            return null
        }
        var userInfo = userInfoHost.substring(0, atIndex)
        if (!userInfo.contains(":") && looksLikeBase64(userInfo)) {
            userInfo = decodeBase64(userInfo)?.toString(Charsets.UTF_8) ?: return null
        }
        val hostPort = userInfoHost.substring(atIndex + 1)
        val methodPassword = userInfo.split(":", limit = 2)
        if (methodPassword.size < 2) {
            return null
        }
        val method = decodeUriComponent(methodPassword[0])
        val password = decodeUriComponent(methodPassword[1])
        val (host, port) = splitHostPort(hostPort, 8388)
        val node = JSONObject()
            .put("type", "shadowsocks")
            .put("tag", name)
            .put("server", host)
            .put("server_port", port)
            .put("method", method)
            .put("password", password)
        val queryMap = parseQueryParameters(queryPart)
        queryMap["plugin"]?.takeIf { it.isNotBlank() }?.let { pluginSpec ->
            val pluginDecoded = decodeUriComponent(pluginSpec)
            val parts = pluginDecoded.split(";", limit = 2)
            node.put("plugin", parts[0])
            if (parts.size > 1 && parts[1].isNotBlank()) {
                node.put("plugin_opts", parts[1])
            }
        }
        return node
    }

    private fun parseShadowsocksrUri(line: String, fallbackName: String): JSONObject? {
        // SSR 链接内容为 base64(server:port:protocol:method:obfs:password/?params)
        val encoded = line.substringAfter("://").substringBefore("#")
        val decodedBytes = decodeBase64(encoded) ?: return null
        val decodedText = decodedBytes.toString(Charsets.UTF_8)
        val mainPart = decodedText.substringBefore("/?")
        val paramsPart = decodedText.substringAfter("/?", "")
        val parts = mainPart.split(":")
        if (parts.size < 6) {
            return null
        }
        val server = parts[0]
        val port = parts[1].toIntOrNull() ?: return null
        val protocol = parts[2]
        val method = parts[3]
        val obfs = parts[4]
        val passwordEncoded = parts[5]
        val password = decodeSsrParam(passwordEncoded)?.takeIf { it.isNotBlank() } ?: return null
        val params = parseQueryParameters(paramsPart)
        val name = decodeSsrParam(params["remarks"])?.takeIf { it.isNotBlank() } ?: fallbackName
        val outbound = JSONObject()
            .put("type", "shadowsocksr")
            .put("tag", name)
            .put("server", server)
            .put("server_port", port)
            .put("method", method)
            .put("password", password)
            .put("protocol", protocol)
            .put("obfs", obfs)
        decodeSsrParam(params["protoparam"])?.takeIf { it.isNotBlank() }
            ?.let { outbound.put("protocol_param", it) }
        decodeSsrParam(params["obfsparam"])?.takeIf { it.isNotBlank() }
            ?.let { outbound.put("obfs_param", it) }
        return outbound
    }

    private fun parseHysteria2Uri(line: String, fallbackName: String): JSONObject? {
        val withoutScheme = line.substringAfter("://")
        val fragment = withoutScheme.substringAfter("#", "")
        val name = decodeUriComponent(fragment).takeIf { it.isNotBlank() } ?: fallbackName
        val withoutFragment = withoutScheme.substringBefore("#")
        val hostPart = withoutFragment.substringBefore("?")
        val queryPart = withoutFragment.substringAfter("?", "")
        val atIndex = hostPart.indexOf('@')
        val auth = if (atIndex > 0) decodeUriComponent(hostPart.substring(0, atIndex)) else ""
        val hostPort = if (atIndex > 0) hostPart.substring(atIndex + 1) else hostPart
        val (host, port) = splitHostPort(hostPort, 443)
        val queryMap = parseQueryParameters(queryPart)
        val password = queryMap["password"]?.takeIf { it.isNotBlank() }
            ?: queryMap["auth"]?.takeIf { it.isNotBlank() }
            ?: auth.takeIf { it.isNotBlank() }
        if (password.isNullOrBlank()) {
            return null
        }
        val node = JSONObject()
            .put("type", "hysteria2")
            .put("tag", name)
            .put("server", host)
            .put("server_port", port)
            .put("password", password)
            .put("tls", buildTlsFromQuery(queryMap))
        parseNumber(queryMap["upmbps"] ?: queryMap["up_mbps"] ?: queryMap["up"])
            ?.let { node.put("up_mbps", it) }
        parseNumber(queryMap["downmbps"] ?: queryMap["down_mbps"] ?: queryMap["down"])
            ?.let { node.put("down_mbps", it) }
        queryMap["network"]?.lowercase()?.takeIf { it == "tcp" || it == "udp" }
            ?.let { node.put("network", it) }
        val obfsType = queryMap["obfs"]?.takeIf { it.isNotBlank() }
        if (!obfsType.isNullOrBlank()) {
            val obfs = JSONObject().put("type", obfsType)
            val obfsPassword = queryMap["obfs-password"] ?: queryMap["obfs_password"]
            obfsPassword?.takeIf { it.isNotBlank() }?.let { obfs.put("password", it) }
            node.put("obfs", obfs)
        }
        return node
    }

    private fun parseTuicUri(line: String, fallbackName: String): JSONObject? {
        val withoutScheme = line.substringAfter("://")
        val fragment = withoutScheme.substringAfter("#", "")
        val name = decodeUriComponent(fragment).takeIf { it.isNotBlank() } ?: fallbackName
        val withoutFragment = withoutScheme.substringBefore("#")
        val hostPart = withoutFragment.substringBefore("?")
        val queryPart = withoutFragment.substringAfter("?", "")
        val atIndex = hostPart.indexOf('@')
        if (atIndex <= 0) {
            return null
        }
        val userInfo = decodeUriComponent(hostPart.substring(0, atIndex))
        val hostPort = hostPart.substring(atIndex + 1)
        val (host, port) = splitHostPort(hostPort, 443)
        val uuid = userInfo.substringBefore(":", userInfo).takeIf { it.isNotBlank() } ?: return null
        val passwordFromUser = userInfo.substringAfter(":", "").takeIf { it.isNotBlank() }
        val queryMap = parseQueryParameters(queryPart)
        val node = JSONObject()
            .put("type", "tuic")
            .put("tag", name)
            .put("server", host)
            .put("server_port", port)
            .put("uuid", uuid)
            .put("tls", buildTlsFromQuery(queryMap))
        val password = passwordFromUser ?: queryMap["password"]
        password?.takeIf { it.isNotBlank() }?.let { node.put("password", it) }
        queryMap["congestion_control"]?.takeIf { it.isNotBlank() }
            ?.let { node.put("congestion_control", it) }
        queryMap["udp_relay_mode"]?.takeIf { it.isNotBlank() }
            ?.let { node.put("udp_relay_mode", it) }
        parseBoolean(queryMap["udp_over_stream"])?.let { node.put("udp_over_stream", it) }
        parseBoolean(queryMap["zero_rtt_handshake"])?.let { node.put("zero_rtt_handshake", it) }
        queryMap["heartbeat"]?.takeIf { it.isNotBlank() }?.let { node.put("heartbeat", it) }
        queryMap["network"]?.lowercase()?.takeIf { it == "tcp" || it == "udp" }
            ?.let { node.put("network", it) }
        return node
    }

    private fun splitHostPort(value: String, defaultPort: Int): Pair<String, Int> {
        if (value.startsWith("[")) {
            val end = value.indexOf(']')
            if (end > 0) {
                val host = value.substring(1, end)
                val portStr = value.substringAfter("]:", "")
                val port = portStr.toIntOrNull() ?: defaultPort
                return host to port
            }
            return value to defaultPort
        }
        val colonIndex = value.lastIndexOf(':')
        if (colonIndex > 0 && colonIndex < value.length - 1) {
            val host = value.substring(0, colonIndex)
            val port = value.substring(colonIndex + 1).toIntOrNull() ?: defaultPort
            return host to port
        }
        return value to defaultPort
    }

    private fun parseQueryParameters(query: String?): Map<String, String> {
        if (query.isNullOrBlank()) {
            return emptyMap()
        }
        return query.split("&").mapNotNull { part ->
            val idx = part.indexOf('=')
            if (idx >= 0) {
                val key = decodeUriComponent(part.substring(0, idx))
                val value = decodeUriComponent(part.substring(idx + 1))
                key to value
            } else {
                val key = decodeUriComponent(part)
                key to ""
            }
        }.toMap()
    }

    private fun decodeUriComponent(value: String): String {
        return runCatching { URLDecoder.decode(value, "UTF-8") }.getOrNull() ?: value
    }

    private fun decodeSsrParam(value: String?): String? {
        if (value.isNullOrBlank()) return null
        val decoded = decodeBase64(value)?.toString(Charsets.UTF_8)
        return decoded ?: decodeUriComponent(value)
    }

    private fun buildTlsFromQuery(queryMap: Map<String, String>): JSONObject {
        val tls = JSONObject().put("enabled", true)
        val serverName = queryMap["sni"]
            ?: queryMap["peer"]
            ?: queryMap["server_name"]
            ?: queryMap["servername"]
        serverName?.takeIf { it.isNotBlank() }?.let { tls.put("server_name", it) }
        parseBoolean(
            queryMap["allowInsecure"]
                ?: queryMap["allow_insecure"]
                ?: queryMap["insecure"]
                ?: queryMap["skip-cert-verify"]
        )?.let { tls.put("insecure", it) }
        queryMap["alpn"]?.takeIf { it.isNotBlank() }?.let { value ->
            val list = value.split(",").mapNotNull { it.trim().takeIf { t -> t.isNotBlank() } }
            if (list.isNotEmpty()) {
                val alpnArray = JSONArray()
                list.forEach { alpnArray.put(it) }
                tls.put("alpn", alpnArray)
            }
        }
        return tls
    }

    private fun parseBoolean(value: String?): Boolean? {
        if (value.isNullOrBlank()) return null
        return when (value.lowercase()) {
            "1", "true", "yes", "on" -> true
            "0", "false", "no", "off" -> false
            else -> null
        }
    }

    private fun parseNumber(value: String?): Number? {
        if (value.isNullOrBlank()) return null
        return value.toIntOrNull() ?: value.toDoubleOrNull()
    }
}
