# Xboard New

[English](README.md) | [中文](README_CN.md)

[![Telegram Channel](https://img.shields.io/badge/Telegram-Channel-blue)](https://t.me/XboardOfficial)

## About Xboard
Xboard New is a panel system based on Xboard's secondary development, featuring a rewritten admin interface and optimized system architecture to improve maintainability.

## Disclaimer
This project is personally developed and maintained. I do not guarantee any availability or take responsibility for any consequences of using this software.

## Features
- Upgraded to Laravel 11
- Added Octane support
- Rebuilt admin interface using React + Shadcn UI + TailwindCSS
- Rebuilt user frontend using Vue3 + TypeScript + NaiveUI + Unocss + Pinia
- Using Docker Compose as containerization deployment tool
- Using Docker as containerization tool
- Restructured theme management with theme upload support and active theme exposure
- Using Octane Cache for settings caching
- Optimized system architecture for better maintainability

## System Requirements
- PHP 8.2+
- Composer
- MySQL 5.7+
- Redis
- Laravel
- Octane

## Quick Start
Deploy and experience Xboard quickly using the following commands (based on Docker + SQLite):

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
After installation, visit http://SERVER_IP:7001

> Note: Admin credentials will be displayed during installation, make sure to save them.

## Preview
![Dashboard Preview](./docs/images/dashboard.png)

## Documentation

### Installation
- [1Panel Installation](./docs/zh-CN/installation/1panel.md)
- [Docker Compose Installation](./docs/zh-CN/installation/docker-compose.md)
- [aapanel + Docker Installation](./docs/zh-CN/installation/aapanel-docker.md)
- [aapanel Installation](./docs/zh-CN/installation/aapanel.md)

### Migration
- [v2board dev Migration](./docs/zh-CN/migration/v2board-dev.md)
- [v2board 1.7.4 Migration](./docs/zh-CN/migration/v2board-1.7.4.md)
- [v2board 1.7.3 Migration](./docs/zh-CN/migration/v2board-1.7.3.md)
- [v2board wyx2685 Migration](./docs/zh-CN/migration/v2board-wyx2685.md)
- [Config Migration](./docs/zh-CN/migration/config.md)

### Development
- [Device Limit Design](./docs/zh-CN/development/device-limit.md)
- [Performance Comparison](./docs/zh-CN/development/performance.md)

## Note
> Modifying admin path requires restart to take effect:
```bash
docker compose restart
```
> For aapanel installations, restart the webman daemon process