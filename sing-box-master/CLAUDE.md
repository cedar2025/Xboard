# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

这是一个基于 sing-box 核心的 Android VPN 客户端应用，使用 Kotlin 和 Jetpack Compose (Material 3) 构建。应用支持订阅管理、节点选择、流量监控和 Clash API 诊断等功能。

## 构建命令

```bash
# 构建 Debug 版本
./gradlew assembleDebug

# 构建 Release 版本
./gradlew assembleRelease

# 安装到连接的设备
./gradlew installDebug

# 按架构构建特定 APK (splits.abi 配置)
./gradlew assembleRelease  # 会生成 arm64-v8a, armeabi-v7a, x86, x86_64 四个独立 APK
```

## 架构概览

### 核心架构模式

应用采用 **单例状态管理 + Compose 响应式 UI** 模式：

1. **Store 模式**: 全局状态使用单例对象 + `mutableStateOf` 管理，Compose UI 自动订阅变化
2. **Repository 模式**: 配置和订阅数据通过 Repository 对象持久化到本地文件
3. **Manager 模式**: 核心功能通过 Manager 单例协调（如 OutboundGroupManager、CoreStatusManager）

### 关键组件

#### 1. VPN 服务层 (`vpn/`)

- `AppVpnService`: Android VpnService 实现，负责 VPN 接口的建立和管理
- `VpnStateStore`: 全局 VPN 状态单例 (IDLE/CONNECTING/CONNECTED/ERROR)
- `VpnController`: 控制 VPN 服务的启动/停止
- `PlatformInterfaceBridge`: 连接 libbox 与 Android VPN 的桥梁

#### 2. 核心引擎层 (`core/`)

- `SingBoxEngine`: sing-box 核心生命周期管理，负责启动/停止 libbox 服务
- `LibboxManager`: libbox 库的初始化和管理
- `CoreStatusManager/CoreStatusStore`: 核心状态（内存、连接数、流量）监控
- `CoreInfoManager/CoreInfoStore`: 核心版本等信息管理
- `OutboundGroupManager`: 节点分组管理，通过 Clash API 获取和操作节点
- `ClashApiClient`: Clash API 客户端，与 sing-box 的 REST API 通信
- `ClashApiStreamManager/ClashApiDiagnosticsManager`: Clash API 流量和诊断数据管理

#### 3. 配置层 (`config/`)

- `ConfigRepository`: sing-box JSON 配置的加载、保存和迁移
- `ConfigSettingsApplier`: 将用户设置应用到配置 JSON
- `ConfigDefaults`: 默认配置模板
- `SubscriptionRepository`: 订阅列表的 CRUD 操作，支持远程订阅和本地节点导入
- `ClashConverter`: Clash YAML 配置转换为 sing-box JSON
- `SettingsRepository/AppSettings`: 用户设置的持久化

#### 4. UI 层 (`ui/`)

- `AppNavigation`: 底部导航栏和 NavHost 路由
- `screens/`: 各功能页面 (HomeScreen, NodesScreen, SubscriptionScreen, SettingsScreen, DiagnosticsScreen)
- `components/`: 可复用的 UI 组件
- `theme/`: Material 3 主题和颜色定义

#### 5. 主入口 (`MainActivity.kt`)

- Activity 作为状态管理中心，聚合所有 Store 的状态
- 使用 `LaunchedEffect` 处理初始化和副作用
- 通过 AppNavigation 将所有回调和状态传递给子页面

### 数据流

```
用户操作 → MainActivity 回调 → Repository/Manager → libbox/文件系统
                ↓
          状态更新 (mutableStateOf)
                ↓
          Compose UI 自动重组
```

### Clash API 集成

应用通过 Clash REST API 与 sing-box 核心交互：

- `/proxies`: 获取节点列表和分组
- `/proxies/{group}`: 选择节点
- `/proxies/{proxy}/delay`: 节点测速
- `/connections`: 管理连接
- `/traffic` (stream): 流量统计

Clash API 配置通过 `AppSettings.clashApiEnabled` 和 `clashApiAddress` 控制。

## 重要约定

### 1. 状态管理模式

所有全局状态都使用 Store 单例模式，定义在对应模块中：

```kotlin
object SomeStore {
    var state by mutableStateOf<SomeState?>(null)
        private set

    fun update(newState: SomeState) {
        state = newState
    }
}
```

### 2. 文件存储位置

- 配置文件: `context.filesDir/singbox.json`
- 订阅数据: `context.filesDir/subscriptions.json`
- 设置数据: `context.filesDir/settings.json`

### 3. libbox AAR 依赖

核心库位于 `app/libs/libbox.aar`，提供 `io.nekohasekai.libbox` 包下的 JNI 接口。

### 4. 架构拆分

- `splits.abi` 配置会为每个 CPU 架构生成独立 APK
- 通用 APK 已禁用 (`universalApk false`)

### 5. 页面导航

页面切换通过 `AppNavigation` 中的 `Screen` sealed class 定义，添加新页面需要：
1. 在 `Screen` 中添加新路由
2. 在 `screens` 列表中添加
3. 在 NavHost 中添加 composable

### 6. 主题和样式

使用 Material 3 设计系统，主题定义在 `ui/theme/` 目录。状态颜色根据 VPN 状态动态变化（在 AppNavigation 中定义）。
