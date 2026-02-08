package cn.moncn.sing_box_windows.ui

import androidx.compose.animation.animateColorAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import cn.moncn.sing_box_windows.core.CoreStatus
import cn.moncn.sing_box_windows.core.OutboundGroupModel
import cn.moncn.sing_box_windows.core.ClashModeManager
import cn.moncn.sing_box_windows.config.SubscriptionState
import cn.moncn.sing_box_windows.vpn.VpnState
import cn.moncn.sing_box_windows.ui.screens.HomeScreen
import cn.moncn.sing_box_windows.ui.screens.NodesScreen
import cn.moncn.sing_box_windows.ui.screens.SettingsScreen
import cn.moncn.sing_box_windows.ui.screens.SubscriptionScreen
import cn.moncn.sing_box_windows.ui.components.AppBackground
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.List
import androidx.compose.material.icons.rounded.Dns
import androidx.compose.material.icons.rounded.Home
import androidx.compose.material.icons.rounded.Settings
import cn.moncn.sing_box_windows.ui.theme.ConnectedColor
import cn.moncn.sing_box_windows.ui.theme.ConnectingColor
import cn.moncn.sing_box_windows.ui.theme.ErrorColor
import cn.moncn.sing_box_windows.ui.theme.IdleColor

// ==================== 导航路由定义 ====================

/**
 * 导航页面路由
 */
sealed class Screen(
    val route: String,
    val label: String,
    val icon: ImageVector
) {
    /** 首页 - 控制中心 */
    object Home : Screen("home", "首页", Icons.Rounded.Home)

    /** 订阅管理 */
    object Subscriptions : Screen("subscriptions", "订阅", Icons.AutoMirrored.Rounded.List)

    /** 节点选择 */
    object Nodes : Screen("nodes", "节点", Icons.Rounded.Dns)

    /** 设置 */
    object Settings : Screen("settings", "设置", Icons.Rounded.Settings)
}

// ==================== 主导航组件 ====================

/**
 * 现代化应用导航
 * 包含底部导航栏和页面路由
 */
@Composable
fun AppNavigation(
    state: VpnState,
    coreStatus: CoreStatus?,
    coreVersion: String?,
    currentMode: ClashModeManager.ClashMode?,
    isModeSupported: Boolean,
    subscriptions: SubscriptionState,
    nameInput: String,
    urlInput: String,
    updatingId: String?,
    groups: List<OutboundGroupModel>,
    // Actions
    onConnect: () -> Unit,
    onDisconnect: () -> Unit,
    onSwitchMode: (ClashModeManager.ClashMode) -> Unit,
    onAddSubscription: (String, String) -> Unit,
    onImportNodes: (String, String) -> Unit,
    onEditSubscription: (String, String, String) -> Unit,
    onRemoveSubscription: (String) -> Unit,
    onSelectSubscription: (String) -> Unit,
    onUpdateSubscription: (String) -> Unit,
    onSelectNode: (String, String) -> Unit,
    onTestNode: (String) -> Unit,
    onShowMessage: (MessageDialogState) -> Unit
) {
    val navController = rememberNavController()
    val scheme = MaterialTheme.colorScheme

    // ==================== 状态颜色动画 ====================

    /**
     * 根据 VPN 状态动态变化的颜色
     * 用于导航栏指示器和状态显示
     */
    val statusColor by animateColorAsState(
        targetValue = when (state) {
            VpnState.CONNECTED -> ConnectedColor
            VpnState.CONNECTING -> ConnectingColor
            VpnState.ERROR -> ErrorColor
            VpnState.IDLE -> IdleColor
        },
        label = "statusColor"
    )

    val statusText = when (state) {
        VpnState.CONNECTED -> "已连接"
        VpnState.CONNECTING -> "连接中"
        VpnState.ERROR -> "错误"
        VpnState.IDLE -> "未连接"
    }

    val actionText = if (state == VpnState.CONNECTED || state == VpnState.CONNECTING) {
        "断开连接"
    } else {
        "点击连接"
    }

    val screens = listOf(
        Screen.Home,
        Screen.Subscriptions,
        Screen.Nodes,
        Screen.Settings
    )

    // ==================== 主脚手架 ====================

    Scaffold(
        containerColor = Color.Transparent,
        bottomBar = {
            // ==================== 底部导航栏 ====================
            ModernNavigationBar(
                screens = screens,
                navController = navController,
                statusColor = statusColor
            )
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            // 全局背景
            AppBackground(modifier = Modifier.fillMaxSize())

            // ==================== 路由导航宿主 ====================
            NavHost(
                navController = navController,
                startDestination = Screen.Home.route,
                modifier = Modifier.fillMaxSize()
            ) {
                // 首页路由
                composable(Screen.Home.route) {
                    HomeScreen(
                        state = state,
                        statusText = statusText,
                        statusColor = statusColor,
                        actionText = actionText,
                        coreStatus = coreStatus,
                        coreVersion = coreVersion,
                        groups = groups,
                        currentMode = currentMode,
                        isModeSupported = isModeSupported,
                        onConnect = onConnect,
                        onDisconnect = onDisconnect,
                        onSwitchMode = onSwitchMode
                    )
                }

                // 订阅管理路由
                composable(Screen.Subscriptions.route) {
                    SubscriptionScreen(
                        subscriptions = subscriptions,
                        updatingId = updatingId,
                        onAddSubscription = onAddSubscription,
                        onImportNodes = onImportNodes,
                        onEditSubscription = onEditSubscription,
                        onRemoveSubscription = onRemoveSubscription,
                        onSelectSubscription = onSelectSubscription,
                        onUpdateSubscription = { id ->
                            onUpdateSubscription(id)
                        },
                        onShowMessage = onShowMessage
                    )
                }

                // 节点选择路由
                composable(Screen.Nodes.route) {
                    NodesScreen(
                        groups = groups,
                        onSelectNode = onSelectNode,
                        onTestNode = onTestNode
                    )
                }

                // 设置路由
                composable(Screen.Settings.route) {
                    SettingsScreen(
                        onShowMessage = onShowMessage
                    )
                }
            }
        }
    }
}

// ==================== 现代化导航栏 ====================

/**
 * 现代化底部导航栏
 * 带有状态指示和动画效果
 */
@Composable
private fun ModernNavigationBar(
    screens: List<Screen>,
    navController: androidx.navigation.NavController,
    statusColor: Color
) {
    val scheme = MaterialTheme.colorScheme

    // 获取当前导航位置
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentDestination = navBackStackEntry?.destination

    NavigationBar(
        containerColor = scheme.surface.copy(alpha = 0.95f),
        tonalElevation = 8.dp
    ) {
        screens.forEach { screen ->
            val isSelected = currentDestination?.hierarchy?.any {
                it.route == screen.route
            } == true

            NavigationBarItem(
                icon = {
                    Icon(
                        imageVector = screen.icon,
                        contentDescription = screen.label
                    )
                },
                label = {
                    Text(
                        text = screen.label,
                        style = MaterialTheme.typography.labelSmall
                    )
                },
                selected = isSelected,
                colors = NavigationBarItemDefaults.colors(
                    selectedIconColor = if (isSelected) {
                        statusColor
                    } else {
                        scheme.onSurfaceVariant
                    },
                    selectedTextColor = if (isSelected) {
                        statusColor
                    } else {
                        scheme.onSurfaceVariant
                    },
                    indicatorColor = scheme.surfaceVariant,
                    unselectedIconColor = scheme.onSurfaceVariant,
                    unselectedTextColor = scheme.onSurfaceVariant
                ),
                onClick = {
                    navController.navigate(screen.route) {
                        // 返回到起始目的地时保存状态
                        popUpTo(navController.graph.findStartDestination().id) {
                            saveState = true
                        }
                        launchSingleTop = true
                        restoreState = true
                    }
                }
            )
        }
    }
}
