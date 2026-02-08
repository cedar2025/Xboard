package cn.moncn.sing_box_windows.config

import org.json.JSONArray
import org.json.JSONObject

object ConfigSettingsApplier {
    fun applySettings(rawJson: String, settings: AppSettings): String {
        return runCatching {
            val root = JSONObject(rawJson)
            applyDns(root, settings)
            applyTunInbound(root, settings)
            root.toString(2)
        }.getOrElse { rawJson }
    }

    private fun applyDns(root: JSONObject, settings: AppSettings) {
        val dns = root.optJSONObject("dns") ?: return
        dns.put("strategy", settings.dnsStrategy)
        dns.put("independent_cache", settings.dnsIndependentCache)
        dns.put("disable_cache", !settings.dnsCacheEnabled)
        val route = root.optJSONObject("route") ?: return
        val resolver = route.optJSONObject("default_domain_resolver") ?: return
        resolver.put("strategy", settings.dnsStrategy)
    }

    private fun applyTunInbound(root: JSONObject, settings: AppSettings) {
        val inbounds = root.optJSONArray("inbounds") ?: return
        forEachInbound(inbounds) { inbound ->
            if (inbound.optString("type") != "tun") return@forEachInbound
            inbound.put("mtu", settings.tunMtu)
            inbound.put("auto_route", settings.tunAutoRoute)
            inbound.put("strict_route", settings.tunStrictRoute)
            val platform = inbound.optJSONObject("platform") ?: JSONObject().also {
                inbound.put("platform", it)
            }
            val httpProxy = platform.optJSONObject("http_proxy") ?: JSONObject().also {
                platform.put("http_proxy", it)
            }
            httpProxy.put("enabled", settings.httpProxyEnabled)
        }
    }

    private fun forEachInbound(inbounds: JSONArray, action: (JSONObject) -> Unit) {
        for (index in 0 until inbounds.length()) {
            val inbound = inbounds.optJSONObject(index) ?: continue
            action(inbound)
        }
    }
}
