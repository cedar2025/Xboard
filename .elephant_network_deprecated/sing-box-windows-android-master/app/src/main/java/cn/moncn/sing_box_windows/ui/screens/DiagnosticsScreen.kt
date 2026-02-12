package cn.moncn.sing_box_windows.ui.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import cn.moncn.sing_box_windows.config.AppSettingsDefaults
import cn.moncn.sing_box_windows.config.ClashApiDefaults
import cn.moncn.sing_box_windows.config.ConfigRepository
import cn.moncn.sing_box_windows.config.ConfigSettingsApplier
import cn.moncn.sing_box_windows.config.SettingsRepository
import cn.moncn.sing_box_windows.core.ClashApiClient
import cn.moncn.sing_box_windows.core.ClashApiDiagnostics
import cn.moncn.sing_box_windows.core.ClashApiDiagnosticsManager
import cn.moncn.sing_box_windows.core.EndpointStatus
import cn.moncn.sing_box_windows.ui.components.InfoRow
import cn.moncn.sing_box_windows.ui.components.NeoCard
import cn.moncn.sing_box_windows.ui.components.NeoDivider
import cn.moncn.sing_box_windows.ui.components.Section
import cn.moncn.sing_box_windows.ui.components.StatusBadge
import cn.moncn.sing_box_windows.ui.components.StatusBadgeSize
import androidx.compose.ui.platform.LocalContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date

/**
 * 现代化诊断页面
 * API 诊断界面
 */
@Composable
fun DiagnosticsScreen(
    diagnostics: ClashApiDiagnostics?
) {
    val context = LocalContext.current
    var configInfo by remember { mutableStateOf(ConfigInfo()) }

    DisposableEffect(Unit) {
        ClashApiDiagnosticsManager.start()
        onDispose { ClashApiDiagnosticsManager.stop() }
    }

    LaunchedEffect(Unit) {
        val settings = withContext(Dispatchers.IO) { SettingsRepository.load(context) }
        val rawConfig = withContext(Dispatchers.IO) { ConfigRepository.loadOrCreateConfig(context) }
        val appliedConfig = ConfigSettingsApplier.applySettings(rawConfig, settings)
        configInfo = parseConfigInfo(settings, appliedConfig)
        ClashApiClient.configureFromConfig(appliedConfig)
        // 默认启用 Clash API 用于诊断
        if (!ClashApiClient.isConfigured()) {
            ClashApiClient.configure(ClashApiDefaults.ADDRESS, ClashApiDefaults.SECRET)
        }
    }

    val scheme = MaterialTheme.colorScheme
    val display = diagnostics

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 20.dp, vertical = 24.dp),
        verticalArrangement = Arrangement.spacedBy(20.dp)
    ) {
        // ==================== 页面标题 ====================
        item {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(
                    text = "API 诊断",
                    style = MaterialTheme.typography.headlineLarge,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "查看接口连接状态",
                    style = MaterialTheme.typography.bodyMedium,
                    color = scheme.onSurfaceVariant
                )
            }
        }

        // ==================== 连接信息 ====================
        item {
            Section(title = { Text("连接信息", style = MaterialTheme.typography.titleMedium) }) {
                NeoCard(modifier = Modifier.fillMaxWidth()) {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        InfoRow(label = "控制地址", value = display?.baseUrl ?: "未配置")
                        NeoDivider()
                        InfoRow(label = "鉴权 Token", value = if (display?.hasSecret == true) "已设置" else "未设置")
                        NeoDivider()
                        InfoRow(label = "设置开关", value = if (configInfo.settingsEnabled) "开启" else "关闭")
                        NeoDivider()
                        InfoRow(label = "配置包含 API", value = if (configInfo.configEnabled) "是" else "否")
                    }
                }
            }
        }

        // ==================== WebSocket 状态 ====================
        item {
            Section(title = { Text("WebSocket", style = MaterialTheme.typography.titleMedium) }) {
                NeoCard(modifier = Modifier.fillMaxWidth()) {
                    Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
                        val traffic = display?.trafficSocket
                        SocketRow(
                            label = "流量通道",
                            connected = traffic?.connected == true,
                            lastMessageAt = traffic?.lastMessageAt,
                            error = traffic?.lastError
                        )
                        NeoDivider()
                        val memory = display?.memorySocket
                        SocketRow(
                            label = "内存通道",
                            connected = memory?.connected == true,
                            lastMessageAt = memory?.lastMessageAt,
                            error = memory?.lastError
                        )
                    }
                }
            }
        }

        // ==================== HTTP 接口状态 ====================
        item {
            Section(title = { Text("HTTP 接口", style = MaterialTheme.typography.titleMedium) }) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    if (display == null) {
                        NeoCard(modifier = Modifier.fillMaxWidth()) {
                            Text(
                                text = "诊断数据暂不可用",
                                style = MaterialTheme.typography.bodyMedium,
                                color = scheme.onSurfaceVariant
                            )
                        }
                    } else {
                        // 使用 LazyColumn.items
                        LazyColumn(modifier = Modifier.fillMaxWidth()) {
                            items(display.endpoints, key = { it.path }) { endpoint ->
                                EndpointCard(endpoint = endpoint)
                            }
                        }
                    }
                }
            }
        }

        // 底部留白
        item {
            Spacer(modifier = Modifier.height(16.dp))
        }
    }
}

// ==================== 端点卡片 ====================

@Composable
private fun EndpointCard(endpoint: EndpointStatus) {
    val scheme = MaterialTheme.colorScheme
    val badgeColor = if (endpoint.ok) scheme.secondary else scheme.error

    NeoCard(
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = endpoint.name,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Medium
                    )
                    Text(
                        text = endpoint.path,
                        style = MaterialTheme.typography.labelSmall,
                        color = scheme.onSurfaceVariant
                    )
                }
                StatusBadge(
                    text = endpoint.message ?: if (endpoint.ok) "OK" else "失败",
                    color = badgeColor,
                    size = StatusBadgeSize.Small
                )
            }

            if (!endpoint.sample.isNullOrBlank()) {
                Text(
                    text = endpoint.sample!!,
                    style = MaterialTheme.typography.labelSmall,
                    color = scheme.onSurfaceVariant
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = "HTTP: ${endpoint.code?.toString() ?: "-"}",
                    style = MaterialTheme.typography.labelSmall,
                    color = scheme.onSurfaceVariant
                )
                Text(
                    text = "时间: ${formatTime(endpoint.updatedAt)}",
                    style = MaterialTheme.typography.labelSmall,
                    color = scheme.onSurfaceVariant
                )
            }
        }
    }
}

// ==================== Socket 状态行 ====================

@Composable
private fun SocketRow(
    label: String,
    connected: Boolean,
    lastMessageAt: Long?,
    error: String?
) {
    val scheme = MaterialTheme.colorScheme
    val badgeColor = if (connected) scheme.secondary else scheme.error

    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.Medium
        )
        StatusBadge(
            text = if (connected) "已连接" else "未连接",
            color = badgeColor,
            size = StatusBadgeSize.Small
        )
    }

    if (!connected && !error.isNullOrBlank()) {
        Text(
            text = error,
            style = MaterialTheme.typography.labelSmall,
            color = scheme.error,
            modifier = Modifier.padding(top = 4.dp)
        )
    }
}

// ==================== 工具函数 ====================

private fun formatTime(timestamp: Long?): String {
    if (timestamp == null || timestamp <= 0L) return "---"
    val formatter = SimpleDateFormat.getTimeInstance()
    return formatter.format(Date(timestamp))
}

private data class ConfigInfo(
    val settingsEnabled: Boolean = false,
    val settingsAddress: String = "",
    val configEnabled: Boolean = false,
    val configAddress: String = "",
    val configHasSecret: Boolean = false
)

private fun parseConfigInfo(
    settings: cn.moncn.sing_box_windows.config.AppSettings,
    configJson: String
): ConfigInfo {
    val root = runCatching { JSONObject(configJson) }.getOrNull()
    val clashApi = root?.optJSONObject("experimental")?.optJSONObject("clash_api")
    val address = clashApi?.optString("external_controller")
        ?: clashApi?.optString("external-controller")
        ?: ""
    val hasSecret = !clashApi?.optString("secret").isNullOrBlank()
    return ConfigInfo(
        settingsEnabled = true, // 默认启用
        settingsAddress = ClashApiDefaults.ADDRESS,
        configEnabled = clashApi != null,
        configAddress = address.trim(),
        configHasSecret = hasSecret
    )
}
