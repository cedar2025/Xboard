package cn.moncn.sing_box_windows.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Check
import androidx.compose.material.icons.rounded.KeyboardArrowDown
import androidx.compose.material.icons.rounded.Speed
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import cn.moncn.sing_box_windows.core.OutboundGroupModel
import cn.moncn.sing_box_windows.core.OutboundItemModel
import cn.moncn.sing_box_windows.ui.components.NeoCard
import cn.moncn.sing_box_windows.ui.components.NeoDivider
import cn.moncn.sing_box_windows.ui.components.ShapeSmall
import cn.moncn.sing_box_windows.ui.components.ShapeTiny
import cn.moncn.sing_box_windows.ui.components.StatusBadge
import cn.moncn.sing_box_windows.ui.components.StatusBadgeSize
import cn.moncn.sing_box_windows.ui.theme.LatencyHigh
import cn.moncn.sing_box_windows.ui.theme.LatencyLow
import cn.moncn.sing_box_windows.ui.theme.LatencyMedium
import cn.moncn.sing_box_windows.ui.theme.LatencyUnknown

/**
 * 现代化节点页面
 * 全新设计的节点选择界面
 */
@Composable
fun NodesScreen(
    groups: List<OutboundGroupModel>,
    onSelectNode: (String, String) -> Unit,
    onTestNode: (String) -> Unit
) {
    val expandedStates = remember { mutableStateMapOf<String, Boolean>() }

    // 默认展开第一个分组
    if (expandedStates.isEmpty() && groups.isNotEmpty()) {
        expandedStates[groups.first().tag] = true
    }

    val scheme = MaterialTheme.colorScheme

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
                    text = "节点选择",
                    style = MaterialTheme.typography.headlineLarge,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "选择合适的代理节点",
                    style = MaterialTheme.typography.bodyMedium,
                    color = scheme.onSurfaceVariant
                )
            }
        }

        // ==================== 空状态 ====================
        if (groups.isEmpty()) {
            item {
                Box(
                    modifier = Modifier.fillMaxWidth(),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "暂无节点",
                        style = MaterialTheme.typography.titleMedium,
                        color = scheme.onSurfaceVariant
                    )
                }
            }
        }

        // ==================== 节点分组列表 ====================
        items(groups) { group ->
            val isExpanded = expandedStates[group.tag] ?: false
            NodeGroupCard(
                group = group,
                isExpanded = isExpanded,
                onToggle = { expandedStates[group.tag] = !isExpanded },
                onSelectNode = { nodeTag -> onSelectNode(group.tag, nodeTag) },
                onTestNode = onTestNode
            )
        }

        // 底部留白
        item {
            Spacer(modifier = Modifier.height(16.dp))
        }
    }
}

// ==================== 节点分组卡片 ====================

@Composable
fun NodeGroupCard(
    group: OutboundGroupModel,
    isExpanded: Boolean,
    onToggle: () -> Unit,
    onSelectNode: (String) -> Unit,
    onTestNode: (String) -> Unit
) {
    val scheme = MaterialTheme.colorScheme

    // 展开/收起动画
    val rotationAngle by animateFloatAsState(
        targetValue = if (isExpanded) 180f else 0f,
        label = "rotation"
    )

    NeoCard(
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // ==================== 分组头部 ====================
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { onToggle() }
                    .padding(vertical = 4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // 分组信息
                Column(
                    modifier = Modifier.weight(1f),
                    verticalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = group.tag,
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.SemiBold
                        )
                        // 类型标签
                        TypeBadge(type = group.type)
                    }
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "已选: ${group.selected}",
                            style = MaterialTheme.typography.labelMedium,
                            color = scheme.onSurfaceVariant
                        )
                        // 节点数量徽章
                        StatusBadge(
                            text = "${group.items.size} 个节点",
                            color = scheme.primary,
                            size = StatusBadgeSize.Small
                        )
                    }
                }

                // 展开按钮
                IconButton(onClick = onToggle) {
                    Icon(
                        imageVector = Icons.Rounded.KeyboardArrowDown,
                        contentDescription = if (isExpanded) "收起" else "展开",
                        modifier = Modifier.rotate(rotationAngle)
                    )
                }
            }

            // ==================== 节点列表 ====================
            AnimatedVisibility(
                visible = isExpanded,
                enter = expandVertically(
                    animationSpec = androidx.compose.animation.core.spring(
                        dampingRatio = 0.7f,
                        stiffness = 300f
                    )
                ) + fadeIn(),
                exit = shrinkVertically(
                    animationSpec = androidx.compose.animation.core.spring(
                        dampingRatio = 0.7f,
                        stiffness = 300f
                    )
                ) + fadeOut()
            ) {
                Column(
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    NeoDivider()
                    group.items.forEach { node ->
                        NodeItemRow(
                            node = node,
                            isSelected = group.selected == node.tag,
                            selectable = group.selectable,
                            onSelect = { onSelectNode(node.tag) },
                            onTest = { onTestNode(node.tag) }
                        )
                    }
                }
            }
        }
    }
}

// ==================== 类型标签 ====================

@Composable
private fun TypeBadge(type: String) {
    val scheme = MaterialTheme.colorScheme

    Surface(
        shape = ShapeTiny,
        color = scheme.secondaryContainer.copy(alpha = 0.6f)
    ) {
        Text(
            text = type,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
            style = MaterialTheme.typography.labelSmall,
            color = scheme.onSecondaryContainer,
            fontWeight = FontWeight.Medium
        )
    }
}

// ==================== 节点项目行 ====================

@Composable
fun NodeItemRow(
    node: OutboundItemModel,
    isSelected: Boolean,
    selectable: Boolean,
    onSelect: () -> Unit,
    onTest: () -> Unit
) {
    val scheme = MaterialTheme.colorScheme
    val isDark = androidx.compose.foundation.isSystemInDarkTheme()

    // 根据延迟值确定颜色
    val delayMs = node.delayMs
    val latencyInfo = when {
        delayMs == null || delayMs <= 0 -> LatencyInfo.Unknown
        delayMs < 150 -> LatencyInfo.Low(delayMs.toLong())
        delayMs < 500 -> LatencyInfo.Medium(delayMs.toLong())
        else -> LatencyInfo.High(delayMs.toLong())
    }

    val delayColor = when (latencyInfo) {
        is LatencyInfo.Unknown -> LatencyUnknown
        is LatencyInfo.Low -> LatencyLow
        is LatencyInfo.Medium -> LatencyMedium
        is LatencyInfo.High -> LatencyHigh
        else -> LatencyUnknown
    }

    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .then(
                if (selectable) {
                    Modifier.clickable(enabled = !isSelected) { onSelect() }
                } else {
                    Modifier
                }
            ),
        shape = ShapeSmall,
        color = if (isSelected) {
            scheme.primaryContainer.copy(alpha = 0.5f)
        } else {
            Color.Transparent
        },
        border = if (isSelected) {
            null
        } else {
            androidx.compose.foundation.BorderStroke(
                width = 0.5.dp,
                color = if (isDark) {
                    scheme.outline.copy(alpha = 0.2f)
                } else {
                    scheme.outline.copy(alpha = 0.4f)
                }
            )
        }
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            // 节点信息
            Row(
                modifier = Modifier.weight(1f),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // 选中指示器
                if (isSelected) {
                    Box(
                        modifier = Modifier
                            .size(8.dp)
                            .background(scheme.primary, CircleShape)
                    )
                } else {
                    Spacer(modifier = Modifier.size(8.dp))
                }

                Column(
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    // 节点名称
                    Text(
                        text = node.tag,
                        style = MaterialTheme.typography.bodyLarge,
                        fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Normal,
                        color = if (isSelected) {
                            scheme.onPrimaryContainer
                        } else {
                            scheme.onSurface
                        },
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )

                    // 延迟信息
                    LatencyDisplay(
                        latencyInfo = latencyInfo,
                        delayColor = delayColor
                    )
                }
            }

            // 操作按钮
            Row(
                horizontalArrangement = Arrangement.spacedBy(4.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // 测试延迟按钮
                IconButton(
                    onClick = onTest,
                    enabled = !node.isTesting
                ) {
                    Icon(
                        imageVector = Icons.Rounded.Speed,
                        contentDescription = "测试延迟",
                        tint = if (node.isTesting) {
                            scheme.onSurfaceVariant
                        } else {
                            scheme.primary
                        }
                    )
                }

                // 选择按钮
                if (selectable && !isSelected) {
                    IconButton(onClick = onSelect) {
                        Icon(
                            imageVector = Icons.Rounded.Check,
                            contentDescription = "选择",
                            tint = scheme.onSurfaceVariant
                        )
                    }
                }

                // 已选择指示
                if (isSelected) {
                    Icon(
                        imageVector = Icons.Rounded.Check,
                        contentDescription = "已选择",
                        tint = scheme.primary
                    )
                }
            }
        }
    }
}

// ==================== 延迟信息显示 ====================

@Composable
private fun LatencyDisplay(
    latencyInfo: LatencyInfo,
    delayColor: Color
) {
    val scheme = MaterialTheme.colorScheme

    when (latencyInfo) {
        is LatencyInfo.Unknown -> {
            Text(
                text = "点击测速",
                style = MaterialTheme.typography.labelSmall,
                color = scheme.onSurfaceVariant
            )
        }
        else -> {
            val ms = (latencyInfo as? LatencyInfo.Known)?.ms ?: 0
            val labelText = when (latencyInfo) {
                is LatencyInfo.Low -> "优秀"
                is LatencyInfo.Medium -> "一般"
                is LatencyInfo.High -> "较慢"
                else -> ""
            }

            Row(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // 延迟数值
                Text(
                    text = "${ms}ms",
                    style = MaterialTheme.typography.labelSmall,
                    color = delayColor,
                    fontWeight = FontWeight.Medium
                )

                // 状态标签
                if (labelText.isNotEmpty()) {
                    Surface(
                        shape = ShapeTiny,
                        color = delayColor.copy(alpha = 0.15f)
                    ) {
                        Text(
                            text = labelText,
                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp),
                            style = MaterialTheme.typography.labelSmall,
                            color = delayColor
                        )
                    }
                }
            }
        }
    }
}

// ==================== 延迟信息密封类 ====================

/**
 * 延迟信息
 */
private sealed class LatencyInfo {
    /** 未知延迟 */
    data object Unknown : LatencyInfo()

    /** 低延迟 (<150ms) */
    data class Low(override val ms: Long) : Known(ms)

    /** 中等延迟 (<500ms) */
    data class Medium(override val ms: Long) : Known(ms)

    /** 高延迟 (>=500ms) */
    data class High(override val ms: Long) : Known(ms)

    /** 已知延迟基类 */
    abstract class Known(open val ms: Long) : LatencyInfo()
}
