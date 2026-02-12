package cn.moncn.sing_box_windows.config

import android.content.Context
import cn.moncn.sing_box_windows.vpn.VpnDefaults
import org.json.JSONArray
import org.json.JSONObject
import java.io.File

object ConfigRepository {
    private const val FILE_NAME = "singbox.json"

    fun loadOrCreateConfig(context: Context): String {
        val file = File(context.filesDir, FILE_NAME)
        if (!file.exists()) {
            val defaultJson = ConfigDefaults.defaultConfigJson()
            file.writeText(defaultJson)
            return defaultJson
        }
        val raw = file.readText()
        val migrated = migrateTunAddress(raw)
        if (migrated != raw) {
            file.writeText(migrated)
        }
        return migrated
    }

    fun saveConfig(context: Context, json: String) {
        val file = File(context.filesDir, FILE_NAME)
        val normalized = normalizeConfig(json)
        file.writeText(normalized)
    }

    fun saveConfigWithSettings(context: Context, json: String, settings: AppSettings) {
        val file = File(context.filesDir, FILE_NAME)
        val normalized = normalizeConfig(json)
        val applied = ConfigSettingsApplier.applySettings(normalized, settings)
        file.writeText(applied)
    }

    fun applySettings(context: Context, settings: AppSettings) {
        val file = File(context.filesDir, FILE_NAME)
        val raw = if (file.exists()) file.readText() else ConfigDefaults.defaultConfigJson()
        val normalized = normalizeConfig(raw)
        val applied = ConfigSettingsApplier.applySettings(normalized, settings)
        file.writeText(applied)
    }

    fun convertClashAndSave(context: Context, clashYaml: String): ConversionResult {
        val result = ClashConverter.convert(clashYaml)
        saveConfig(context, result.json)
        return result
    }

    private fun migrateTunAddress(json: String): String {
        return runCatching {
            val root = JSONObject(json)
            var changed = false
            changed = changed or migrateTunAddress(root)
            changed = changed or ensureOutboundGroup(root)
            if (changed) root.toString(2) else json
        }.getOrElse { json }
    }

    private fun normalizeConfig(json: String): String {
        return runCatching {
            val root = JSONObject(json)
            var changed = false
            changed = changed or migrateTunAddress(root)
            changed = changed or ensureOutboundGroup(root)
            if (changed) root.toString(2) else json
        }.getOrElse { json }
    }

    private fun migrateTunAddress(root: JSONObject): Boolean {
        val inbounds = root.optJSONArray("inbounds") ?: return false
        var changed = false
        for (i in 0 until inbounds.length()) {
            val inbound = inbounds.optJSONObject(i) ?: continue
            if (inbound.optString("type") != "tun") continue
            if (inbound.optString("stack", "system") != "system") continue
            val addresses = inbound.optJSONArray("address") ?: continue
            val updated = JSONArray()
            for (j in 0 until addresses.length()) {
                val address = addresses.optString(j)
                val replacement = if (address == "${VpnDefaults.ADDRESS}/32") {
                    "${VpnDefaults.ADDRESS}/${VpnDefaults.PREFIX}"
                } else {
                    address
                }
                if (replacement != address) {
                    changed = true
                }
                updated.put(replacement)
            }
            if (changed) {
                inbound.put("address", updated)
            }
        }
        return changed
    }

    private fun ensureOutboundGroup(root: JSONObject): Boolean {
        val outbounds = root.optJSONArray("outbounds") ?: return false
        var changed = false
        val existingTags = mutableSetOf<String>()
        val groupTypes = setOf("selector", "urltest", "fallback")
        var firstGroupTag: String? = null
        for (i in 0 until outbounds.length()) {
            val outbound = outbounds.optJSONObject(i) ?: continue
            val tag = outbound.optString("tag")
            if (tag.isNotBlank()) {
                existingTags.add(tag)
            }
            val type = outbound.optString("type")
            if (type in groupTypes && firstGroupTag == null) {
                firstGroupTag = tag
            }
        }

        if (firstGroupTag == null) {
            val proxyTags = mutableListOf<String>()
            val excludedTypes = setOf("direct", "block", "dns")
            for (i in 0 until outbounds.length()) {
                val outbound = outbounds.optJSONObject(i) ?: continue
                val tag = outbound.optString("tag")
                val type = outbound.optString("type")
                if (tag.isNotBlank() && type !in excludedTypes) {
                    proxyTags.add(tag)
                }
            }
            if (proxyTags.isEmpty()) {
                return changed
            }
            val groupTag = uniqueTag("Proxy", existingTags)
            val groupOutbound = JSONObject()
                .put("type", "selector")
                .put("tag", groupTag)
                .put("outbounds", JSONArray().apply { proxyTags.forEach { put(it) } })
                .put("default", proxyTags.first())
            outbounds.put(groupOutbound)
            existingTags.add(groupTag)
            firstGroupTag = groupTag
            changed = true
        }

        val route = root.optJSONObject("route") ?: JSONObject().also {
            root.put("route", it)
            changed = true
        }
        val resolvedGroupTag = firstGroupTag ?: return changed
        val finalTag = route.optString("final")
        if (finalTag.isBlank() || finalTag == "DIRECT") {
            route.put("final", resolvedGroupTag)
            changed = true
        }

        return changed
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
