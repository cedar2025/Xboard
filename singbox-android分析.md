# Android-Master 项目架构分析

## 📋 项目概述
**Elephant Route (大象网络)** 是一个基于 `sing-box` 核心的现代化 Android VPN 客户端应用。
* **开发语言**：Kotlin
* **UI 框架**：Jetpack Compose
* **设计系统**：Material 3

---

## 🏗️ 核心功能模块

### 1. 认证与用户模块 (`api/`)
**核心功能：**
* 用户登录、注册、密码找回
* 用户信息管理
* Token 认证机制
* 用户登出

**关键文件：**
* `ApiConstants.kt` - API 端点常量定义
* `AuthService.kt` - 认证服务实现
* `UserService.kt` - 用户信息服务
* `ApiModels.kt` - API 数据模型
* `NetworkClient.kt` - HTTP 客户端封装

### 2. 配置管理模块 (`config/`)
**核心功能：**
* Sing-box 配置生成与管理
* 订阅管理（远程订阅、本地导入）
* Clash 配置转换为 Sing-box 配置
* 应用设置持久化
* 配置模板构建

**关键文件：**
* `SubscriptionRepository.kt` - 订阅数据的 CRUD 操作
* `ConfigRepository.kt` - Sing-box 配置文件管理
* `ClashConverter.kt` - Clash YAML → Sing-box JSON 转换
* `TemplateConfigBuilder.kt` - 配置模板构建器
* `ConfigSettingsApplier.kt` - 将用户设置应用到配置
* `SettingsRepository.kt` - 用户设置持久化
* `AppSettings.kt` - 应用设置数据类

### 3. VPN 核心模块 (`vpn/`)
**核心功能：**
* Android VPN 服务实现
* VPN 状态管理
* 网络接口配置
* 流量路由
* DNS 解析

**关键文件：**
* `AppVpnService.kt` - Android VpnService 实现
* `VpnStateStore.kt` - VPN 状态全局管理（IDLE/CONNECTING/CONNECTED/ERROR）
* `VpnController.kt` - VPN 服务启停控制器
* `PlatformInterfaceBridge.kt` - libbox 与 Android VPN 的桥梁
* `DefaultNetworkMonitor.kt` - 网络状态监控
* `LocalResolver.kt` - 本地 DNS 解析器

### 4. 核心引擎模块 (`core/`)
**核心功能：**
* Sing-box 核心生命周期管理
* Clash API 集成
* 节点管理与测速
* 流量统计
* 连接诊断

**关键文件：**
* **核心引擎**
    * `SingBoxEngine.kt` - Sing-box 核心启动/停止管理
    * `LibboxManager.kt` - libbox 库初始化
    * `LibboxServiceHolder.kt` - Libbox 服务持有者
    * `CommandServerHolder.kt` - 命令服务器管理
* **Clash API 集成**
    * `ClashApiClient.kt` - Clash REST API 客户端
    * `ClashApiStreamManager.kt` - 流量数据 WebSocket 流管理
    * `ClashApiDiagnosticsManager.kt` - 诊断数据管理
    * `ClashModeManager.kt` - Clash 模式切换
* **状态管理**
    * `CoreStatusManager.kt` - 核心状态监控（内存、连接数、流量）
    * `CoreStatusStore.kt` - 核心状态数据存储
    * `CoreInfoManager.kt` - 核心版本信息管理
    * `CoreInfoStore.kt` - 核心信息存储
* **节点管理**
    * `OutboundGroupManager.kt` - 节点分组管理、节点切换、测速

### 5. 用户界面模块 (`ui/`)
**核心功能：**
* Material 3 设计系统
* 页面导航
* 主页、节点、订阅、设置等界面
* 自定义组件库

**关键文件：**
* **导航**：`AppNavigation.kt` (底部导航栏 + NavHost 路由)
* **页面**：
    * `screens/HomeScreen.kt` - 主页（连接控制、流量统计）
    * `screens/NodesScreen.kt` - 节点列表与选择
    * `screens/SubscriptionScreen.kt` - 订阅管理
    * `screens/SettingsScreen.kt` - 应用设置
    * `screens/DiagnosticsScreen.kt` - 诊断工具
    * `screens/LoginScreen.kt` - 登录界面
* **组件与主题**：
    * `components/DesignSystem.kt` - 设计系统组件库
    * `components/ElephantLogo.kt` - Logo 组件
    * `theme/` - 包含 `Color.kt`, `Theme.kt`, `Type.kt`

### 6. 应用更新模块 (`update/`)
**核心功能：**
* GitHub Release 检查
* APK 下载
* 应用自动更新安装

**关键文件：**
* `UpdateManager.kt` - 更新管理器
* `GitHubReleaseChecker.kt` - GitHub Release API 检查
* `ApkDownloader.kt` - APK 文件下载器
* `AppUpdateInstaller.kt` - APK 安装器

### 7. 主入口 (`MainActivity.kt`)
**核心功能：**
* 应用生命周期管理
* 全局状态聚合
* 初始化协调
* 事件分发

---

## 🔌 系统架构图

```mermaid
graph TD
    subgraph UI_Layer [用户界面层 UI Layer]
        AppNavigation --> LoginScreen
        AppNavigation --> HomeScreen
        AppNavigation --> NodesScreen
        AppNavigation --> SubscriptionScreen
        AppNavigation --> SettingsScreen
    end

    subgraph Business_Layer [业务逻辑层 Business Layer]
        AuthService
        UserService
        VpnController
        OutboundGroupManager
        SubscriptionRepository
        UpdateManager
    end

    subgraph State_Layer [状态管理层 State Layer]
        VpnStateStore
        CoreStatusStore
        CoreInfoStore
        SettingsRepository
    end

    subgraph Core_Engine_Layer [核心引擎层 Core Engine Layer]
        SingBoxEngine
        LibboxManager
        ClashApiClient
        CoreStatusManager
    end

    subgraph System_Service_Layer [系统服务层 System Service Layer]
        AppVpnService
        PlatformInterfaceBridge
        DefaultNetworkMonitor
    end

    subgraph Network_Layer [网络层 Network Layer]
        NetworkClient --> BackendAPI[Xboard Panel]
        SubServer[订阅服务器]
    end

    subgraph Native_Layer [核心库层 Native Library Layer]
        libbox[libbox.aar / Sing-box]
    end

    subgraph Storage_Layer [存储层 Storage Layer]
        singbox_json[singbox.json]
        sub_json[subscriptions.json]
        settings_json[settings.json]
    end

    UI_Layer --> Business_Layer
    Business_Layer --> State_Layer
    Business_Layer --> Core_Engine_Layer
    Core_Engine_Layer --> Native_Layer
    System_Service_Layer --> Native_Layer
    Native_Layer --> Storage_Layer