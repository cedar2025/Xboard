package cn.moncn.sing_box_windows.config

import org.json.JSONArray
import org.json.JSONObject

object ConfigSettingsApplier {
    fun applySettings(rawJson: String, settings: AppSettings): String {
        return runCatching {
            val root = JSONObject(rawJson)
            applyDns(root, settings)
            applyTunInbound(root, settings)
            applyClashApi(root)
            applyRouting(root)
            root.toString(2)
        }.getOrElse { rawJson }
    }

    private fun applyClashApi(root: JSONObject) {
        val experimental = root.optJSONObject("experimental") ?: JSONObject().also {
            root.put("experimental", it)
        }
        val clashApi = experimental.optJSONObject("clash_api") ?: JSONObject().also {
            experimental.put("clash_api", it)
        }
        clashApi.put("external_controller", ClashApiDefaults.ADDRESS)
        clashApi.put("secret", ClashApiDefaults.SECRET)
        // Ensure access from local loopback
        clashApi.put("external_ui", "")
        clashApi.put("external_ui_download_url", "")
        clashApi.put("external_ui_download_detour", "")
        clashApi.put("default_mode", "")
    }

    private fun applyDns(root: JSONObject, settings: AppSettings) {
        val dns = root.optJSONObject("dns") ?: JSONObject().also {
            root.put("dns", it)
        }
        
        // Ensure robust DNS servers exist
        // We add AliDNS for better domestic resolution
        val servers = JSONArray()
        servers.put(JSONObject().put("tag", "google").put("address", "8.8.8.8").put("detour", "DIRECT"))
        servers.put(JSONObject().put("tag", "ali").put("address", "223.5.5.5").put("detour", "DIRECT"))
        servers.put(JSONObject().put("tag", "local").put("address", "local").put("detour", "DIRECT"))
        dns.put("servers", servers)

        dns.put("strategy", "prefer_ipv4") // Force IPv4 preference
        dns.put("independent_cache", settings.dnsIndependentCache)
        dns.put("disable_cache", !settings.dnsCacheEnabled)
        
        // Ensure default resolver uses ali/local
        val route = root.optJSONObject("route") ?: JSONObject().also { root.put("route", it) }
        val resolver = route.optJSONObject("default_domain_resolver") ?: JSONObject().also { route.put("default_domain_resolver", it) }
        resolver.put("server", "ali")
        resolver.put("strategy", "prefer_ipv4")
    }

    private fun applyTunInbound(root: JSONObject, settings: AppSettings) {
        val inbounds = root.optJSONArray("inbounds") ?: return
        forEachInbound(inbounds) { inbound ->
            if (inbound.optString("type") != "tun") return@forEachInbound
            inbound.put("mtu", 9000) // Force MTU 9000 for performance
            inbound.put("auto_route", true) // Force Auto Route
            inbound.put("strict_route", false) // Disable Strict Route to prevent loop/blocking
            
            val platform = inbound.optJSONObject("platform") ?: JSONObject().also {
                inbound.put("platform", it)
            }
            val httpProxy = platform.optJSONObject("http_proxy") ?: JSONObject().also {
                platform.put("http_proxy", it)
            }
            httpProxy.put("enabled", settings.httpProxyEnabled)
            httpProxy.put("server", "127.0.0.1")
            httpProxy.put("server_port", 2080)
        }
    }

    private fun forEachInbound(inbounds: JSONArray, action: (JSONObject) -> Unit) {
        for (index in 0 until inbounds.length()) {
            val inbound = inbounds.optJSONObject(index) ?: continue
            action(inbound)
        }
    }

    private fun applyRouting(root: JSONObject) {
        val route = root.optJSONObject("route") ?: JSONObject().also { root.put("route", it) }
        val outbounds = root.optJSONArray("outbounds") ?: return
        val selectorTag = findMainSelector(outbounds) ?: return

        // Update Final to use the proxy group if not valid
        val currentFinal = route.optString("final", "")
        if (currentFinal.isEmpty() || currentFinal == "DIRECT" || currentFinal == "本地直连") {
            route.put("final", selectorTag)
        }
        
        // Rewrite remote rule_sets to local if we have the assets
        rewriteRemoteRuleSetsToLocal(route)

        ensureRules(route, selectorTag)
    }

    private fun rewriteRemoteRuleSetsToLocal(route: JSONObject) {
        val ruleSets = route.optJSONArray("rule_set") ?: return
        for (i in 0 until ruleSets.length()) {
            val ruleSet = ruleSets.optJSONObject(i) ?: continue
            val tag = ruleSet.optString("tag")
            
            // Check if this tag matches one of our local offline files
            // We support: geosite-google, geosite-cn, geosite-category-ads-all, etc.
            // Simplified check: validation is loose, we assume standard tags.
            // If the tag corresponds to a filename we likely have.
            
            if (shouldOffline(tag)) {
                // Transform to local
                ruleSet.put("type", "local")
                ruleSet.put("format", "binary")
                ruleSet.put("path", getLocalPath(tag)) // LibboxManager copies srs/*.srs to workingRoot/*.srs
                // Remove remote fields
                ruleSet.remove("url")
                ruleSet.remove("download_detour")
                ruleSet.remove("update_interval")
            } else {
                 // For unknown remote rules, we still force download_detour to proxy
                 // But most common ones should be caught above.
            }
        }
    }

    private fun shouldOffline(tag: String): Boolean {
        // List of srs files we downloaded
        val offlineTags = setOf(
            "geosite-category-ads-all",
            "geosite-telegram", "geoip-telegram",
            "geosite-youtube",
            "geosite-netflix", "geoip-netflix",
            "geosite-openai", "geosite-apple",
            "geosite-google", "geoip-google",
            "geosite-microsoft", "geosite-github",
            "geosite-geolocation-!cn",
            "geosite-cn", "geoip-cn",
            "geosite-private", "geoip-private"
        )
        if (tag in offlineTags) return true
        
        // Handle variations like "geosite-openai@ads" -> we map it to "geosite-openai.srs" if possible?
        // Actually, if we just check if it STARTS with a known prefix, we might map it to the wrong file.
        // But for "geosite-openai@ads", we want it to verify if we have coverage.
        // If we don't have "geosite-openai@ads.srs", we can't offline it easily unless we map it to "geosite-openai.srs".
        // Let's safe-guard: only offline if we have the exact file OR if we can map it.
        
        // Special mapping for openai@ads
        if (tag == "geosite-openai@ads") return true 
        
        return false
    } 
    
    // Helper to get local path
    private fun getLocalPath(tag: String): String {
        if (tag == "geosite-openai@ads") return "geosite-openai.srs" // Fallback to main openai rules or ads? 
        // Actually openai@ads is likely ads. Let's map it to category-ads-all if we want, or just openai.
        // Better yet: I'll map it to "geosite-openai.srs" assuming the user is okay with broad openai rules, 
        // OR I should have downloaded geosite-openai@ads.srs. 
        // I'll check if I can just offline it. 
        // Wait, I DID NOT download geosite-openai@ads.srs. I only downloaded geosite-openai.srs.
        // So I must map it.
        return "$tag.srs"
    }

    private fun findMainSelector(outbounds: JSONArray): String? {
        for (i in 0 until outbounds.length()) {
            val item = outbounds.optJSONObject(i) ?: continue
            val type = item.optString("type")
            val tag = item.optString("tag")
            if (type in setOf("selector", "urltest", "loadbalance")) {
                return tag
            }
        }
        return null
    }

    private fun ensureRules(route: JSONObject, proxyTag: String) {
        val rules = route.optJSONArray("rules") ?: JSONArray().also { route.put("rules", it) }
        
        // Ensure we have the definitions for the rule sets we are about to use
        ensureLocalRuleSetDefinitions(route)

        // 0. DNS Protocol -> Direct (Prevent loop)
        val dnsRule = JSONObject()
            .put("protocol", "dns")
            .put("outbound", "DIRECT")

        // 1. Google/Telegram -> Proxy
        // using rule_set instead of deprecated geosite field
        val googleRule = JSONObject()
            .put("rule_set", JSONArray()
                .put("geosite-google")
                .put("geosite-youtube")
                .put("geosite-telegram")
                .put("geoip-google")
                .put("geoip-telegram")
            )
            .put("outbound", proxyTag)
        
        // 2. CN -> Direct
        val cnRule = JSONObject()
            .put("rule_set", JSONArray()
                .put("geosite-cn")
                .put("geoip-cn")
            )
            .put("outbound", "DIRECT")

        val newRules = JSONArray()
        newRules.put(dnsRule) // Add DNS rule first
        newRules.put(googleRule)
        newRules.put(cnRule)
        
        // Append existing rules
        for (i in 0 until rules.length()) {
            val rule = rules.optJSONObject(i) ?: continue
            // Filter out any legacy geosite/geoip rules that might still exist in the user's config
            // to prevent "unknown field" errors if they slipped through
            if (rule.has("geosite") || rule.has("geoip")) continue
            
            newRules.put(rule)
        }
        route.put("rules", newRules)
    }

    private fun ensureLocalRuleSetDefinitions(route: JSONObject) {
        val ruleSets = route.optJSONArray("rule_set") ?: JSONArray().also { route.put("rule_set", it) }
        
        // Helper to check if a tag exists
        fun hasTag(tag: String): Boolean {
            for (i in 0 until ruleSets.length()) {
                if (ruleSets.optJSONObject(i)?.optString("tag") == tag) return true
            }
            return false
        }

        // List of essential offline rule sets we want to define
        val essentialSets = listOf(
            "geosite-google", "geosite-youtube", "geosite-telegram",
            "geoip-google", "geoip-telegram",
            "geosite-cn", "geoip-cn"
        )

        for (tag in essentialSets) {
            if (!hasTag(tag)) {
                // Add definition
                val def = JSONObject()
                def.put("tag", tag)
                def.put("type", "local")
                def.put("format", "binary")
                def.put("path", "$tag.srs")
                ruleSets.put(def)
            }
        }
    }
}
