package cn.moncn.sing_box_windows.config

import cn.moncn.sing_box_windows.vpn.VpnDefaults
import org.json.JSONArray
import org.json.JSONObject

object ConfigDefaults {
    fun tunInbound(): JSONObject {
        return JSONObject()
            .put("type", "tun")
            .put("tag", "tun-in")
            .put("mtu", VpnDefaults.MTU)
            .put("address", JSONArray().put("${VpnDefaults.ADDRESS}/${VpnDefaults.PREFIX}"))
            .put("auto_route", false)
            .put("strict_route", false)
            .put("stack", "system")
            .put("sniff", true)
            .put("sniff_override_destination", true)
    }

    fun defaultConfigJson(): String {
        val outbounds = JSONArray()
            .put(JSONObject().put("type", "direct").put("tag", "DIRECT"))
            .put(JSONObject().put("type", "block").put("tag", "BLOCK"))

        val route = JSONObject()
            .put("final", "DIRECT")

        val config = JSONObject()
            .put("log", JSONObject().put("level", "info"))
            .put("inbounds", JSONArray().put(tunInbound()))
            .put("outbounds", outbounds)
            .put("route", route)

        return config.toString(2)
    }
}
