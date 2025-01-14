# Xboard New

[English](README.md) | [中文](README_CN.md)

# 关于Xboard
Xboard New是基于Xboard二次开发，重写后台管理并优化系统架构的面板，提升可维护性。

# 免责声明
本项目只是本人个人学习开发并维护，本人不保证任何可用性，也不对使用本软件造成的任何后果负责。

# Xboard New 特点 
- 升级Laravel11
- 增加Octane支持
- 使用React + Shadcn UI + TailwindCSS重构后台管理
- 使用Vue3 + TypeScript + NaiveUI + Unocss + Pinia重构用户前端
- 使用Docker Compose作为容器化部署工具
- 使用Docker作为容器化部署工具
- 重构主题管理，增加主题上传，并且只暴露激活主题
- 使用Octane Cache作为设置的缓存
- 优化系统架构，提升可维护性

# 系统要求
- PHP8.2+
- Composer
- MySQL5.7+
- Redis
- Laravel
- Octane

## 快速体验
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

> 提示：安装过程中会显示管理员账号密码，请务必保存。

## 页面展示
![示例图片](./docs/images/dashboard.png)

## 安装 / 更新 / 回滚
你可以点击查看下列方式的安装、更新步骤：
- [1panel 部署](./docs/1panel安装指南.md)
- [Docker Compose 纯命令行快速部署](./docs/docker-compose安装指南.md)
- [aapanel + Docker Compose (推荐)](./docs/aapanel+docker安装指南.md)
- [aapanel 部署](./docs/aapanel安装指南.md)

### 从其他版本迁移
#### 数据库迁移
**根据你的版本查看对应的迁移指南进行迁移**
- v2board dev 23/10/27的版本 [点击跳转迁移指南](./docs/v2b_dev迁移指南.md)
- v2board 1.7.4 [点击跳转迁移指南](./docs/v2b_1.7.4迁移指南.md)
- v2board 1.7.3 [点击跳转迁移指南](./docs/v2b_1.7.3迁移指南.md)
- v2board wyx2685 [点击跳转迁移指南](./docs/v2b_wyx2685迁移指南.md)

### 注意
> 修改后台路径需要重启才能生效：
```bash
docker compose restart
```
> 如果是aapanel安装则需要重启 webman守护进程 