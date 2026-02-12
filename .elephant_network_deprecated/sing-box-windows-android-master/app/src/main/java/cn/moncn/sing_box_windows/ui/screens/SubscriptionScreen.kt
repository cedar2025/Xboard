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
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Add
import androidx.compose.material.icons.rounded.CheckCircle
import androidx.compose.material.icons.rounded.Delete
import androidx.compose.material.icons.rounded.Edit
import androidx.compose.material.icons.rounded.Refresh
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import cn.moncn.sing_box_windows.config.SubscriptionItem
import cn.moncn.sing_box_windows.config.SubscriptionState
import cn.moncn.sing_box_windows.ui.MessageDialogState
import cn.moncn.sing_box_windows.ui.MessageTone
import cn.moncn.sing_box_windows.ui.components.NeoCard
import cn.moncn.sing_box_windows.ui.components.ShapeMedium
import cn.moncn.sing_box_windows.ui.components.ShapeSmall
import cn.moncn.sing_box_windows.ui.components.ShapeTiny
import cn.moncn.sing_box_windows.ui.components.StatusBadge
import cn.moncn.sing_box_windows.ui.components.StatusBadgeSize
import java.text.SimpleDateFormat
import java.util.Date

/**
 * 现代化订阅管理页面
 * 全新设计的订阅管理界面
 */
@Composable
fun SubscriptionScreen(
    subscriptions: SubscriptionState,
    updatingId: String?,
    onAddSubscription: (String, String) -> Unit,
    onImportNodes: (String, String) -> Unit,
    onEditSubscription: (String, String, String) -> Unit,
    onRemoveSubscription: (String) -> Unit,
    onSelectSubscription: (String) -> Unit,
    onUpdateSubscription: (String) -> Unit,
    onShowMessage: (MessageDialogState) -> Unit
) {
    val scheme = MaterialTheme.colorScheme
    var showAddDialog by remember { mutableStateOf(false) }
    var editingItem by remember { mutableStateOf<SubscriptionItem?>(null) }

    Box(modifier = Modifier.fillMaxSize()) {
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
                        text = "订阅管理",
                        style = MaterialTheme.typography.headlineLarge,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = "添加和管理订阅源",
                        style = MaterialTheme.typography.bodyMedium,
                        color = scheme.onSurfaceVariant
                    )
                }
            }

            // ==================== 空状态 ====================
            if (subscriptions.items.isEmpty()) {
                item {
                    NeoCard(
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(24.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Text(
                                text = "暂无订阅",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Medium
                            )
                            Text(
                                text = "点击右下角按钮添加订阅源",
                                style = MaterialTheme.typography.bodyMedium,
                                color = scheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }

            // ==================== 订阅列表 ====================
            items(subscriptions.items, key = { it.id }) { item ->
                SubscriptionCard(
                    item = item,
                    isSelected = subscriptions.selectedId == item.id,
                    isUpdating = updatingId == item.id,
                    onSelect = { onSelectSubscription(item.id) },
                    onEdit = { editingItem = item },
                    onDelete = { onRemoveSubscription(item.id) },
                    onUpdate = { onUpdateSubscription(item.id) },
                    onShowError = { msg ->
                        onShowMessage(
                            MessageDialogState(
                                "错误详情",
                                msg,
                                MessageTone.Error
                            )
                        )
                    }
                )
            }

            // 底部留白（为悬浮按钮留出空间）
            item {
                Spacer(modifier = Modifier.height(100.dp))
            }
        }

        // ==================== 悬浮添加按钮 ====================
        androidx.compose.material3.FloatingActionButton(
            onClick = { showAddDialog = true },
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .padding(24.dp),
            containerColor = scheme.primary,
            contentColor = scheme.onPrimary
        ) {
            Icon(Icons.Rounded.Add, contentDescription = "添加订阅")
        }
    }

    // ==================== 添加订阅对话框 ====================
    if (showAddDialog) {
        var name by remember { mutableStateOf("") }
        var url by remember { mutableStateOf("") }
        var content by remember { mutableStateOf("") }
        var inputMode by remember { mutableStateOf(AddSubscriptionInputMode.Url) }

        AddSubscriptionDialog(
            name = name,
            url = url,
            content = content,
            inputMode = inputMode,
            onNameChange = { name = it },
            onUrlChange = { url = it },
            onContentChange = { content = it },
            onModeChange = { inputMode = it },
            onDismiss = { showAddDialog = false },
            onConfirm = {
                when (inputMode) {
                    AddSubscriptionInputMode.Url -> {
                        if (url.isNotBlank()) {
                            onAddSubscription(name, url)
                            showAddDialog = false
                        }
                    }
                    AddSubscriptionInputMode.NodeList -> {
                        if (content.isNotBlank()) {
                            onImportNodes(name, content)
                            showAddDialog = false
                        }
                    }
                }
            }
        )
    }

    // ==================== 编辑订阅对话框 ====================
    editingItem?.let { item ->
        var name by remember { mutableStateOf(item.name) }
        var url by remember { mutableStateOf(item.url) }

        EditSubscriptionDialog(
            name = name,
            url = url,
            isLocal = item.isLocal,
            onNameChange = { name = it },
            onUrlChange = { url = it },
            onDismiss = { editingItem = null },
            onConfirm = {
                if (url.isNotBlank()) {
                    onEditSubscription(item.id, name, url)
                    editingItem = null
                }
            }
        )
    }
}

// ==================== 订阅卡片 ====================

@Composable
fun SubscriptionCard(
    item: SubscriptionItem,
    isSelected: Boolean,
    isUpdating: Boolean,
    onSelect: () -> Unit,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
    onUpdate: () -> Unit,
    onShowError: (String) -> Unit
) {
    val scheme = MaterialTheme.colorScheme

    NeoCard(
        modifier = Modifier.fillMaxWidth(),
        onClick = if (!isSelected) onSelect else null,
        containerColor = if (isSelected) {
            scheme.primaryContainer.copy(alpha = 0.4f)
        } else {
            scheme.surface
        }
    ) {
        Column(
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // ==================== 卡片头部 ====================
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // 订阅信息
                Row(
                    modifier = Modifier.weight(1f),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // 选中指示器
                    if (isSelected) {
                        Icon(
                            imageVector = Icons.Rounded.CheckCircle,
                            contentDescription = "当前使用",
                            tint = scheme.primary,
                            modifier = Modifier.size(24.dp)
                        )
                    } else {
                        Spacer(modifier = Modifier.size(24.dp))
                    }

                    Column(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        // 订阅名称
                        Text(
                            text = if (item.name.isNotBlank()) item.name else "未命名订阅",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.SemiBold,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )

                        // 订阅地址
                        Text(
                            text = if (item.isLocal) "本地节点列表" else item.url,
                            style = MaterialTheme.typography.bodySmall,
                            color = scheme.onSurfaceVariant,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }

                // 徽章组
                Row(
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    if (item.isLocal) {
                        StatusBadge(
                            text = "本地",
                            color = scheme.tertiary,
                            size = StatusBadgeSize.Small
                        )
                    }
                    if (isSelected) {
                        StatusBadge(
                            text = "使用中",
                            color = scheme.primary,
                            size = StatusBadgeSize.Small
                        )
                    }
                }
            }

            // ==================== 状态信息 ====================
            StatusInfoSection(
                item = item,
                isUpdating = isUpdating,
                onShowError = onShowError
            )

            // ==================== 操作按钮 ====================
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // 左侧操作按钮
                Row(
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    // 更新按钮（仅远程订阅）
                    if (!item.isLocal) {
                        IconButton(
                            onClick = onUpdate,
                            enabled = !isUpdating
                        ) {
                            Icon(
                                imageVector = Icons.Rounded.Refresh,
                                contentDescription = "更新",
                                tint = if (isUpdating) {
                                    scheme.onSurfaceVariant
                                } else {
                                    scheme.primary
                                }
                            )
                        }
                    }

                    // 编辑按钮
                    IconButton(onClick = onEdit) {
                        Icon(
                            imageVector = Icons.Rounded.Edit,
                            contentDescription = "编辑"
                        )
                    }

                    // 删除按钮
                    IconButton(onClick = onDelete) {
                        Icon(
                            imageVector = Icons.Rounded.Delete,
                            contentDescription = "删除",
                            tint = scheme.error
                        )
                    }
                }

                // 启用按钮（未选中时显示）
                if (!isSelected && !isUpdating) {
                    FilledTonalButton(onClick = onSelect) {
                        Text("启用")
                    }
                }
            }
        }
    }
}

// ==================== 状态信息区块 ====================

@Composable
private fun StatusInfoSection(
    item: SubscriptionItem,
    isUpdating: Boolean,
    onShowError: (String) -> Unit
) {
    val scheme = MaterialTheme.colorScheme

    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            when {
                // 更新中状态
                isUpdating -> {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(12.dp)
                                .clip(CircleShape)
                                .background(scheme.tertiary)
                        )
                        Text(
                            text = if (item.isLocal) "正在应用..." else "正在同步...",
                            style = MaterialTheme.typography.labelMedium,
                            color = scheme.tertiary,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
                // 错误状态
                item.lastError != null -> {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Rounded.CheckCircle,
                            contentDescription = null,
                            tint = scheme.error,
                            modifier = Modifier.size(16.dp)
                        )
                        Text(
                            text = if (item.isLocal) {
                                "应用失败"
                            } else {
                                "同步失败"
                            },
                            style = MaterialTheme.typography.labelMedium,
                            color = scheme.error,
                            fontWeight = FontWeight.Medium,
                            modifier = Modifier.clickable { onShowError(item.lastError!!) }
                        )
                    }
                }
                // 本地订阅提示
                item.isLocal -> {
                    Text(
                        text = "本地订阅，不支持在线更新",
                        style = MaterialTheme.typography.labelMedium,
                        color = scheme.onSurfaceVariant
                    )
                }
                // 正常状态
                else -> {
                    val date = item.lastUpdatedAt?.let { Date(it) }
                    val dateStr = if (date != null) {
                        SimpleDateFormat.getDateTimeInstance().format(date)
                    } else {
                        "从未更新"
                    }
                    Text(
                        text = "更新于: $dateStr",
                        style = MaterialTheme.typography.labelMedium,
                        color = scheme.onSurfaceVariant
                    )
                }
            }
        }
    }
}

// ==================== 添加订阅对话框 ====================

@Composable
fun AddSubscriptionDialog(
    name: String,
    url: String,
    content: String,
    inputMode: AddSubscriptionInputMode,
    onNameChange: (String) -> Unit,
    onUrlChange: (String) -> Unit,
    onContentChange: (String) -> Unit,
    onModeChange: (AddSubscriptionInputMode) -> Unit,
    onDismiss: () -> Unit,
    onConfirm: () -> Unit
) {
    val scheme = MaterialTheme.colorScheme

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(
                text = "添加订阅",
                style = MaterialTheme.typography.titleLarge
            )
        },
        text = {
            Column(
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // 输入模式切换
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    if (inputMode == AddSubscriptionInputMode.Url) {
                        FilledTonalButton(
                            onClick = { },
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("订阅地址")
                        }
                        TextButton(
                            onClick = { onModeChange(AddSubscriptionInputMode.NodeList) },
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("节点列表")
                        }
                    } else {
                        TextButton(
                            onClick = { onModeChange(AddSubscriptionInputMode.Url) },
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("订阅地址")
                        }
                        FilledTonalButton(
                            onClick = { },
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("节点列表")
                        }
                    }
                }

                // 名称输入
                OutlinedTextField(
                    value = name,
                    onValueChange = onNameChange,
                    label = { Text("名称 (可选)") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )

                // 订阅地址输入
                if (inputMode == AddSubscriptionInputMode.Url) {
                    OutlinedTextField(
                        value = url,
                        onValueChange = onUrlChange,
                        label = { Text("订阅地址") },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        placeholder = { Text("https://example.com/sub") }
                    )
                } else {
                    // 节点列表输入
                    OutlinedTextField(
                        value = content,
                        onValueChange = onContentChange,
                        label = { Text("节点列表") },
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 4,
                        maxLines = 6
                    )
                    Text(
                        text = "每行一条节点链接，支持 vless://、vmess://、ss://、ssr://、trojan://、hysteria2://、tuic://",
                        style = MaterialTheme.typography.bodySmall,
                        color = scheme.onSurfaceVariant
                    )
                }
            }
        },
        confirmButton = {
            val canSubmit = when (inputMode) {
                AddSubscriptionInputMode.Url -> url.isNotBlank()
                AddSubscriptionInputMode.NodeList -> content.isNotBlank()
            }
            val buttonText = if (inputMode == AddSubscriptionInputMode.Url) "添加" else "导入"
            Button(
                onClick = onConfirm,
                enabled = canSubmit
            ) {
                Text(buttonText)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("取消")
            }
        }
    )
}

// ==================== 编辑订阅对话框 ====================

@Composable
fun EditSubscriptionDialog(
    name: String,
    url: String,
    isLocal: Boolean,
    onNameChange: (String) -> Unit,
    onUrlChange: (String) -> Unit,
    onDismiss: () -> Unit,
    onConfirm: () -> Unit
) {
    val scheme = MaterialTheme.colorScheme

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(
                text = "编辑订阅",
                style = MaterialTheme.typography.titleLarge
            )
        },
        text = {
            Column(
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                OutlinedTextField(
                    value = name,
                    onValueChange = onNameChange,
                    label = { Text("名称") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )

                OutlinedTextField(
                    value = url,
                    onValueChange = onUrlChange,
                    label = { Text(if (isLocal) "节点来源" else "订阅地址") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    enabled = !isLocal
                )

                if (isLocal) {
                    Text(
                        text = "本地订阅无法修改内容，如需更改请删除后重新导入",
                        style = MaterialTheme.typography.bodySmall,
                        color = scheme.onSurfaceVariant
                    )
                }
            }
        },
        confirmButton = {
            Button(onClick = onConfirm) {
                Text("保存")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("取消")
            }
        }
    )
}

// ==================== 枚举定义 ====================

/**
 * 添加订阅输入模式
 */
enum class AddSubscriptionInputMode {
    Url,       // 订阅地址
    NodeList   // 节点列表
}
