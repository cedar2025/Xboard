package cn.moncn.sing_box_windows.ui.screens

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.AnimationSpec
import androidx.compose.animation.core.SpringSpec
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.PowerSettingsNew
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import cn.moncn.sing_box_windows.core.ClashModeManager
import cn.moncn.sing_box_windows.core.CoreStatus
import cn.moncn.sing_box_windows.core.OutboundGroupModel
import cn.moncn.sing_box_windows.ui.components.GradientMetricTile
import cn.moncn.sing_box_windows.ui.components.MetricTile
import cn.moncn.sing_box_windows.ui.components.NeoCard
import cn.moncn.sing_box_windows.ui.components.NeoDivider
import cn.moncn.sing_box_windows.ui.components.Section
import cn.moncn.sing_box_windows.ui.components.ShapeMedium
import cn.moncn.sing_box_windows.ui.theme.ConnectedColor
import cn.moncn.sing_box_windows.ui.theme.ConnectingColor
import cn.moncn.sing_box_windows.ui.theme.ErrorColor
import cn.moncn.sing_box_windows.ui.theme.IdleColor
import cn.moncn.sing_box_windows.ui.theme.PrimaryGradient
import cn.moncn.sing_box_windows.ui.theme.SuccessGradient
import cn.moncn.sing_box_windows.vpn.VpnState

/**
 * 现代化首页
 * 全新设计的连接控制中心
 */
@Composable
fun HomeScreen(
    state: VpnState,
    statusText: String,
    statusColor: Color,
    actionText: String,
    coreStatus: CoreStatus?,
    coreVersion: String?,
    groups: List<OutboundGroupModel>,
    currentMode: ClashModeManager.ClashMode?,
    isModeSupported: Boolean,
    onConnect: () -> Unit,
    onDisconnect: () -> Unit,
    onSwitchMode: (ClashModeManager.ClashMode) -> Unit
) {
    val scrollState = rememberScrollState()
    val scheme = MaterialTheme.colorScheme
    val trafficAvailable = coreStatus?.trafficAvailable == true

    // 连接按钮动画
    val buttonScale = remember { Animatable(1f) }
    LaunchedEffect(state) {
        buttonScale.animateTo(
            targetValue = 0.95f,
            animationSpec = spring(
                dampingRatio = 0.7f,
                stiffness = 300f
            )
        )
        buttonScale.animateTo(
            targetValue = 1f,
            animationSpec = spring(
                dampingRatio = 0.7f,
                stiffness = 300f
            )
        )
    }

    val glowAlpha by animateFloatAsState(
        targetValue = when (state) {
            VpnState.CONNECTED -> 0.6f
            VpnState.CONNECTING -> 0.4f
            VpnState.ERROR -> 0.3f
            VpnState.IDLE -> 0.2f
        },
        label = "glowAlpha",
        animationSpec = SpringSpec(
            dampingRatio = 0.7f,
            stiffness = 300f
        )
    )

    val primaryGroup = findPrimaryGroup(groups)
    val modeLabel = currentMode?.displayName ?: "---"

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(scrollState)
            .padding(horizontal = 20.dp, vertical = 24.dp),
        verticalArrangement = Arrangement.spacedBy(24.dp)
    ) {
        // ==================== 页面标题 ====================
        PageHeader(
            title = "控制中心",
            subtitle = when (state) {
                VpnState.CONNECTED -> "已连接 · 安全加密中"
                VpnState.CONNECTING -> "正在建立安全连接"
                VpnState.ERROR -> "连接出现错误"
                VpnState.IDLE -> "一键开启隐私保护"
            }
        )

        // ==================== 连接卡片 ====================
        NeoCard(
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(
                modifier = Modifier.fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(20.dp)
            ) {
                // 状态标题行
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(
                            text = "连接状态",
                            style = MaterialTheme.typography.labelMedium,
                            color = scheme.onSurfaceVariant
                        )
                        Text(
                            text = statusText,
                            style = MaterialTheme.typography.headlineSmall,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                    StatusIndicator(state = state)
                }

                // ==================== 连接按钮 ====================
                Box(
                    modifier = Modifier.fillMaxWidth(),
                    contentAlignment = Alignment.Center
                ) {
                    // 发光背景
                    Box(
                        modifier = Modifier
                            .size(160.dp)
                            .scale(buttonScale.value)
                            .background(
                                brush = Brush.radialGradient(
                                    colors = listOf(
                                        statusColor.copy(alpha = glowAlpha),
                                        Color.Transparent
                                    )
                                ),
                                shape = CircleShape
                            )
                    )

                    // 连接按钮
                    ConnectionButton(
                        state = state,
                        statusColor = statusColor,
                        onClick = {
                            if (state == VpnState.CONNECTED || state == VpnState.CONNECTING) {
                                onDisconnect()
                            } else {
                                onConnect()
                            }
                        }
                    )
                }

                // 提示文字
                Text(
                    text = actionText,
                    style = MaterialTheme.typography.labelLarge,
                    color = scheme.onSurfaceVariant
                )
            }
        }

        // ==================== 模式切换器 ====================
        if (state == VpnState.CONNECTED && isModeSupported) {
            ModeSelector(
                currentMode = currentMode,
                onModeSelected = onSwitchMode
            )
        }

        // ==================== 实时速度 ====================
        Section(title = { Text("实时速度", style = MaterialTheme.typography.titleMedium) }) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                GradientMetricTile(
                    label = "上传",
                    value = if (trafficAvailable) formatSpeed(coreStatus?.uplinkBytes ?: 0) else "---",
                    modifier = Modifier.weight(1f),
                    gradient = PrimaryGradient
                )
                GradientMetricTile(
                    label = "下载",
                    value = if (trafficAvailable) formatSpeed(coreStatus?.downlinkBytes ?: 0) else "---",
                    modifier = Modifier.weight(1f),
                    gradient = SuccessGradient
                )
            }
        }

        // ==================== 统计数据 ====================
        Section(title = { Text("统计数据", style = MaterialTheme.typography.titleMedium) }) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                MetricTile(
                    label = "总上传",
                    value = if (trafficAvailable) formatBytes(coreStatus?.uplinkTotalBytes ?: 0) else "---",
                    modifier = Modifier.weight(1f)
                )
                MetricTile(
                    label = "总下载",
                    value = if (trafficAvailable) formatBytes(coreStatus?.downlinkTotalBytes ?: 0) else "---",
                    modifier = Modifier.weight(1f)
                )
            }
            Spacer(modifier = Modifier.height(12.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                MetricTile(
                    label = "运行模式",
                    value = modeLabel,
                    modifier = Modifier.weight(1f)
                )
                MetricTile(
                    label = "活跃连接",
                    value = formatCount(coreStatus?.connectionsIn),
                    modifier = Modifier.weight(1f)
                )
            }
        }

        // ==================== 连接信息 ====================
        Section(title = { Text("连接信息", style = MaterialTheme.typography.titleMedium) }) {
            NeoCard(
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    ConnectionInfoRow(
                        label = "当前出口",
                        value = primaryGroup?.selected ?: "---"
                    )
                    NeoDivider()
                    ConnectionInfoRow(
                        label = "内存占用",
                        value = formatMemory(coreStatus?.memoryBytes ?: 0)
                    )
                    NeoDivider()
                    ConnectionInfoRow(
                        label = "内核版本",
                        value = coreVersion ?: "未知"
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))
    }
}

// ==================== 页面标题 ====================

@Composable
private fun PageHeader(
    title: String,
    subtitle: String
) {
    val scheme = MaterialTheme.colorScheme

    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(
            text = title,
            style = MaterialTheme.typography.headlineLarge,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = subtitle,
            style = MaterialTheme.typography.bodyMedium,
            color = scheme.onSurfaceVariant
        )
    }
}

// ==================== 状态指示器 ====================

@Composable
private fun StatusIndicator(
    state: VpnState
) {
    val scheme = MaterialTheme.colorScheme
    val indicatorColor = when (state) {
        VpnState.CONNECTED -> ConnectedColor
        VpnState.CONNECTING -> ConnectingColor
        VpnState.ERROR -> ErrorColor
        VpnState.IDLE -> IdleColor
    }

    Row(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(8.dp)
                .background(indicatorColor, CircleShape)
        )
        Text(
            text = when (state) {
                VpnState.CONNECTED -> "安全"
                VpnState.CONNECTING -> "连接中"
                VpnState.ERROR -> "异常"
                VpnState.IDLE -> "就绪"
            },
            style = MaterialTheme.typography.labelMedium,
            color = indicatorColor,
            fontWeight = FontWeight.Medium
        )
    }
}

// ==================== 连接按钮 ====================

@Composable
private fun ConnectionButton(
    state: VpnState,
    statusColor: Color,
    onClick: () -> Unit
) {
    val scheme = MaterialTheme.colorScheme
    val isDark = androidx.compose.foundation.isSystemInDarkTheme()

    val buttonGradient = when (state) {
        VpnState.CONNECTED -> SuccessGradient
        VpnState.CONNECTING -> listOf(
            Color(0xFFF59E0B),
            Color(0xFFFBBF24)
        )
        VpnState.ERROR -> listOf(
            Color(0xFFEF4444),
            Color(0xFFF87171)
        )
        VpnState.IDLE -> PrimaryGradient
    }

    Box(
        modifier = Modifier
            .size(120.dp)
            .clip(CircleShape)
            .background(
                brush = Brush.radialGradient(buttonGradient)
            )
            .clickable(onClick = onClick)
            .then(
                if (state == VpnState.CONNECTED) {
                    Modifier
                } else {
                    Modifier.border(
                        width = 3.dp,
                        color = if (isDark) {
                            Color.White.copy(alpha = 0.1f)
                        } else {
                            Color.White.copy(alpha = 0.3f)
                        },
                        shape = CircleShape
                    )
                }
            ),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = Icons.Rounded.PowerSettingsNew,
            contentDescription = "连接/断开",
            modifier = Modifier.size(48.dp),
            tint = Color.White
        )
    }
}

// ==================== 模式选择器 ====================

@Composable
private fun ModeSelector(
    currentMode: ClashModeManager.ClashMode?,
    onModeSelected: (ClashModeManager.ClashMode) -> Unit
) {
    val scheme = MaterialTheme.colorScheme
    val modes = listOf(
        ClashModeManager.ClashMode.Rule,
        ClashModeManager.ClashMode.Global,
        ClashModeManager.ClashMode.Direct
    )

    val modeLabels = mapOf(
        ClashModeManager.ClashMode.Rule to "规则",
        ClashModeManager.ClashMode.Global to "全局",
        ClashModeManager.ClashMode.Direct to "直连"
    )

    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(
            text = "代理模式",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.SemiBold
        )

        NeoCard(
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                modes.forEach { mode ->
                    val isSelected = currentMode == mode
                    val isDark = androidx.compose.foundation.isSystemInDarkTheme()

                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(ShapeMedium)
                            .background(
                                if (isSelected) {
                                    scheme.primaryContainer
                                } else {
                                    Color.Transparent
                                }
                            )
                            .clickable { onModeSelected(mode) }
                            .padding(vertical = 14.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = modeLabels[mode] ?: mode.displayName,
                            style = MaterialTheme.typography.labelLarge,
                            color = if (isSelected) {
                                scheme.onPrimaryContainer
                            } else {
                                scheme.onSurfaceVariant
                            },
                            fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Medium
                        )
                    }
                }
            }
        }
    }
}

// ==================== 连接信息行 ====================

@Composable
private fun ConnectionInfoRow(
    label: String,
    value: String
) {
    val scheme = MaterialTheme.colorScheme

    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = scheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            color = scheme.onSurface,
            fontWeight = FontWeight.Medium
        )
    }
}

// ==================== 工具函数 ====================

fun formatSpeed(bytes: Long): String {
    if (bytes < 1024) return "$bytes B/s"
    if (bytes < 1024 * 1024) return String.format("%.1f KB/s", bytes / 1024f)
    return String.format("%.1f MB/s", bytes / (1024f * 1024f))
}

fun formatBytes(bytes: Long): String {
    if (bytes < 1024) return "$bytes B"
    if (bytes < 1024 * 1024) return String.format("%.1f KB", bytes / 1024f)
    if (bytes < 1024 * 1024 * 1024) return String.format("%.1f MB", bytes / (1024f * 1024f))
    return String.format("%.1f GB", bytes / (1024f * 1024f * 1024f))
}

fun formatMemory(bytes: Long): String {
    if (bytes < 1024) return "$bytes B"
    if (bytes < 1024 * 1024) return String.format("%.1f KB", bytes / 1024f)
    return String.format("%.1f MB", bytes / (1024f * 1024f))
}

private fun formatCount(count: Int?): String {
    return count?.let { it.coerceAtLeast(0).toString() } ?: "---"
}

private fun findPrimaryGroup(groups: List<OutboundGroupModel>): OutboundGroupModel? {
    if (groups.isEmpty()) return null
    val preferredTags = setOf("global", "proxy")
    val preferred = groups.firstOrNull { preferredTags.contains(it.tag.lowercase()) }
    if (preferred != null) return preferred
    return groups.firstOrNull { it.selectable } ?: groups.first()
}
