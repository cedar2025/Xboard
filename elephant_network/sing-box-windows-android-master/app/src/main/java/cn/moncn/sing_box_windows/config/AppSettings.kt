package cn.moncn.sing_box_windows.config

data class AppSettings(
    val dnsStrategy: String = AppSettingsDefaults.DNS_STRATEGY,
    val dnsCacheEnabled: Boolean = AppSettingsDefaults.DNS_CACHE_ENABLED,
    val dnsIndependentCache: Boolean = AppSettingsDefaults.DNS_INDEPENDENT_CACHE,
    val tunMtu: Int = AppSettingsDefaults.TUN_MTU,
    val tunAutoRoute: Boolean = AppSettingsDefaults.TUN_AUTO_ROUTE,
    val tunStrictRoute: Boolean = AppSettingsDefaults.TUN_STRICT_ROUTE,
    val httpProxyEnabled: Boolean = AppSettingsDefaults.HTTP_PROXY_ENABLED
)

object AppSettingsDefaults {
    const val DNS_STRATEGY = "prefer_ipv4"
    const val DNS_CACHE_ENABLED = true
    const val DNS_INDEPENDENT_CACHE = true
    const val TUN_MTU = 9000
    const val TUN_AUTO_ROUTE = true
    const val TUN_STRICT_ROUTE = true
    const val HTTP_PROXY_ENABLED = true
}
