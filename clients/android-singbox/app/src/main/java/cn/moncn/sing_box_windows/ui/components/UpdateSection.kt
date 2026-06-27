package cn.moncn.sing_box_windows.ui.components

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.collectAsState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import cn.moncn.sing_box_windows.update.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.*

/**
 * 更新卡片组件
 * 显示当前版本和更新操作
 */
@Composable
fun UpdateSection(
    modifier: Modifier = Modifier,
    onUpdateClick: () -> Unit = {}
) {
    val context = LocalContext.current

    // 使用 produceState 订阅 UpdateStore 的状态变化
    val updateState by UpdateStore.stateFlow.collectAsState()

    // 处理更新状态
    when (val state = updateState) {
        is UpdateState.UpdateAvailable -> {
            UpdateAvailableDialog(
                release = state.releaseInfo,
                onDownloadClick = {
                    UpdateManager.getInstance(context).downloadUpdate(state.releaseInfo)
                },
                onDismissClick = {
                    UpdateManager.getInstance(context).ignoreUpdate()
                }
            )
        }
        is UpdateState.Failed -> {
            ErrorDialog(
                error = state.error,
                onDismiss = {
                    UpdateStore.reset()
                }
            )
        }
        else -> {}
    }

    Section(
        title = { Text("应用更新", style = MaterialTheme.typography.titleMedium) },
        modifier = modifier
    ) {
        NeoCard(modifier = Modifier.fillMaxWidth()) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // 版本信息行
                VersionInfoRow()

                // WiFi 下载开关
                WifiOnlyRow()

                // 状态行
                UpdateStatusRow(
                    state = updateState,
                    onUpdateClick = onUpdateClick
                )
            }
        }
    }
}

/**
 * 版本信息行
 */
@Composable
private fun VersionInfoRow() {
    val context = LocalContext.current
    val currentVersion = remember {
        try {
            val pm = context.packageManager
            val info = pm.getPackageInfo(context.packageName, 0)
            info.versionName ?: "未知"
        } catch (e: Exception) {
            "1.0"
        }
    }

    val scheme = MaterialTheme.colorScheme

    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column {
            Text(
                text = "当前版本",
                style = MaterialTheme.typography.labelMedium,
                color = scheme.onSurfaceVariant
            )
            Text(
                text = "v$currentVersion",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Medium
            )
        }

        // 检查更新按钮
        when (val state = UpdateStore.state) {
            is UpdateState.Checking -> {
                CircularProgressIndicator(
                    modifier = Modifier.size(20.dp),
                    strokeWidth = 2.dp
                )
            }
            else -> {
                FilledTonalIconButton(
                    onClick = {
                        UpdateManager.getInstance(context).checkUpdate(isManual = true)
                    }
                ) {
                    Icon(
                        imageVector = Icons.Rounded.Refresh,
                        contentDescription = "检查更新",
                        tint = MaterialTheme.colorScheme.primary
                    )
                }
            }
        }
    }
}

/**
 * WiFi 下载开关行
 */
@Composable
private fun WifiOnlyRow() {
    val scheme = MaterialTheme.colorScheme

    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Icon(
                imageVector = Icons.Rounded.Wifi,
                contentDescription = null,
                tint = scheme.onSurfaceVariant,
                modifier = Modifier.size(20.dp)
            )
            Column {
                Text(
                    text = "仅 Wi-Fi 下载",
                    style = MaterialTheme.typography.bodyMedium
                )
                Text(
                    text = if (UpdateStore.wifiOnlyDownload) "已启用" else "已禁用",
                    style = MaterialTheme.typography.labelSmall,
                    color = scheme.onSurfaceVariant
                )
            }
        }

        Switch(
            checked = UpdateStore.wifiOnlyDownload,
            onCheckedChange = {
                UpdateStore.setWifiOnly(it)
            }
        )
    }
}

/**
 * 更新状态行
 */
@Composable
private fun UpdateStatusRow(
    state: UpdateState,
    onUpdateClick: () -> Unit
) {
    val scheme = MaterialTheme.colorScheme

    when (state) {
        is UpdateState.UpToDate -> {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = Icons.Rounded.CheckCircle,
                    contentDescription = null,
                    tint = Color(0xFF4CAF50),
                    modifier = Modifier.size(18.dp)
                )
                Text(
                    text = "已是最新版本",
                    style = MaterialTheme.typography.bodySmall,
                    color = scheme.onSurfaceVariant
                )
            }
        }
        is UpdateState.Downloading -> {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "正在下载更新...",
                        style = MaterialTheme.typography.bodySmall,
                        color = scheme.primary
                    )
                    Text(
                        text = state.progress.getPercentageString(),
                        style = MaterialTheme.typography.labelSmall,
                        fontWeight = FontWeight.Medium,
                        color = scheme.primary
                    )
                }
                LinearProgressIndicator(
                    progress = { state.progress.percentage / 100f },
                    modifier = Modifier.fillMaxWidth(),
                )
                Text(
                    text = "${state.progress.getDownloadedSizeReadable()} / ${state.progress.getTotalSizeReadable()}",
                    style = MaterialTheme.typography.labelSmall,
                    color = scheme.onSurfaceVariant
                )
            }
        }
        is UpdateState.Failed -> {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = Icons.Rounded.Error,
                    contentDescription = null,
                    tint = Color(0xFFF44336),
                    modifier = Modifier.size(18.dp)
                )
                Text(
                    text = "检查失败",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color(0xFFF44336)
                )
            }
        }
        else -> {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = Icons.Rounded.Info,
                    contentDescription = null,
                    tint = scheme.onSurfaceVariant,
                    modifier = Modifier.size(18.dp)
                )
                Text(
                    text = "点击右上角按钮检查更新",
                    style = MaterialTheme.typography.bodySmall,
                    color = scheme.onSurfaceVariant
                )
            }
        }
    }
}

/**
 * 发现新版本对话框
 */
@Composable
private fun UpdateAvailableDialog(
    release: GitHubRelease,
    onDownloadClick: () -> Unit,
    onDismissClick: () -> Unit
) {
    Dialog(
        onDismissRequest = onDismissClick,
        properties = DialogProperties(
            dismissOnBackPress = true,
            dismissOnClickOutside = true,
            usePlatformDefaultWidth = false
        )
    ) {
        NeoCard(
            modifier = Modifier
                .fillMaxWidth(0.9f)
                .padding(16.dp)
        ) {
            Column(
                modifier = Modifier.padding(20.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // 标题
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Icon(
                        imageVector = Icons.Rounded.SystemUpdate,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(28.dp)
                    )
                    Column {
                        Text(
                            text = "发现新版本",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = release.tagName,
                            style = MaterialTheme.typography.titleMedium,
                            color = MaterialTheme.colorScheme.primary
                        )
                    }
                }

                // 更新说明
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
                    )
                ) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Text(
                            text = "更新内容",
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            fontWeight = FontWeight.Medium
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        // 可滚动的更新说明内容
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .heightIn(max = 200.dp)
                        ) {
                            LazyColumn(
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                item {
                                    Text(
                                        text = release.body,
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurface
                                    )
                                }
                            }
                        }
                    }
                }

                // 按钮
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp, Alignment.End)
                ) {
                    TextButton(onClick = onDismissClick) {
                        Text("稍后再说")
                    }
                    Button(onClick = onDownloadClick) {
                        Icon(
                            imageVector = Icons.Rounded.Download,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("立即下载")
                    }
                }
            }
        }
    }
}

/**
 * 错误对话框
 */
@Composable
private fun ErrorDialog(
    error: String,
    onDismiss: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        icon = {
            Icon(
                imageVector = Icons.Rounded.Error,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.error
            )
        },
        title = { Text("更新失败") },
        text = { Text(error) },
        confirmButton = {
            Button(onClick = onDismiss) {
                Text("确定")
            }
        }
    )
}
