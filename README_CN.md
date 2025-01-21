# Xboard

<div align="center">

[![Telegram](https://img.shields.io/badge/Telegram-Channel-blue)](https://t.me/XboardOfficial)
![PHP](https://img.shields.io/badge/PHP-8.2+-green.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-blue.svg)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

[English](README.md) | 简体中文

</div>

## 📖 简介

Xboard 是一个基于 Laravel 11 开发的现代化面板系统，专注于提供简洁、高效的用户体验。

## ✨ 特性

- 🚀 基于 Laravel 11 + Octane，性能提升显著
- 🎨 全新设计的管理界面 (React + Shadcn UI)
- 📱 现代化的用户前端 (Vue3 + TypeScript)
- 🐳 开箱即用的 Docker 部署方案
- 🎯 优化的系统架构，提供更好的可维护性

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

> 安装完成后访问：http://服务器IP:7001  
> ⚠️ 请务必保存安装时显示的管理员账号密码

## 📚 使用文档

### 🔄 升级提示
> 🚨 **重要：** 此次版本跨度较大，请严格按照升级文档进行升级，必要时请备份数据库再进行升级。升级跟迁移不是一个东西，请不要混淆。

### 部署教程
- [使用 1Panel 部署](./docs/zh-CN/installation/1panel.md)
- [Docker Compose 部署](./docs/zh-CN/installation/docker-compose.md)
- [使用 aaPanel 部署](./docs/zh-CN/installation/aapanel.md)
- [aaPanel + Docker 部署](./docs/zh-CN/installation/aapanel-docker.md)（推荐）

### 迁移指南
- [从 v2board dev 迁移](./docs/zh-CN/migration/v2board-dev.md)
- [从 v2board 1.7.4 迁移](./docs/zh-CN/migration/v2board-1.7.4.md)
- [从 v2board 1.7.3 迁移](./docs/zh-CN/migration/v2board-1.7.3.md)
- [从 v2board wyx2685 迁移](./docs/zh-CN/migration/v2board-wyx2685.md)

## 🤝 参与贡献

欢迎提交 Issue 和 Pull Request 来帮助改进项目。

## 🛠️ 技术栈

- 后端：Laravel 11 + Octane
- 管理面板：React + Shadcn UI + TailwindCSS
- 用户前端：Vue3 + TypeScript + NaiveUI
- 部署方案：Docker + Docker Compose
- 缓存系统：Redis + Octane Cache

## 📷 界面预览

![管理员后台](./docs/images/admin.png)

![用户前端](./docs/images/user.png)

## ⚠️ 免责声明

本项目仅供学习交流使用，使用本项目造成的任何后果由使用者自行承担。

## 🌟 维护说明

本项目目前处于浅维护状态。我们将：
- 修复关键性bug和安全问题
- 审查并合并重要的pull requests
- 提供必要的兼容性更新

但新功能的开发可能会受到限制。

## 🔔 注意事项

1. 修改后台路径后需要重启：
```bash
docker compose restart
```

2. aaPanel 环境下需要重启 Octane 守护进程

## 🤝 参与贡献

欢迎提交 Issue 和 Pull Request 来帮助改进项目。

## 📈 Star 增长趋势

[![Stargazers over time](https://starchart.cc/cedar2025/Xboard.svg)](https://starchart.cc/cedar2025/Xboard) 