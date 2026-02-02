# Xboard

[English](README.md) | [简体中文](README_zh.md)

<div align="center">

[![Telegram](https://img.shields.io/badge/Telegram-Channel-blue)](https://t.me/XboardOfficial)
![PHP](https://img.shields.io/badge/PHP-8.2+-green.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-blue.svg)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

</div>

## 📖 简介

Xboard 是一个基于 Laravel 11 构建的现代面板系统，专注于提供简洁高效的用户体验。

## ✨ 特性

- 🚀 基于 Laravel 12 + Octane 构建，显著提升性能
- 🎨 重设计的管理后台 (React + Shadcn UI)
- 📱 现代化的用户前台 (Vue3 + TypeScript)
- 🐳 开箱即用的 Docker 部署方案
- 🎯 优化的系统架构，更易于维护

## 🚀 快速开始

```bash
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard && \
cd Xboard && \
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    web php artisan xboard:install && \
docker compose up -d
```

> 安装完成后访问: http://SERVER_IP:7001  
> ⚠️ 请务必保存安装过程中显示的管理员账号密码

## 📖 文档

### 🔄 更新须知
> 🚨 **重要:** 此版本包含重大变更。升级前请严格遵照升级文档并备份数据库。请注意升级和迁移是不同的过程，切勿混淆。

**全局一键更新设置:**
如需在服务器任何地方一键更新，请在项目根目录运行：
```bash
chmod +x update.sh && sudo ln -sf $(pwd)/update.sh /usr/local/bin/xb-update
```
之后即可使用 `xb-update` 命令进行更新。

### 开发指南
- [插件开发指南](./docs/zh/development/plugin-development-guide.md) - 开发 XBoard 插件的完整指南

### 部署指南
- [使用 1Panel 部署](./docs/zh/installation/1panel.md)
- [使用 Docker Compose 部署](./docs/zh/installation/docker-compose.md)
- [使用 aaPanel 部署](./docs/zh/installation/aapanel.md)
- [使用 aaPanel + Docker 部署](./docs/zh/installation/aapanel-docker.md) (推荐)

### 迁移指南
- [从 v2board dev 迁移](./docs/zh/migration/v2board-dev.md)
- [从 v2board 1.7.4 迁移](./docs/zh/migration/v2board-1.7.4.md)
- [从 v2board 1.7.3 迁移](./docs/zh/migration/v2board-1.7.3.md)

## 🛠️ 技术栈

- 后端: Laravel 11 + Octane
- 管理后台: React + Shadcn UI + TailwindCSS
- 用户前台: Vue3 + TypeScript + NaiveUI
- 部署: Docker + Docker Compose
- 缓存: Redis + Octane Cache

## 📷 预览
![管理后台预览](./docs/images/admin.png)

![用户前台预览](./docs/images/user.png)

## ⚠️ 免责声明

本项目仅供学习交流使用。用户使用本项目产生的一切后果由用户自行承担。

## 🌟 维护声明

本项目目前处于轻度维护状态。我们将：
- 修复严重 Bug 和安全问题
- 审核并合并重要的 Pull Request
- 提供必要的兼容性更新

但是，新功能的开发可能会受到限制。

## 🔔 重要提示

1. 修改后台路径后需重启:
```bash
docker compose restart
```

2. 对于 aaPanel 安装，需重启 Octane 守护进程

## 🤝 贡献

欢迎提交 Issue 和 Pull Request 来帮助改进本项目。

## 📈 Star History

[![Stargazers over time](https://starchart.cc/cedar2025/Xboard.svg)](https://starchart.cc/cedar2025/Xboard)
