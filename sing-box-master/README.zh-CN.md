# Sing Box Windows Android

<div align="center">
  <img src="https://img.shields.io/badge/Android-10%2B-green.svg" alt="Android Version">
  <img src="https://img.shields.io/badge/Kotlin-blue.svg" alt="Kotlin">
  <img src="https://img.shields.io/badge/Jetpack%20Compose-purple.svg" alt="Jetpack Compose">
  <img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License">
  <a href="https://github.com/xinggaoya/sing-box-windows-android/actions/workflows/release.yml">
    <img src="https://img.shields.io/github/actions/workflow/status/xinggaoya/sing-box-windows-android/release.yml?branch=master" alt="Build Status">
  </a>
</div>

**Language / 语言**: [中文](README.zh-CN.md) | [English](README.md)

---

<div align="center">
  <img src="./docs/image.png" alt="App Screenshot" width="300">
</div>

## 🎯 简介

Sing Box Windows Android 是一个基于 sing-box 核心的现代化 Android VPN 客户端应用，提供简洁、高效的网络代理管理功能。支持多种代理协议，具有优雅的用户界面和强大的功能特性。

## ✨ 主要特性

- 🚀 **高性能 VPN 服务**：基于 sing-box 核心，稳定可靠的连接体验
- 📱 **现代化界面**：采用 Jetpack Compose 构建的 Material 3 设计风格
- 🔗 **智能订阅管理**：支持添加、编辑和管理多个订阅链接
- ⚡ **实时节点测速**：自动测试节点延迟，选择最优连接节点
- 📊 **流量监控统计**：实时显示网络连接和流量使用情况
- 🎯 **自动节点分组**：智能组织和管理节点分组
- 🔧 **多架构支持**：支持 ARM64 和 ARMv7 架构设备
- 🌐 **协议兼容性**：完整支持 VLESS、Shadowsocks 等主流代理协议

## 📱 下载安装

### 最新版本

- [📥 v1.1.0 正式版](https://github.com/xinggaoya/sing-box-windows-android/releases/tag/v1.1.0)

### 架构选择

根据您的设备架构选择合适的 APK：

| 架构        | 文件名                        | 适用设备                         | 推荐度 |
| ----------- | ----------------------------- | -------------------------------- | ------ |
| arm64-v8a   | `app-arm64-v8a-release.apk`   | 64 位 ARM 设备（大部分现代设备） | ⭐⭐⭐ |
| armeabi-v7a | `app-armeabi-v7a-release.apk` | 32 位 ARM 设备（较老设备）       | ⭐⭐   |

### 系统要求

- **Android 版本**: Android 10 (API 29) 或更高版本
- **存储空间**: 至少 100MB 可用空间
- **权限**: VPN 权限和网络访问权限

## 🚀 快速开始

1. **下载应用**：从 [Releases](https://github.com/xinggaoya/sing-box-windows-android/releases) 页面下载对应架构的 APK
2. **安装应用**：在 Android 设备上安装 APK 文件
3. **授予权限**：允许 VPN 权限和通知权限
4. **添加订阅**：输入您的订阅链接或导入本地节点列表
5. **连接使用**：选择节点并连接 VPN

## 📖 使用指南

### 添加订阅

1. 打开应用，点击"添加订阅"
2. 输入订阅名称和链接地址
3. 点击"添加"并等待同步完成
4. 选择启用的订阅

### 导入本地节点

您也可以导入本地节点列表：
1. 在订阅管理中点击"导入本地"
2. 选择或粘贴节点列表内容
3. 保存即可使用本地管理的节点

### 节点管理

- **自动选择**：使用"自动测速"分组自动选择最优节点
- **手动选择**：在分组中手动选择特定节点
- **节点测速**：点击测速按钮测试节点延迟

### 流量监控

- 实时显示上传/下载速度
- 统计累计流量使用
- 监控连接状态

## 🛠️ 技术规格

| 项目         | 规格                         |
| ------------ | ---------------------------- |
| **开发语言** | Kotlin                       |
| **UI 框架**  | Jetpack Compose + Material 3 |
| **核心库**   | libbox (sing-box)            |
| **最低 SDK** | API 29 (Android 10)          |
| **目标 SDK** | API 36 (Android 15)          |
| **构建工具** | Gradle with Kotlin DSL       |
| **支持协议** | VLESS, Shadowsocks 等        |

## 🔧 开发构建

### 环境要求

- Android Studio Hedgehog 或更高版本
- JDK 17 或更高版本
- Android SDK (API 29+)

### 构建步骤

```bash
# 克隆仓库
git clone https://github.com/xinggaoya/sing-box-windows-android.git
cd singboxwindows

# 构建 Debug 版本
./gradlew assembleDebug

# 构建 Release 版本
./gradlew assembleRelease

# 安装到设备
./gradlew installDebug
```

### 项目结构

```
app/
├── src/main/
│   ├── java/cn/moncn/sing_box_windows/
│   │   ├── config/          # 配置管理
│   │   ├── core/            # 核心功能 (Clash API, 状态, 诊断)
│   │   ├── ui/              # 用户界面 (屏幕, 组件, 主题)
│   │   └── vpn/             # VPN 服务
│   ├── res/                 # 资源文件
│   └── libs/                # 本地依赖库 (libbox.aar)
└── build.gradle             # 模块构建配置
```

### 架构概览

应用采用 **单例 Store + Compose 响应式 UI** 模式：

- **Store 模式**：使用单例对象 + `mutableStateOf` 进行全局状态管理
- **Repository 模式**：配置和订阅数据持久化
- **Manager 模式**：核心功能协调 (OutboundGroupManager, CoreStatusManager)

详细架构文档请参阅 [CLAUDE.md](CLAUDE.md)。

## 🤝 贡献指南

欢迎贡献代码！请遵循以下步骤：

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

### 编码规范

- 遵循 Kotlin 官方编码规范
- 使用 4 空格缩进
- 函数和变量使用 camelCase
- 类名使用 PascalCase
- 添加必要的注释说明

## 📝 更新日志

查看 [CHANGELOG.md](docs/CHANGELOG.md) 了解详细的版本更新记录。

### 最近更新

**v1.1.0** (2025-12-23)
- Clash API 深度集成，实时流量统计
- 支持导入本地节点列表
- 重构导航架构，底部导航栏设计
- 增强设置功能，配置自动保存

## 🐛 问题反馈

如果遇到问题，请：

1. 查看 [已知问题](#已知问题)
2. 搜索已有的 [Issues](https://github.com/xinggaoya/sing-box-windows-android/issues)
3. 如果问题未被解决，请创建新的 Issue 并提供：
   - 设备型号和 Android 版本
   - 应用版本
   - 详细的错误描述和复现步骤

## ⚠️ 已知问题

- 部分旧设备可能需要手动授予通知权限
- 某些网络环境下连接可能不稳定
- 订阅同步超时时间较长（正在优化中）

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🙏 致谢

- [sing-box](https://github.com/SagerNet/sing-box) - 强大的网络代理工具
- [Jetpack Compose](https://developer.android.com/jetpack/compose) - 现代化的 UI 工具包
- [Material Design 3](https://m3.material.io/) - 设计系统

## ⚖️ 免责声明

本应用仅供学习和研究使用，请遵守当地法律法规。开发者不对使用本应用产生的任何后果承担责任。

---

<div align="center">
  <strong>如果这个项目对您有帮助，请给个 ⭐ Star！</strong>
</div>
