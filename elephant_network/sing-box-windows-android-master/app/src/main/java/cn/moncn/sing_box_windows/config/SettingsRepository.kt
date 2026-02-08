package cn.moncn.sing_box_windows.config

import android.content.Context

object SettingsRepository {
    private const val PREFS_NAME = "app_settings"
    private const val KEY_DNS_STRATEGY = "dns_strategy"
    private const val KEY_DNS_CACHE_ENABLED = "dns_cache_enabled"
    private const val KEY_DNS_INDEPENDENT_CACHE = "dns_independent_cache"
    private const val KEY_TUN_MTU = "tun_mtu"
    private const val KEY_TUN_AUTO_ROUTE = "tun_auto_route"
    private const val KEY_TUN_STRICT_ROUTE = "tun_strict_route"
    private const val KEY_HTTP_PROXY_ENABLED = "http_proxy_enabled"

    fun load(context: Context): AppSettings {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        return AppSettings(
            dnsStrategy = prefs.getString(KEY_DNS_STRATEGY, AppSettingsDefaults.DNS_STRATEGY)
                ?: AppSettingsDefaults.DNS_STRATEGY,
            dnsCacheEnabled = prefs.getBoolean(
                KEY_DNS_CACHE_ENABLED,
                AppSettingsDefaults.DNS_CACHE_ENABLED
            ),
            dnsIndependentCache = prefs.getBoolean(
                KEY_DNS_INDEPENDENT_CACHE,
                AppSettingsDefaults.DNS_INDEPENDENT_CACHE
            ),
            tunMtu = prefs.getInt(KEY_TUN_MTU, AppSettingsDefaults.TUN_MTU),
            tunAutoRoute = prefs.getBoolean(KEY_TUN_AUTO_ROUTE, AppSettingsDefaults.TUN_AUTO_ROUTE),
            tunStrictRoute = prefs.getBoolean(KEY_TUN_STRICT_ROUTE, AppSettingsDefaults.TUN_STRICT_ROUTE),
            httpProxyEnabled = prefs.getBoolean(
                KEY_HTTP_PROXY_ENABLED,
                AppSettingsDefaults.HTTP_PROXY_ENABLED
            )
        )
    }

    fun save(context: Context, settings: AppSettings) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        prefs.edit()
            .putString(KEY_DNS_STRATEGY, settings.dnsStrategy)
            .putBoolean(KEY_DNS_CACHE_ENABLED, settings.dnsCacheEnabled)
            .putBoolean(KEY_DNS_INDEPENDENT_CACHE, settings.dnsIndependentCache)
            .putInt(KEY_TUN_MTU, settings.tunMtu)
            .putBoolean(KEY_TUN_AUTO_ROUTE, settings.tunAutoRoute)
            .putBoolean(KEY_TUN_STRICT_ROUTE, settings.tunStrictRoute)
            .putBoolean(KEY_HTTP_PROXY_ENABLED, settings.httpProxyEnabled)
            .apply()
    }
}
