package cn.moncn.sing_box_windows.config

import org.json.JSONArray
import org.json.JSONObject
import org.yaml.snakeyaml.Yaml

object ClashConverter {
    fun convert(clashYaml: String): ConversionResult {
        val warnings = mutableListOf<String>()
        val root = readRootMap(clashYaml, warnings)
        if (root == null) {
            return ConversionResult(ConfigDefaults.defaultConfigJson(), warnings)
        }

        val proxies = readMapList(root["proxies"])
        val proxyGroups = readMapList(root["proxy-groups"])
        val rules = readStringList(root["rules"])

        val outbounds = JSONArray()
        val outboundTags = linkedSetOf<String>()
        val proxyTags = linkedSetOf<String>()
        var firstGroupTag: String? = null

        addOutbound(outbounds, outboundTags, JSONObject().put("type", "direct").put("tag", "DIRECT"))
        addOutbound(outbounds, outboundTags, JSONObject().put("type", "block").put("tag", "BLOCK"))

        proxies.forEach { proxy ->
            mapProxy(proxy, warnings)?.let { outbound ->
                addOutbound(outbounds, outboundTags, outbound)
                val tag = outbound.optString("tag")
                if (tag.isNotBlank()) {
                    proxyTags.add(tag)
                }
            }
        }

        proxyGroups.forEach { group ->
            mapGroup(group, warnings)?.let { outbound ->
                addOutbound(outbounds, outboundTags, outbound)
                if (firstGroupTag == null) {
                    firstGroupTag = outbound.optString("tag")
                }
            }
        }

        if (firstGroupTag == null && proxyTags.isNotEmpty()) {
            val groupTag = uniqueTag("Proxy", outboundTags)
            val outboundsJson = JSONArray().apply {
                proxyTags.forEach { put(it) }
            }
            val groupOutbound = JSONObject()
                .put("type", "selector")
                .put("tag", groupTag)
                .put("outbounds", outboundsJson)
                .put("default", proxyTags.first())
            addOutbound(outbounds, outboundTags, groupOutbound)
            firstGroupTag = groupTag
        }

        val finalOutbound = firstGroupTag ?: proxyTags.firstOrNull() ?: "DIRECT"
        val routeRules = JSONArray()
        rules.forEach { rule ->
            parseRule(rule, warnings)?.let { routeRules.put(it) }
        }

        val route = JSONObject()
            .put("rules", routeRules)
            .put("final", finalOutbound)

        val config = JSONObject()
            .put("log", JSONObject().put("level", (root["log-level"] ?: "info").toString()))
            .put("inbounds", JSONArray().put(ConfigDefaults.tunInbound()))
            .put("outbounds", outbounds)
            .put("route", route)

        return ConversionResult(config.toString(2), warnings)
    }

    private fun readRootMap(clashYaml: String, warnings: MutableList<String>): Map<*, *>? {
        return try {
            val parsed = Yaml().load<Any>(clashYaml)
            parsed as? Map<*, *>
        } catch (e: Exception) {
            warnings.add("clash yaml parse failed: ${e.message}")
            null
        }
    }

    private fun readMapList(value: Any?): List<Map<*, *>> {
        return (value as? List<*>)?.mapNotNull { it as? Map<*, *> } ?: emptyList()
    }

    private fun readStringList(value: Any?): List<String> {
        return (value as? List<*>)?.mapNotNull { it?.toString() } ?: emptyList()
    }

    private fun addOutbound(outbounds: JSONArray, tags: MutableSet<String>, outbound: JSONObject) {
        val tag = outbound.optString("tag")
        if (tag.isNotEmpty() && tags.add(tag)) {
            outbounds.put(outbound)
        }
    }

    private fun mapProxy(proxy: Map<*, *>, warnings: MutableList<String>): JSONObject? {
        val name = proxy["name"]?.toString() ?: return null
        val type = proxy["type"]?.toString()?.lowercase() ?: return null
        return when (type) {
            "ss", "shadowsocks" -> mapShadowsocks(proxy, name, warnings)
            "trojan" -> mapTrojan(proxy, name)
            "vmess" -> mapVmess(proxy, name)
            "vless" -> mapVless(proxy, name)
            "socks5", "socks" -> mapSocks(proxy, name)
            "http" -> mapHttp(proxy, name)
            else -> {
                warnings.add("unsupported proxy type: $type ($name)")
                null
            }
        }
    }

    private fun mapShadowsocks(proxy: Map<*, *>, name: String, warnings: MutableList<String>): JSONObject? {
        val server = proxy["server"]?.toString() ?: return null
        val port = proxy["port"]?.toString()?.toIntOrNull() ?: return null
        val method = proxy["cipher"]?.toString() ?: proxy["method"]?.toString()
        val password = proxy["password"]?.toString()
        if (method.isNullOrBlank() || password.isNullOrBlank()) {
            warnings.add("shadowsocks missing method/password: $name")
            return null
        }
        val outbound = JSONObject()
            .put("type", "shadowsocks")
            .put("tag", name)
            .put("server", server)
            .put("server_port", port)
            .put("method", method)
            .put("password", password)
        val plugin = proxy["plugin"]?.toString()
        if (!plugin.isNullOrBlank()) {
            outbound.put("plugin", plugin)
            proxy["plugin-opts"]?.toString()?.let { outbound.put("plugin_opts", it) }
        }
        return outbound
    }

    private fun mapTrojan(proxy: Map<*, *>, name: String): JSONObject? {
        val server = proxy["server"]?.toString() ?: return null
        val port = proxy["port"]?.toString()?.toIntOrNull() ?: return null
        val password = proxy["password"]?.toString() ?: return null
        val outbound = JSONObject()
            .put("type", "trojan")
            .put("tag", name)
            .put("server", server)
            .put("server_port", port)
            .put("password", password)
        buildTls(proxy)?.let { outbound.put("tls", it) }
        buildTransport(proxy)?.let { outbound.put("transport", it) }
        return outbound
    }

    private fun mapVmess(proxy: Map<*, *>, name: String): JSONObject? {
        val server = proxy["server"]?.toString() ?: return null
        val port = proxy["port"]?.toString()?.toIntOrNull() ?: return null
        val uuid = proxy["uuid"]?.toString() ?: return null
        val outbound = JSONObject()
            .put("type", "vmess")
            .put("tag", name)
            .put("server", server)
            .put("server_port", port)
            .put("uuid", uuid)
        proxy["cipher"]?.toString()?.let { outbound.put("security", it) }
        proxy["alterId"]?.toString()?.toIntOrNull()?.let { outbound.put("alter_id", it) }
        buildTls(proxy)?.let { outbound.put("tls", it) }
        buildTransport(proxy)?.let { outbound.put("transport", it) }
        return outbound
    }

    private fun mapVless(proxy: Map<*, *>, name: String): JSONObject? {
        val server = proxy["server"]?.toString() ?: return null
        val port = proxy["port"]?.toString()?.toIntOrNull() ?: return null
        val uuid = proxy["uuid"]?.toString() ?: return null
        val outbound = JSONObject()
            .put("type", "vless")
            .put("tag", name)
            .put("server", server)
            .put("server_port", port)
            .put("uuid", uuid)
        proxy["flow"]?.toString()?.let { outbound.put("flow", it) }
        buildTls(proxy)?.let { outbound.put("tls", it) }
        buildTransport(proxy)?.let { outbound.put("transport", it) }
        return outbound
    }

    private fun mapSocks(proxy: Map<*, *>, name: String): JSONObject? {
        val server = proxy["server"]?.toString() ?: return null
        val port = proxy["port"]?.toString()?.toIntOrNull() ?: return null
        val outbound = JSONObject()
            .put("type", "socks")
            .put("tag", name)
            .put("server", server)
            .put("server_port", port)
        proxy["username"]?.toString()?.let { outbound.put("username", it) }
        proxy["password"]?.toString()?.let { outbound.put("password", it) }
        return outbound
    }

    private fun mapHttp(proxy: Map<*, *>, name: String): JSONObject? {
        val server = proxy["server"]?.toString() ?: return null
        val port = proxy["port"]?.toString()?.toIntOrNull() ?: return null
        val outbound = JSONObject()
            .put("type", "http")
            .put("tag", name)
            .put("server", server)
            .put("server_port", port)
        proxy["username"]?.toString()?.let { outbound.put("username", it) }
        proxy["password"]?.toString()?.let { outbound.put("password", it) }
        buildTls(proxy)?.let { outbound.put("tls", it) }
        return outbound
    }

    private fun buildTls(proxy: Map<*, *>): JSONObject? {
        val tlsEnabled = proxy["tls"] as? Boolean ?: false
        if (!tlsEnabled) {
            return null
        }
        val tls = JSONObject().put("enabled", true)
        val serverName = proxy["servername"]?.toString() ?: proxy["sni"]?.toString()
        if (!serverName.isNullOrBlank()) {
            tls.put("server_name", serverName)
        }
        (proxy["skip-cert-verify"] as? Boolean)?.let { tls.put("insecure", it) }
        val alpn = proxy["alpn"]
        if (alpn is List<*>) {
            val alpnArray = JSONArray()
            alpn.filterNotNull().forEach { alpnArray.put(it.toString()) }
            tls.put("alpn", alpnArray)
        }
        return tls
    }

    private fun buildTransport(proxy: Map<*, *>): JSONObject? {
        val network = proxy["network"]?.toString()?.lowercase() ?: return null
        return when (network) {
            "ws" -> {
                val opts = proxy["ws-opts"] as? Map<*, *>
                val path = opts?.get("path")?.toString() ?: "/"
                val headers = opts?.get("headers") as? Map<*, *>
                val transport = JSONObject()
                    .put("type", "ws")
                    .put("path", path)
                if (headers != null) {
                    val headerJson = JSONObject()
                    headers.forEach { (key, value) ->
                        if (key != null && value != null) {
                            headerJson.put(key.toString(), value.toString())
                        }
                    }
                    transport.put("headers", headerJson)
                }
                transport
            }
            "grpc" -> {
                val opts = proxy["grpc-opts"] as? Map<*, *>
                val serviceName = opts?.get("grpc-service-name")?.toString()
                    ?: opts?.get("service-name")?.toString()
                val transport = JSONObject().put("type", "grpc")
                serviceName?.takeIf { it.isNotBlank() }?.let { transport.put("service_name", it) }
                transport
            }
            "h2" -> {
                val opts = proxy["h2-opts"] as? Map<*, *>
                val transport = JSONObject().put("type", "http")
                val hostValue = opts?.get("host")
                val hosts = when (hostValue) {
                    is List<*> -> hostValue.mapNotNull { it?.toString()?.takeIf { h -> h.isNotBlank() } }
                    null -> emptyList()
                    else -> listOf(hostValue.toString()).filter { it.isNotBlank() }
                }
                if (hosts.isNotEmpty()) {
                    val hostArray = JSONArray()
                    hosts.forEach { hostArray.put(it) }
                    transport.put("host", hostArray)
                }
                opts?.get("path")?.toString()?.takeIf { it.isNotBlank() }?.let {
                    transport.put("path", it)
                }
                transport
            }
            else -> null
        }
    }

    private fun mapGroup(group: Map<*, *>, warnings: MutableList<String>): JSONObject? {
        val name = group["name"]?.toString() ?: return null
        val type = group["type"]?.toString()?.lowercase() ?: return null
        val proxies = readStringList(group["proxies"])
        val outbounds = JSONArray()
        proxies.forEach { outbounds.put(it) }

        return when (type) {
            "select" -> JSONObject()
                .put("type", "selector")
                .put("tag", name)
                .put("outbounds", outbounds)
                .put("default", proxies.firstOrNull() ?: "DIRECT")

            "url-test", "fallback" -> JSONObject()
                .put("type", "urltest")
                .put("tag", name)
                .put("outbounds", outbounds)
                .put("url", group["url"]?.toString() ?: "http://www.gstatic.com/generate_204")
                .put("interval", group["interval"]?.toString() ?: "5m")

            else -> {
                warnings.add("unsupported proxy-group type: $type ($name)")
                null
            }
        }
    }

    private fun parseRule(rule: String, warnings: MutableList<String>): JSONObject? {
        val parts = rule.split(",").map { it.trim() }.filter { it.isNotEmpty() }
        if (parts.size < 2) {
            warnings.add("invalid rule: $rule")
            return null
        }

        val type = parts[0].uppercase()
        if (type == "MATCH") {
            return null
        }

        val target = parts.last()
        val outbound = when (target.uppercase()) {
            "DIRECT" -> "DIRECT"
            "REJECT", "REJECT-TINY" -> "BLOCK"
            else -> target
        }

        val ruleJson = JSONObject()
            .put("action", "route")
            .put("outbound", outbound)

        when (type) {
            "DOMAIN" -> ruleJson.put("domain", JSONArray().put(parts[1]))
            "DOMAIN-SUFFIX" -> ruleJson.put("domain_suffix", JSONArray().put(parts[1]))
            "DOMAIN-KEYWORD" -> ruleJson.put("domain_keyword", JSONArray().put(parts[1]))
            "IP-CIDR", "IP-CIDR6" -> ruleJson.put("ip_cidr", JSONArray().put(parts[1]))
            "GEOIP" -> ruleJson.put("geoip", JSONArray().put(parts[1]))
            "SRC-IP-CIDR" -> ruleJson.put("source_ip_cidr", JSONArray().put(parts[1]))
            "DST-PORT" -> ruleJson.put("port", JSONArray().put(parts[1]))
            "SRC-PORT" -> ruleJson.put("source_port", JSONArray().put(parts[1]))
            else -> {
                warnings.add("unsupported rule type: $type ($rule)")
                return null
            }
        }
        return ruleJson
    }

    private fun uniqueTag(base: String, existing: Set<String>): String {
        if (!existing.contains(base)) {
            return base
        }
        var index = 2
        while (true) {
            val candidate = "$base-$index"
            if (!existing.contains(candidate)) {
                return candidate
            }
            index++
        }
    }
}
