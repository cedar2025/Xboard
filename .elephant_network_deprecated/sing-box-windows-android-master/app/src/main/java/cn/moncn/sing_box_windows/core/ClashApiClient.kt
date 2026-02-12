package cn.moncn.sing_box_windows.core

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

object ClashApiClient {
    private const val DEFAULT_TIMEOUT_MS = 4000
    @Volatile
    private var baseUrl: String? = null
    @Volatile
    private var secret: String? = null

    fun configure(address: String?, secret: String? = null) {
        val normalized = address?.trim().orEmpty()
        baseUrl = if (normalized.isBlank()) {
            null
        } else if (normalized.startsWith("http://") || normalized.startsWith("https://")) {
            normalized.removeSuffix("/")
        } else {
            "http://$normalized".removeSuffix("/")
        }
        this.secret = secret?.trim()?.ifBlank { null }
    }

    fun configureFromConfig(configJson: String) {
        runCatching {
            val root = JSONObject(configJson)
            val clashApi = root.optJSONObject("experimental")?.optJSONObject("clash_api")
            val address = clashApi?.optString("external_controller")?.ifBlank { null }
                ?: clashApi?.optString("external-controller")?.ifBlank { null }
            val secret = clashApi?.optString("secret")?.ifBlank { null }
            if (address == null && secret == null) {
                return
            }
            configure(address, secret)
        }.onFailure {
            if (!isConfigured()) {
                reset()
            }
        }
    }

    fun reset() {
        baseUrl = null
        secret = null
    }

    fun isConfigured(): Boolean = !baseUrl.isNullOrBlank()

    fun buildWebSocketUrl(path: String): String {
        val base = baseUrl ?: error("Clash API is not configured")
        val normalizedPath = if (path.startsWith("/")) path else "/$path"
        val wsBase = when {
            base.startsWith("https://") -> "wss://${base.removePrefix("https://")}"
            base.startsWith("http://") -> "ws://${base.removePrefix("http://")}"
            else -> "ws://$base"
        }
        return wsBase + normalizedPath
    }

    fun authorizationHeader(): String? = secret?.let { "Bearer $it" }

    fun baseUrl(): String? = baseUrl

    fun hasSecret(): Boolean = !secret.isNullOrBlank()

    suspend fun getJsonObject(
        path: String,
        query: Map<String, String> = emptyMap()
    ): JSONObject {
        val response = request("GET", path, query, null)
        return JSONObject(response)
    }

    suspend fun getJsonArray(
        path: String,
        query: Map<String, String> = emptyMap()
    ): JSONArray {
        val response = request("GET", path, query, null)
        return JSONArray(response)
    }

    suspend fun getJsonValue(
        path: String,
        query: Map<String, String> = emptyMap()
    ): Any {
        val response = request("GET", path, query, null)
        return parseJsonValue(response)
    }

    suspend fun getRaw(
        path: String,
        query: Map<String, String> = emptyMap()
    ): RawResponse = withContext(Dispatchers.IO) {
        val url = buildUrl(path, query)
        val connection = (URL(url).openConnection() as HttpURLConnection)
        try {
            connection.requestMethod = "GET"
            connection.connectTimeout = DEFAULT_TIMEOUT_MS
            connection.readTimeout = DEFAULT_TIMEOUT_MS
            connection.setRequestProperty("Accept", "application/json")
            secret?.let { connection.setRequestProperty("Authorization", "Bearer $it") }
            val code = connection.responseCode
            val stream = if (code in 200..299) {
                connection.inputStream
            } else {
                connection.errorStream
            }
            val responseText = stream?.bufferedReader()?.use { it.readText() } ?: ""
            RawResponse(code, responseText)
        } finally {
            connection.disconnect()
        }
    }

    suspend fun putJson(path: String, body: JSONObject): JSONObject? {
        val response = request("PUT", path, emptyMap(), body.toString())
        return response.takeIf { it.isNotBlank() }?.let { JSONObject(it) }
    }

    suspend fun delete(path: String): JSONObject? {
        val response = request("DELETE", path, emptyMap(), null)
        return response.takeIf { it.isNotBlank() }?.let { JSONObject(it) }
    }

    private suspend fun request(
        method: String,
        path: String,
        query: Map<String, String>,
        body: String?
    ): String = withContext(Dispatchers.IO) {
        val url = buildUrl(path, query)
        android.util.Log.d("ClashApi", "$method $url")
        val connection = (URL(url).openConnection() as HttpURLConnection)
        try {
            connection.requestMethod = method
            connection.connectTimeout = DEFAULT_TIMEOUT_MS
            connection.readTimeout = DEFAULT_TIMEOUT_MS
            connection.setRequestProperty("Accept", "application/json")
            secret?.let {
                connection.setRequestProperty("Authorization", "Bearer $it")
                android.util.Log.d("ClashApi", "Authorization: Bearer ***")
            }
            if (body != null) {
                connection.doOutput = true
                connection.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                connection.outputStream.use { stream ->
                    stream.write(body.toByteArray(Charsets.UTF_8))
                }
            }
            val code = connection.responseCode
            android.util.Log.d("ClashApi", "Response code: $code")
            val stream = if (code in 200..299) {
                connection.inputStream
            } else {
                connection.errorStream
            }
            val responseText = stream?.bufferedReader()?.use { it.readText() } ?: ""
            if (code !in 200..299) {
                val snippet = responseText.take(200)
                android.util.Log.e("ClashApi", "Error response: $snippet")
                throw IllegalStateException("Clash API $method $path failed: HTTP $code $snippet")
            }
            android.util.Log.d("ClashApi", "Response: ${responseText.take(100)}")
            responseText
        } finally {
            connection.disconnect()
        }
    }

    private fun buildUrl(path: String, query: Map<String, String>): String {
        val base = baseUrl ?: error("Clash API is not configured")
        val normalizedPath = if (path.startsWith("/")) path else "/$path"
        val builder = StringBuilder(base).append(normalizedPath)
        if (query.isNotEmpty()) {
            builder.append("?")
            val queryString = query.entries.joinToString("&") { (key, value) ->
                "${encodeQuery(key)}=${encodeQuery(value)}"
            }
            builder.append(queryString)
        }
        return builder.toString()
    }

    private fun encodeQuery(value: String): String =
        URLEncoder.encode(value, "UTF-8")

    private fun parseJsonValue(response: String): Any {
        val trimmed = response.trim()
        if (trimmed.isBlank()) {
            return JSONObject()
        }
        return if (trimmed.startsWith("[")) JSONArray(trimmed) else JSONObject(trimmed)
    }

    data class RawResponse(
        val code: Int,
        val body: String
    )
}
