package cn.moncn.sing_box_windows

import android.app.Activity
import android.Manifest
import android.net.VpnService
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.provider.Settings
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.tooling.preview.Preview
import androidx.core.content.ContextCompat
import androidx.core.app.NotificationManagerCompat
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import cn.moncn.sing_box_windows.config.SubscriptionRepository
import cn.moncn.sing_box_windows.config.SubscriptionState
import cn.moncn.sing_box_windows.config.SubscriptionUpdateResult
import cn.moncn.sing_box_windows.config.AppSettings
import cn.moncn.sing_box_windows.config.AppSettingsDefaults
import cn.moncn.sing_box_windows.config.ClashApiDefaults
import cn.moncn.sing_box_windows.config.SettingsRepository
import cn.moncn.sing_box_windows.core.CoreInfoStore
import cn.moncn.sing_box_windows.core.CoreStatusStore
import cn.moncn.sing_box_windows.core.ClashApiClient
import cn.moncn.sing_box_windows.core.CoreInfoManager
import cn.moncn.sing_box_windows.core.CoreStatusManager
import cn.moncn.sing_box_windows.core.LibboxManager
import cn.moncn.sing_box_windows.core.OutboundGroupManager
import cn.moncn.sing_box_windows.core.ClashModeManager
import cn.moncn.sing_box_windows.update.UpdateManager
import cn.moncn.sing_box_windows.update.UpdateStore
import cn.moncn.sing_box_windows.ui.MessageDialogState
import cn.moncn.sing_box_windows.ui.MessageTone
import cn.moncn.sing_box_windows.ui.AppNavigation
import cn.moncn.sing_box_windows.ui.theme.SingboxwindowsTheme
import cn.moncn.sing_box_windows.vpn.VpnController
import cn.moncn.sing_box_windows.vpn.VpnState
import cn.moncn.sing_box_windows.vpn.VpnStateStore
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        LibboxManager.initialize(this)
        // 初始化 UpdateStore，加载持久化设置
        UpdateStore.init(this)
        enableEdgeToEdge()
        setContent {
            SingboxwindowsTheme {
                val context = LocalContext.current
                val state = VpnStateStore.state
                val error = VpnStateStore.lastError
                val scope = rememberCoroutineScope()
                val groups = OutboundGroupManager.groups
                val coreStatus = CoreStatusStore.status
                val coreVersion = CoreInfoStore.version
                val currentMode = ClashModeManager.currentMode
                val isModeSupported = ClashModeManager.isModeSupported
                var subscriptions by remember { mutableStateOf(SubscriptionState.empty()) }
                var nameInput by remember { mutableStateOf("") }
                var urlInput by remember { mutableStateOf("") }
                var updatingId by remember { mutableStateOf<String?>(null) }
                var dialogMessage by remember { mutableStateOf<MessageDialogState?>(null) }
                var lastVpnError by remember { mutableStateOf<String?>(null) }
                var pendingConnect by remember { mutableStateOf(false) }
                var appSettings by remember { mutableStateOf<AppSettings?>(null) }

                LaunchedEffect(Unit) {
                    subscriptions = withContext(Dispatchers.IO) {
                        SubscriptionRepository.load(context)
                    }
                }

                LaunchedEffect(Unit) {
                    appSettings = withContext(Dispatchers.IO) {
                        SettingsRepository.load(context)
                    }
                }

                LaunchedEffect(state, appSettings) {
                    val settings = appSettings ?: return@LaunchedEffect
                    if (state == VpnState.CONNECTED || state == VpnState.CONNECTING) {
                        // 默认启用 Clash API 用于节点管理
                        ClashApiClient.configure(ClashApiDefaults.ADDRESS, ClashApiDefaults.SECRET)
                        CoreStatusManager.start()
                        OutboundGroupManager.start()
                        ClashModeManager.start()
                        CoreInfoManager.start()
                    } else {
                        ClashApiClient.reset()
                    }
                }

                LaunchedEffect(error) {
                    if (!error.isNullOrBlank() && error != lastVpnError) {
                        lastVpnError = error
                        dialogMessage = MessageDialogState(
                            title = "连接错误",
                            message = error,
                            tone = MessageTone.Error
                        )
                    }
                }

                fun showMessage(
                    title: String,
                    message: String,
                    tone: MessageTone = MessageTone.Info,
                    confirmText: String = "确定",
                    dismissText: String? = null,
                    onConfirm: (() -> Unit)? = null
                ) {
                    dialogMessage = MessageDialogState(
                        title = title,
                        message = message,
                        tone = tone,
                        confirmText = confirmText,
                        dismissText = dismissText,
                        onConfirm = onConfirm
                    )
                }

                fun showSyncResult(result: SubscriptionUpdateResult) {
                    val tone = when {
                        !result.ok -> MessageTone.Error
                        result.warnings.isNotEmpty() -> MessageTone.Warning
                        else -> MessageTone.Success
                    }
                    val title = if (result.ok) "订阅同步完成" else "订阅同步失败"
                    showMessage(title, result.message, tone)
                }

                val launcher = rememberLauncherForActivityResult(
                    ActivityResultContracts.StartActivityForResult()
                ) { result ->
                    if (result.resultCode == Activity.RESULT_OK) {
                        VpnController.start(context)
                    } else {
                        VpnStateStore.update(VpnState.ERROR, "VPN permission denied")
                    }
                }

                val notificationLauncher = rememberLauncherForActivityResult(
                    ActivityResultContracts.RequestPermission()
                ) { granted ->
                    if (!granted) {
                        showMessage(
                            title = "Notification Permission",
                            message = "Permission denied. Foreground notification may not appear.",
                            tone = MessageTone.Warning
                        )
                        pendingConnect = false
                    } else if (pendingConnect) {
                        pendingConnect = false
                        val intent = VpnService.prepare(context)
                        if (intent != null) {
                            launcher.launch(intent)
                        } else {
                            VpnController.start(context)
                        }
                    }
                }

                LaunchedEffect(Unit) {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                        val permission = Manifest.permission.POST_NOTIFICATIONS
                        val granted = ContextCompat.checkSelfPermission(
                            context,
                            permission
                        ) == PackageManager.PERMISSION_GRANTED
                        if (!granted) {
                            notificationLauncher.launch(permission)
                        }
                    }
                }

                // ==================== 自动检查更新 ====================
                LaunchedEffect(Unit) {
                    // 延迟检查更新，避免影响启动速度
                    kotlinx.coroutines.delay(2000)
                    UpdateManager.getInstance(context).autoCheckIfNeeded()
                }

                if (dialogMessage != null) {
                    AlertDialog(
                        onDismissRequest = { dialogMessage = null },
                        title = { Text(dialogMessage!!.title) },
                        text = { Text(dialogMessage!!.message) },
                        confirmButton = {
                            Button(onClick = {
                                dialogMessage?.onConfirm?.invoke()
                                dialogMessage = null
                            }) {
                                Text(dialogMessage!!.confirmText)
                            }
                        },
                        dismissButton = {
                            if (dialogMessage?.dismissText != null) {
                                TextButton(onClick = { dialogMessage = null }) {
                                    Text(dialogMessage!!.dismissText!!)
                                }
                            }
                        }
                    )
                }

                    AppNavigation(
                        state = state,
                        coreStatus = coreStatus,
                        coreVersion = coreVersion,
                        currentMode = currentMode,
                        isModeSupported = isModeSupported,
                        subscriptions = subscriptions,
                    nameInput = nameInput,
                    urlInput = urlInput,
                    updatingId = updatingId,
                    groups = groups,
                    onShowMessage = { dialogMessage = it },
                    onConnect = {
                        val notificationsEnabled =
                            NotificationManagerCompat.from(context).areNotificationsEnabled()
                        if (!notificationsEnabled) {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                val permission = Manifest.permission.POST_NOTIFICATIONS
                                val granted = ContextCompat.checkSelfPermission(
                                    context,
                                    permission
                                ) == PackageManager.PERMISSION_GRANTED
                                if (!granted) {
                                    pendingConnect = true
                                    notificationLauncher.launch(permission)
                                    return@AppNavigation
                                }
                            }
                            showMessage(
                                title = "Notification Settings",
                                message = "Please enable notifications in system settings.",
                                tone = MessageTone.Warning,
                                confirmText = "Open Settings",
                                dismissText = "Later",
                                onConfirm = { openNotificationSettings(context) }
                            )
                        }
                        val intent = VpnService.prepare(context)
                        if (intent != null) {
                            launcher.launch(intent)
                        } else {
                            VpnController.start(context)
                        }
                    },
                    onDisconnect = { VpnController.stop(context) },
                    onSwitchMode = { mode ->
                        scope.launch {
                            val result = ClashModeManager.switchMode(mode)
                            result.onFailure {
                                showMessage(
                                    title = "切换失败",
                                    message = it.message ?: "未知错误",
                                    tone = MessageTone.Error
                                )
                            }
                        }
                    },
                    onAddSubscription = { name, url ->
                        scope.launch {
                            val addResult = withContext(Dispatchers.IO) {
                                SubscriptionRepository.add(context, name, url)
                            }
                            subscriptions = addResult.state
                            updatingId = addResult.item.id
                            val result = withContext(Dispatchers.IO) {
                                SubscriptionRepository.activate(context, addResult.item.id)
                            }
                            subscriptions = result.state
                            updatingId = null
                            showSyncResult(result)
                        }
                    },
                    onImportNodes = { name, content ->
                        scope.launch {
                            val result = withContext(Dispatchers.IO) {
                                SubscriptionRepository.importLocal(context, name, content)
                            }
                            if (!result.ok) {
                                showMessage(
                                    title = "导入失败",
                                    message = result.message,
                                    tone = MessageTone.Error
                                )
                                return@launch
                            }
                            subscriptions = result.state
                            val tone = if (result.warnings.isNotEmpty()) {
                                MessageTone.Warning
                            } else {
                                MessageTone.Success
                            }
                            showMessage("导入完成", result.message, tone)
                        }
                    },
                    onEditSubscription = { id, name, url ->
                        scope.launch {
                            val result = withContext(Dispatchers.IO) {
                                SubscriptionRepository.edit(context, id, name, url)
                            }
                            if (!result.ok) {
                                showMessage("Edit Failed", result.message, MessageTone.Error)
                                return@launch
                            }
                            subscriptions = result.state
                            if (result.urlChanged && result.state.selectedId == id) {
                                showMessage(
                                    title = "Subscription Updated",
                                    message = "URL changed. Update now?",
                                    tone = MessageTone.Warning,
                                    confirmText = "Update Now",
                                    dismissText = "Later",
                                    onConfirm = {
                                        scope.launch {
                                            updatingId = id
                                            val syncResult = withContext(Dispatchers.IO) {
                                                SubscriptionRepository.updateSelected(context)
                                            }
                                            subscriptions = syncResult.state
                                            updatingId = null
                                            showSyncResult(syncResult)
                                        }
                                    }
                                )
                            }
                        }
                    },
                    onSelectSubscription = { id ->
                        scope.launch {
                            updatingId = id
                            val result = withContext(Dispatchers.IO) {
                                SubscriptionRepository.activate(context, id)
                            }
                            subscriptions = result.state
                            updatingId = null
                            showSyncResult(result)
                        }
                    },
                    onRemoveSubscription = { id ->
                        scope.launch {
                            subscriptions = withContext(Dispatchers.IO) {
                                SubscriptionRepository.remove(context, id)
                            }
                            showMessage("Deleted", "Subscription removed.", MessageTone.Success)
                        }
                    },
                    onUpdateSubscription = { id ->
                        // Adapted to use ID
                        if (id == subscriptions.selectedId) {
                            scope.launch {
                                updatingId = id
                                val result = withContext(Dispatchers.IO) {
                                    SubscriptionRepository.updateSelected(context)
                                }
                                subscriptions = result.state
                                updatingId = null
                                showSyncResult(result)
                            }
                        } else {
                             scope.launch {
                                updatingId = id
                                val result = withContext(Dispatchers.IO) {
                                    SubscriptionRepository.activate(context, id)
                                }
                                subscriptions = result.state
                                updatingId = null
                                showSyncResult(result)
                            }
                        }
                    },
                    onSelectNode = { groupTag, outboundTag ->
                        scope.launch {
                            OutboundGroupManager.select(groupTag, outboundTag)
                        }
                    },
                    onTestNode = { outboundTag ->
                        scope.launch {
                            val result = OutboundGroupManager.urlTest(outboundTag)
                            val errorMessage = result.exceptionOrNull()?.message
                            if (!errorMessage.isNullOrBlank()) {
                                showMessage("Test Failed", errorMessage, MessageTone.Error)
                            }
                        }
                    }
                )
            }
        }
    }
}



private fun openNotificationSettings(context: android.content.Context) {
    val intent = Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).apply {
        putExtra(Settings.EXTRA_APP_PACKAGE, context.packageName)
        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    }
    runCatching { context.startActivity(intent) }
}
