# Xboard New

[English](README.md) | [中文](README_CN.md)

[![Telegram 频道](https://img.shields.io/badge/Telegram-Channel-blue)](https://t.me/XboardOfficial)

## 关于 Xboard
Xboard New 是基于 Xboard 二次开发的面板系统，重写了管理界面，优化了系统架构，提高了可维护性。

## 免责声明
本项目为个人开发维护，不保证可用性，使用本软件造成的任何后果由使用者自行承担。

## 特性
- 升级到 Laravel 11
- 添加 Octane 支持
- 使用 React + Shadcn UI + TailwindCSS 重构管理界面
- 使用 Vue3 + TypeScript + NaiveUI + Unocss + Pinia 重构用户前端
- 使用 Docker Compose 作为容器化部署工具
- 使用 Docker 作为容器化工具
- 重构主题管理，支持主题上传和主题暴露
- 使用 Octane Cache 进行设置缓存
- 优化系统架构，提高可维护性

## 系统要求
- PHP 8.2+
- Composer
- MySQL 5.7+
- Redis
- Laravel
- Octane

## 快速开始
使用以下命令快速部署并体验 Xboard（基于 Docker + SQLite）：

```bash
git clone -b compose-new --depth 1 https://github.com/cedar2025/Xboard && \
cd Xboard && \
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    web php artisan xboard:install && \
docker compose up -d
```
安装完成后访问 http://服务器IP:7001

> 注意：管理员账号密码会在安装时显示，请务必保存。

## 预览
![仪表盘预览](./docs/images/dashboard.png)

## 文档

### 安装指南
- [1Panel 部署教程](./docs/zh-CN/installation/1panel.md)
- [Docker Compose 快速部署](./docs/zh-CN/installation/docker-compose.md)
- [aapanel + Docker 部署（推荐）](./docs/zh-CN/installation/aapanel-docker.md)
- [aapanel 部署教程](./docs/zh-CN/installation/aapanel.md)

### 迁移指南
- [v2board dev 版本迁移](./docs/zh-CN/migration/v2board-dev.md)
- [v2board 1.7.4 迁移](./docs/zh-CN/migration/v2board-1.7.4.md)
- [v2board 1.7.3 迁移](./docs/zh-CN/migration/v2board-1.7.3.md)
- [v2board wyx2685 迁移](./docs/zh-CN/migration/v2board-wyx2685.md)
- [配置迁移指南](./docs/zh-CN/migration/config.md)

### 开发文档
- [在线设备限制设计](./docs/zh-CN/development/device-limit.md)
- [性能对比报告](./docs/zh-CN/development/performance.md)

## 注意事项
> 修改后台路径需要重启才能生效：
```bash
docker compose restart
```
> 对于 aapanel 安装，需要重启 webman 守护进程 