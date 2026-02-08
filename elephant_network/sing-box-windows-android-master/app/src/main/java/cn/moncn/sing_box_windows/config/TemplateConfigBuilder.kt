package cn.moncn.sing_box_windows.config

import org.json.JSONArray
import org.json.JSONObject
import org.yaml.snakeyaml.Yaml

data class TemplateBuildResult(
    val ok: Boolean,
    val configJson: String? = null,
    val warnings: List<String> = emptyList(),
    val error: String? = null
)

data class NodeParseResult(
    val nodes: List<JSONObject>,
    val warnings: List<String>
)

object TemplateConfigBuilder {
    private const val TAG_PROXY = "手动切换"
    private const val TAG_AUTO = "自动选择"
    private const val TAG_DIRECT = "direct"
    private const val TAG_LOCAL = "本地直连"
    private const val TAG_DNS_PROXY = "dns_proxy"
    private const val TAG_DNS_DIRECT = "dns_direct"
    private const val TAG_DNS_RESOLVER = "dns_resolver"
    private const val TAG_DNS_GOOGLE = "google"
    private const val TAG_TELEGRAM = "Telegram"
    private const val TAG_YOUTUBE = "YouTube"
    private const val TAG_NETFLIX = "netflix"
    private const val TAG_OPENAI = "OpenAI"
    private const val TAG_APPLE = "Apple"
    private const val TAG_GOOGLE = "Google"
    private const val TAG_MICROSOFT = "Microsoft"
    private const val TAG_GITHUB = "Github"

    fun buildFromSubscription(content: String): TemplateBuildResult {
        val trimmed = content.trim()
        val jsonNodes = parseSingBoxNodes(trimmed)
        val parseResult = jsonNodes ?: parseClashNodes(trimmed)
            ?: return TemplateBuildResult(
                ok = false,
                error = "订阅内容无法识别为 sing-box JSON 或 Clash 配置"
            )
        if (parseResult.nodes.isEmpty()) {
            return TemplateBuildResult(ok = false, error = "订阅中未找到可用节点")
        }
        val config = buildTemplate(parseResult.nodes)
        return TemplateBuildResult(ok = true, configJson = config, warnings = parseResult.warnings)
    }

    private fun parseSingBoxNodes(json: String): NodeParseResult? {
        if (!isLikelyJson(json)) return null
        return runCatching {
            val root = JSONObject(json)
            val outbounds = root.optJSONArray("outbounds") ?: return null
            val warnings = mutableListOf<String>()
            val nodes = mutableListOf<JSONObject>()
            for (i in 0 until outbounds.length()) {
                val outbound = outbounds.optJSONObject(i) ?: continue
                val type = outbound.optString("type")
                if (isGroupOrSystemOutbound(type)) {
                    continue
                }
                nodes.add(JSONObject(outbound.toString()))
            }
            NodeParseResult(nodes, warnings)
        }.getOrNull()
    }

    private fun parseClashNodes(yamlContent: String): NodeParseResult? {
        val root = runCatching { Yaml().load<Any>(yamlContent) as? Map<*, *> }
            .getOrNull() ?: return null
        val warnings = mutableListOf<String>()
        val proxies = readMapList(root["proxies"])
        val nodes = mutableListOf<JSONObject>()
        proxies.forEach { proxy ->
            mapProxy(proxy, warnings)?.let { nodes.add(it) }
        }
        return NodeParseResult(nodes, warnings)
    }

    
    private fun buildTemplate(nodes: List<JSONObject>): String {
        val outbounds = JSONArray()
        val usedTags = linkedSetOf(
            TAG_DIRECT,
            TAG_LOCAL,
            TAG_PROXY,
            TAG_AUTO,
            TAG_TELEGRAM,
            TAG_YOUTUBE,
            TAG_NETFLIX,
            TAG_OPENAI,
            TAG_APPLE,
            TAG_GOOGLE,
            TAG_MICROSOFT,
            TAG_GITHUB
        )

        val normalizedNodes = nodes.mapIndexed { index, node ->
            val copied = JSONObject(node.toString())
            val originalTag = copied.optString("tag").ifBlank { "Node-${index + 1}" }
            val safeTag = uniqueTag(originalTag, usedTags)
            if (safeTag != originalTag) {
                copied.put("tag", safeTag)
            }
            usedTags.add(safeTag)
            copied
        }

        val nodeTags = normalizedNodes.map { it.optString("tag") }

        val autoGroup = JSONObject()
            .put("type", "urltest")
            .put("tag", TAG_AUTO)
            .put("outbounds", JSONArray().apply { nodeTags.forEach { put(it) } })
            .put("url", "https://www.gstatic.com/generate_204")
            .put("interval", "3m")
            .put("tolerance", 50)
            .put("interrupt_exist_connections", true)
        outbounds.put(autoGroup)

        outbounds.put(
            JSONObject()
                .put("type", "selector")
                .put("tag", TAG_PROXY)
                .put(
                    "outbounds",
                    JSONArray().apply {
                        put(TAG_AUTO)
                        nodeTags.forEach { put(it) }
                    }
                )
                .put("default", TAG_AUTO)
                .put("interrupt_exist_connections", true)
        )

        val serviceTags = listOf(TAG_TELEGRAM, TAG_YOUTUBE, TAG_NETFLIX, TAG_OPENAI)
        serviceTags.forEach { tag ->
            outbounds.put(
                JSONObject()
                    .put("type", "selector")
                    .put("tag", tag)
                    .put("outbounds", buildServiceOutbounds(nodeTags, includeDirect = false))
                    .put("default", TAG_AUTO)
                    .put("interrupt_exist_connections", true)
            )
        }

        val directDefaultTags = listOf(TAG_APPLE, TAG_MICROSOFT)
        directDefaultTags.forEach { tag ->
            outbounds.put(
                JSONObject()
                    .put("type", "selector")
                    .put("tag", tag)
                    .put("outbounds", buildServiceOutbounds(nodeTags, includeDirect = true))
                    .put("default", TAG_DIRECT)
                    .put("interrupt_exist_connections", true)
            )
        }

        val proxyDefaultTags = listOf(TAG_GOOGLE, TAG_GITHUB)
        proxyDefaultTags.forEach { tag ->
            outbounds.put(
                JSONObject()
                    .put("type", "selector")
                    .put("tag", tag)
                    .put("outbounds", buildServiceOutbounds(nodeTags, includeDirect = true))
                    .put("default", TAG_AUTO)
                    .put("interrupt_exist_connections", true)
            )
        }

        outbounds.put(
            JSONObject()
                .put("type", "selector")
                .put("tag", TAG_LOCAL)
                .put("outbounds", buildLocalOutbounds(nodeTags))
                .put("default", TAG_DIRECT)
                .put("interrupt_exist_connections", true)
        )

        outbounds.put(JSONObject().put("type", "direct").put("tag", TAG_DIRECT))
        normalizedNodes.forEach { outbounds.put(it) }

        val dns = JSONObject()
            .put(
                "servers",
                JSONArray()
                    .put(
                        JSONObject()
                            .put("type", "https")
                            .put("server", "1.1.1.1")
                            .put("server_port", 443)
                            .put("path", "/dns-query")
                            .put("tag", TAG_DNS_PROXY)
                            .put("detour", TAG_AUTO)
                            .put("domain_resolver", TAG_DNS_RESOLVER)
                    )
                    .put(
                        JSONObject()
                            .put("type", "h3")
                            .put("server", "dns.alidns.com")
                            .put("server_port", 443)
                            .put("path", "/dns-query")
                            .put("tag", TAG_DNS_DIRECT)
                            .put("domain_resolver", TAG_DNS_RESOLVER)
                    )
                    .put(
                        JSONObject()
                            .put("type", "tls")
                            .put("server", "8.8.4.4")
                            .put("tag", TAG_DNS_GOOGLE)
                            .put("domain_resolver", TAG_DNS_RESOLVER)
                    )
                    .put(
                        JSONObject()
                            .put("type", "udp")
                            .put("server", "114.114.114.114")
                            .put("tag", TAG_DNS_RESOLVER)
                    )
            )
            .put(
                "rules",
                JSONArray()
                    .put(
                        JSONObject()
                            .put("action", "route")
                            .put("clash_mode", "direct")
                            .put("server", TAG_DNS_DIRECT)
                    )
                    .put(
                        JSONObject()
                            .put("action", "route")
                            .put("clash_mode", "global")
                            .put("server", TAG_DNS_PROXY)
                    )
                    .put(
                        JSONObject()
                            .put("action", "route")
                            .put("rule_set", "geosite-cn")
                            .put("server", TAG_DNS_DIRECT)
                    )
                    .put(
                        JSONObject()
                            .put("action", "route")
                            .put("rule_set", "geoip-cn")
                            .put("server", TAG_DNS_DIRECT)
                    )
                    .put(
                        JSONObject()
                            .put("action", "route")
                            .put("rule_set", "geosite-geolocation-!cn")
                            .put("server", TAG_DNS_PROXY)
                    )
            )
            .put("independent_cache", true)
            .put("strategy", "prefer_ipv4")
            .put("final", TAG_DNS_DIRECT)

        val routeRules = JSONArray()
            .put(JSONObject().put("action", "sniff"))
            .put(JSONObject().put("protocol", "dns").put("action", "hijack-dns"))
            .put(JSONObject().put("ip_is_private", true).put("outbound", TAG_DIRECT))
            .put(JSONObject().put("clash_mode", "global").put("outbound", TAG_PROXY))
            .put(JSONObject().put("clash_mode", "direct").put("outbound", TAG_LOCAL))
            .put(
                JSONObject()
                    .put("type", "logical")
                    .put("mode", "or")
                    .put(
                        "rules",
                        JSONArray()
                            .put(JSONObject().put("rule_set", "geosite-category-ads-all"))
                            .put(JSONObject().put("domain_regex", "^stun\\..+"))
                            // 避免误伤国内 App 的 HTTPDNS，仅拦截 STUN 与广告域名。
                            .put(JSONObject().put("domain_keyword", JSONArray().put("stun")))
                            .put(JSONObject().put("protocol", "stun"))
                    )
                    .put("action", "reject")
                    .put("method", "default")
                    .put("no_drop", false)
            )
            .put(
                JSONObject()
                    .put("rule_set", JSONArray().put("geosite-telegram").put("geoip-telegram"))
                    .put("outbound", TAG_TELEGRAM)
            )
            .put(JSONObject().put("rule_set", "geosite-youtube").put("outbound", TAG_YOUTUBE))
            .put(
                JSONObject()
                    .put("rule_set", JSONArray().put("geosite-netflix").put("geoip-netflix"))
                    .put("outbound", TAG_NETFLIX)
            )
            .put(
                JSONObject()
                    .put("rule_set", "geosite-openai@ads")
                    .put("action", "reject")
                    .put("method", "default")
                    .put("no_drop", false)
            )
            .put(JSONObject().put("rule_set", "geosite-openai").put("outbound", TAG_OPENAI))
            .put(JSONObject().put("rule_set", "geosite-apple").put("outbound", TAG_APPLE))
            .put(
                JSONObject()
                    .put("rule_set", JSONArray().put("geosite-google").put("geoip-google"))
                    .put("outbound", TAG_GOOGLE)
            )
            .put(JSONObject().put("rule_set", "geosite-microsoft").put("outbound", TAG_MICROSOFT))
            .put(JSONObject().put("rule_set", "geosite-github").put("outbound", TAG_GITHUB))
            .put(JSONObject().put("rule_set", "geosite-geolocation-!cn").put("outbound", TAG_PROXY))
            .put(
                JSONObject()
                    .put(
                        "rule_set",
                        JSONArray()
                            .put("geosite-private")
                            .put("geosite-cn")
                            .put("geoip-private")
                            .put("geoip-cn")
                    )
                    .put("outbound", TAG_LOCAL)
            )

        val route = JSONObject()
            .put("rules", routeRules)
            .put("final", TAG_LOCAL)
            .put("auto_detect_interface", true)
            .put(
                "default_domain_resolver",
                JSONObject()
                    .put("server", TAG_DNS_RESOLVER)
                    .put("strategy", "prefer_ipv4")
            )
            .put(
                "rule_set",
                JSONArray()
                    .put(
                        JSONObject()
                            .put("tag", "geosite-category-ads-all")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/category-ads-all.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-telegram")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/telegram.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geoip-telegram")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geoip/telegram.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-youtube")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/youtube.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-netflix")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/netflix.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geoip-netflix")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geoip/netflix.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-openai@ads")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/openai@ads.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-openai")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/openai.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-apple")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/apple.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-google")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/google.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geoip-google")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geoip/google.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-microsoft")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/microsoft.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-geolocation-!cn")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/geolocation-!cn.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-github")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/github.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-private")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/private.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geosite-cn")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite/cn.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geoip-private")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geoip/private.srs")
                            .put("update_interval", "1d")
                    )
                    .put(
                        JSONObject()
                            .put("tag", "geoip-cn")
                            .put("type", "remote")
                            .put("format", "binary")
                            .put("url", "https://gh-proxy.com/https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geoip/cn.srs")
                            .put("update_interval", "1d")
                    )
            )

        val inbound = JSONObject()
            .put("type", "tun")
            .put("tag", "tun-in")
            .put(
                "address",
                JSONArray()
                    .put("172.18.0.1/30")
                    .put("fdfe:dcba:9876::1/126")
            )
            .put("mtu", 9000)
            .put("auto_route", true)
            .put("strict_route", true)
            .put("stack", "system")
            .put("sniff", true)
            .put(
                "platform",
                JSONObject()
                    .put(
                        "http_proxy",
                        JSONObject()
                            .put("enabled", true)
                            .put("server", "127.0.0.1")
                            .put("server_port", 1082)
                    )
            )

        val mixedInbound = JSONObject()
            .put("type", "mixed")
            .put("listen", "127.0.0.1")
            .put("listen_port", 1082)
            .put("sniff", true)
            .put("users", JSONArray())

        val socksInbound = JSONObject()
            .put("type", "socks")
            .put("tag", "socks-in")
            .put("listen", "127.0.0.1")
            .put("listen_port", 7888)

        val config = JSONObject()
            .put(
                "log",
                JSONObject()
                    .put("disabled", false)
                    .put("level", "info")
                    .put("timestamp", true)
            )
            .put("dns", dns)
            .put("inbounds", JSONArray().put(inbound).put(mixedInbound).put(socksInbound))
            .put("outbounds", outbounds)
            .put("route", route)
            .put(
                "experimental",
                JSONObject()
                    .put(
                        "cache_file",
                        JSONObject()
                            .put("enabled", true)
                    )
                    .put(
                        "clash_api",
                        JSONObject()
                            .put("default_mode", "rule")
                            .put("external_controller", "127.0.0.1:9090")
                            .put("external_ui", "metacubexd")
                            .put("external_ui_download_detour", TAG_DIRECT)
                            .put(
                                "external_ui_download_url",
                                "https://gh-proxy.com/https://github.com/MetaCubeX/metacubexd/archive/refs/heads/gh-pages.zip"
                            )
                    )
            )

        return config.toString(2)
    }

    private fun buildServiceOutbounds(nodeTags: List<String>, includeDirect: Boolean): JSONArray {
        val outbounds = JSONArray()
        outbounds.put(TAG_AUTO)
        outbounds.put(TAG_PROXY)
        if (includeDirect) {
            outbounds.put(TAG_DIRECT)
        }
        nodeTags.forEach { outbounds.put(it) }
        return outbounds
    }

    private fun buildLocalOutbounds(nodeTags: List<String>): JSONArray {
        val outbounds = JSONArray()
        outbounds.put(TAG_DIRECT)
        outbounds.put(TAG_AUTO)
        outbounds.put(TAG_PROXY)
        nodeTags.forEach { outbounds.put(it) }
        return outbounds
    }

    private fun readMapList(value: Any?): List<Map<*, *>> {
        return (value as? List<*>)?.mapNotNull { it as? Map<*, *> } ?: emptyList()
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

    private fun isGroupOrSystemOutbound(type: String): Boolean {
        return type in setOf("direct", "block", "dns", "selector", "urltest", "fallback")
    }

    private fun uniqueTag(base: String, existing: MutableSet<String>): String {
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

    private fun isLikelyJson(text: String): Boolean {
        val sample = text.trimStart()
        if (!(sample.startsWith("{") || sample.startsWith("["))) {
            return false
        }
        return sample.contains("\"outbounds\"") || sample.contains("\"inbounds\"")
    }
}
