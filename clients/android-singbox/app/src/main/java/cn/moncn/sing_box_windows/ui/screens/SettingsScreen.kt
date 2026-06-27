package cn.moncn.sing_box_windows.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import cn.moncn.sing_box_windows.config.AppSettings
import cn.moncn.sing_box_windows.config.AppSettingsDefaults
import cn.moncn.sing_box_windows.config.ConfigRepository
import cn.moncn.sing_box_windows.config.SettingsRepository
import cn.moncn.sing_box_windows.ui.MessageDialogState
import cn.moncn.sing_box_windows.ui.MessageTone
import cn.moncn.sing_box_windows.ui.components.NeoCard
import cn.moncn.sing_box_windows.ui.components.NeoDivider
import cn.moncn.sing_box_windows.ui.components.PrimaryButton
import cn.moncn.sing_box_windows.ui.components.Section
import cn.moncn.sing_box_windows.ui.components.UpdateSection
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * 现代化设置页面
 * 全新设计的配置界面
 */
@Composable
fun SettingsScreen(
    onShowMessage: (MessageDialogState) -> Unit
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    // 设置状态
    var currentSettings by remember { mutableStateOf(AppSettings()) }
    var dnsStrategy by remember { mutableStateOf(AppSettingsDefaults.DNS_STRATEGY) }
    var dnsCacheEnabled by remember { mutableStateOf(AppSettingsDefaults.DNS_CACHE_ENABLED) }
    var dnsIndependentCache by remember { mutableStateOf(AppSettingsDefaults.DNS_INDEPENDENT_CACHE) }
    var tunMtuInput by remember { mutableStateOf(AppSettingsDefaults.TUN_MTU.toString()) }
    var tunAutoRoute by remember { mutableStateOf(AppSettingsDefaults.TUN_AUTO_ROUTE) }
    var tunStrictRoute by remember { mutableStateOf(AppSettingsDefaults.TUN_STRICT_ROUTE) }
    var httpProxyEnabled by remember { mutableStateOf(AppSettingsDefaults.HTTP_PROXY_ENABLED) }
    var isSaving by remember { mutableStateOf(false) }

    // 加载设置
    LaunchedEffect(Unit) {
        val loaded = withContext(Dispatchers.IO) { SettingsRepository.load(context) }
        currentSettings = loaded
        dnsStrategy = loaded.dnsStrategy
        dnsCacheEnabled = loaded.dnsCacheEnabled
        dnsIndependentCache = loaded.dnsIndependentCache
        tunMtuInput = loaded.tunMtu.toString()
        tunAutoRoute = loaded.tunAutoRoute
        tunStrictRoute = loaded.tunStrictRoute
        httpProxyEnabled = loaded.httpProxyEnabled
    }

    // 保存设置
    fun saveSettings() {
        if (isSaving) return
        val normalizedMtu = tunMtuInput.toIntOrNull()?.coerceIn(1280, 9000) ?: currentSettings.tunMtu

        val newSettings = currentSettings.copy(
            dnsStrategy = dnsStrategy,
            dnsCacheEnabled = dnsCacheEnabled,
            dnsIndependentCache = dnsIndependentCache,
            tunMtu = normalizedMtu,
            tunAutoRoute = tunAutoRoute,
            tunStrictRoute = tunStrictRoute,
            httpProxyEnabled = httpProxyEnabled
        )

        isSaving = true
        scope.launch {
            withContext(Dispatchers.IO) {
                SettingsRepository.save(context, newSettings)
                ConfigRepository.applySettings(context, newSettings)
            }
            currentSettings = newSettings
            tunMtuInput = newSettings.tunMtu.toString()
            isSaving = false
            onShowMessage(
                MessageDialogState(
                    title = "设置已保存",
                    message = "请断开后重新连接以应用新配置。",
                    tone = MessageTone.Success
                )
            )
        }
    }

    val scheme = MaterialTheme.colorScheme

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 20.dp, vertical = 24.dp),
        verticalArrangement = Arrangement.spacedBy(24.dp)
    ) {
        // ==================== 页面标题 ====================
        item {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(
                    text = "设置",
                    style = MaterialTheme.typography.headlineLarge,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "配置 VPN 内核参数",
                    style = MaterialTheme.typography.bodyMedium,
                    color = scheme.onSurfaceVariant
                )
            }
        }

        // ==================== 应用更新 ====================
        item {
            UpdateSection()
        }

        // ==================== DNS 设置 ====================
        item {
            Section(title = { Text("DNS", style = MaterialTheme.typography.titleMedium) }) {
                NeoCard(modifier = Modifier.fillMaxWidth()) {
                    Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
                        SettingDropdownRow(
                            title = "解析策略",
                            description = "控制 IPv4/IPv6 优先级",
                            options = DNS_STRATEGIES,
                            labels = DNS_STRATEGY_LABELS,
                            selected = dnsStrategy,
                            onSelected = { dnsStrategy = it }
                        )
                        NeoDivider()
                        SettingSwitchRow(
                            title = "启用缓存",
                            description = "缓存 DNS 解析结果",
                            checked = dnsCacheEnabled,
                            onCheckedChange = { dnsCacheEnabled = it }
                        )
                        NeoDivider()
                        SettingSwitchRow(
                            title = "独立缓存",
                            description = "各 DNS 服务器使用独立缓存",
                            checked = dnsIndependentCache,
                            onCheckedChange = { dnsIndependentCache = it }
                        )
                    }
                }
            }
        }

        // ==================== TUN/VPN 设置 ====================
        item {
            Section(title = { Text("TUN / VPN", style = MaterialTheme.typography.titleMedium) }) {
                NeoCard(modifier = Modifier.fillMaxWidth()) {
                    Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
                        SettingNumberField(
                            title = "MTU",
                            description = "推荐范围 1280-9000",
                            value = tunMtuInput,
                            onValueChange = { tunMtuInput = it },
                            placeholder = "9000"
                        )
                        NeoDivider()
                        SettingSwitchRow(
                            title = "自动路由",
                            description = "将默认路由指向 TUN",
                            checked = tunAutoRoute,
                            onCheckedChange = { tunAutoRoute = it }
                        )
                        NeoDivider()
                        SettingSwitchRow(
                            title = "严格路由",
                            description = "限制非路由流量",
                            checked = tunStrictRoute,
                            onCheckedChange = { tunStrictRoute = it }
                        )
                        NeoDivider()
                        SettingSwitchRow(
                            title = "系统 HTTP 代理",
                            description = "自动配置系统代理",
                            checked = httpProxyEnabled,
                            onCheckedChange = { httpProxyEnabled = it }
                        )
                    }
                }
            }
        }

        // ==================== 保存按钮 ====================
        item {
            PrimaryButton(
                text = if (isSaving) "保存中..." else "保存并应用",
                onClick = { saveSettings() },
                modifier = Modifier.fillMaxWidth(),
                enabled = !isSaving
            )
        }

        // 底部留白
        item {
            Spacer(modifier = Modifier.height(16.dp))
        }
    }
}

// ==================== 设置项组件 ====================

/**
 * 开关设置行
 */
@Composable
private fun SettingSwitchRow(
    title: String,
    description: String,
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit
) {
    val scheme = MaterialTheme.colorScheme

    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Text(
                text = title,
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = FontWeight.Medium
            )
            Text(
                text = description,
                style = MaterialTheme.typography.labelMedium,
                color = scheme.onSurfaceVariant
            )
        }
        Switch(checked = checked, onCheckedChange = onCheckedChange)
    }
}

/**
 * 下拉选择设置行
 */
@Composable
private fun SettingDropdownRow(
    title: String,
    description: String,
    options: List<String>,
    labels: Map<String, String>,
    selected: String,
    onSelected: (String) -> Unit
) {
    val scheme = MaterialTheme.colorScheme
    var expanded by remember { mutableStateOf(false) }
    val label = labels[selected] ?: selected

    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Text(
                text = title,
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = FontWeight.Medium
            )
            Text(
                text = description,
                style = MaterialTheme.typography.labelMedium,
                color = scheme.onSurfaceVariant
            )
        }
        Box {
            TextButton(onClick = { expanded = true }) {
                Text(label)
            }
            DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
                options.forEach { option ->
                    DropdownMenuItem(
                        text = { Text(labels[option] ?: option) },
                        onClick = {
                            onSelected(option)
                            expanded = false
                        }
                    )
                }
            }
        }
    }
}

/**
 * 数字输入设置
 */
@Composable
private fun SettingNumberField(
    title: String,
    description: String,
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String
) {
    val scheme = MaterialTheme.colorScheme

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            text = title,
            style = MaterialTheme.typography.bodyLarge,
            fontWeight = FontWeight.Medium
        )
        Text(
            text = description,
            style = MaterialTheme.typography.labelMedium,
            color = scheme.onSurfaceVariant
        )
        OutlinedTextField(
            value = value,
            onValueChange = { input -> onValueChange(input.filter { it.isDigit() }) },
            placeholder = { Text(placeholder) },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number)
        )
    }
}

/**
 * 文本输入设置
 */
@Composable
private fun SettingTextField(
    title: String,
    description: String,
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String
) {
    val scheme = MaterialTheme.colorScheme

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            text = title,
            style = MaterialTheme.typography.bodyLarge,
            fontWeight = FontWeight.Medium
        )
        Text(
            text = description,
            style = MaterialTheme.typography.labelMedium,
            color = scheme.onSurfaceVariant
        )
        OutlinedTextField(
            value = value,
            onValueChange = onValueChange,
            placeholder = { Text(placeholder) },
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )
    }
}

// ==================== 常量定义 ====================

private val DNS_STRATEGIES = listOf("prefer_ipv4", "prefer_ipv6", "ipv4_only", "ipv6_only")
private val DNS_STRATEGY_LABELS = mapOf(
    "prefer_ipv4" to "优先 IPv4",
    "prefer_ipv6" to "优先 IPv6",
    "ipv4_only" to "仅 IPv4",
    "ipv6_only" to "仅 IPv6"
)
