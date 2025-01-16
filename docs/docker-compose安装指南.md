## Docker-Compose 快速部署指南

### 环境要求
- Docker (最新稳定版)
- 至少 1GB 可用内存
- 至少 10GB 可用磁盘空间
- 系统支持: Linux/macOS/Windows
- 开放端口: 7001 (默认)

### 部署步骤

#### 1. 安装 Docker
```bash
# 安装 Docker
curl -sSL https://get.docker.com | bash

# CentOS 系统需要执行以下命令启动 Docker
systemctl enable docker
systemctl start docker
```

#### 2. 获取部署文件
```bash
git clone -b docker-compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```

#### 3. 初始化安装
> 提供两种安装方式，选择其一即可：

**方式一：快速安装** (推荐)
```bash
# 使用 SQLite + Docker内置Redis
docker compose run -it --rm \
    -e enable_sqlite=true \
    -e enable_redis=true \
    -e admin_account=admin@demo.com \
    xboard php artisan xboard:install
```

**方式二：自定义安装**
```bash
# 根据提示自定义配置
docker compose run -it --rm xboard php artisan xboard:install
```

> **重要提示：** 
> - 安装完成后会显示后台地址和管理员账号密码，请务必保存
> - 如需使用 MySQL，请先自行安装并配置 MySQL 后再部署

#### 4. 启动服务
```bash
docker compose up -d
```

#### 5. 访问站点
- 网站地址：`http://服务器IP:7001`
- 后台地址：安装时提供的地址

### 更新指南

#### 方式一：快速更新（保持最新版本）
```bash
cd Xboard
docker compose pull
docker compose down
docker compose run -it --rm xboard php artisan xboard:update
docker compose up -d
```

#### 方式二：更新至指定版本
1. 修改版本号
```bash
# 编辑 docker-compose.yaml，修改 image 的版本号
vi docker-compose.yaml
```

2. 执行更新
```bash
docker compose pull
docker compose down
docker compose run -it --rm xboard php artisan xboard:update
docker compose up -d
```

### 版本回滚
```bash
# 1. 修改 docker-compose.yaml 中的版本号为目标版本
vi docker-compose.yaml

# 2. 重启服务
docker compose up -d
```

### 常见问题

1. **端口配置**
- 默认端口为 7001
- 可通过 Nginx 反向代理使用 80/443 端口
- 如需修改端口，请编辑 docker-compose.yaml

2. **数据持久化**
- 数据默认存储在 ./data 目录
- 建议定期备份 data 目录

3. **性能优化**
- 启用 webman 后的代码修改需要重启服务才能生效
- 可根据实际需求调整容器资源限制

### 安全建议
1. 及时更新到最新版本
2. 修改默认管理员账号
3. 使用强密码
4. 建议配置 SSL 证书
5. 定期备份数据

### 技术支持
- GitHub Issues: https://github.com/cedar2025/Xboard/issues
- 官方文档：[文档链接]
