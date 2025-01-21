## Docker Compose 快速部署指南

本指南介绍如何使用 Docker Compose 快速部署 Xboard。默认使用 SQLite 数据库，无需额外安装 MySQL。

### 1. 环境准备

安装 Docker:
```bash
curl -sSL https://get.docker.com | bash

# CentOS 系统需要执行：
systemctl enable docker
systemctl start docker
```

### 2. 部署步骤

1. 获取项目文件：
```bash
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```

2. 安装数据库：  

- 快速安装（推荐新手使用）
```bash
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    web php artisan xboard:install
```
- 自定义配置安装（高级用户）
```bash
docker compose run -it --rm web php artisan xboard:install
```
> 安装完成后请保存返回的后台地址和管理员账号密码

3. 启动服务：
```bash
docker compose up -d
```

4. 访问站点：
- 默认端口：7001
- 网站地址：http://服务器IP:7001

### 3. 版本更新

> 💡 重要提示：根据您安装的版本不同，更新命令可能略有差异：
> - 如果您是最近安装的新版本，使用下面的命令：
```bash
cd Xboard
docker compose pull && \
docker compose run -it --rm web php artisan xboard:update && \
docker compose up -d
```
> - 如果您是较早安装的旧版本，需要将命令中的 `web` 改为 `xboard`，即：
```bash
cd Xboard
docker compose pull && \
docker compose run -it --rm xboard php artisan xboard:update && \
docker compose up -d
```
> 🤔 不确定用哪个？可以先尝试使用新版命令，如果报错再使用旧版命令。

### 4. 版本回滚

1. 修改 `docker-compose.yaml` 中的版本号为需要回滚的版本
2. 执行：`docker compose up -d`

### 注意事项

- 如需使用 MySQL，请自行安装并重新部署
- 代码修改后需要重启服务才能生效
- 可以配置 Nginx 反向代理使用 80 端口
