# Xboard 在 1Panel 环境下的部署指南

## 目录
1. [环境要求](#环境要求)
2. [快速部署](#快速部署)
3. [详细配置](#详细配置)
4. [维护指南](#维护指南)
5. [故障排查](#故障排查)

## 环境要求

### 硬件配置
- CPU: 1核心及以上
- 内存: 2GB及以上
- 硬盘: 10GB及以上可用空间

### 软件要求
- 操作系统: Ubuntu 20.04+ / CentOS 7+ / Debian 10+
- 1Panel 最新版
- Docker 和 Docker Compose

## 快速部署

### 1. 安装 1Panel
```bash
curl -sSL https://resource.fit2cloud.com/1panel/package/quick_start.sh -o quick_start.sh && \
sudo bash quick_start.sh
```

### 2. 基础环境配置

#### 2.1 安装必要组件
在 1Panel 应用商店中安装：
- OpenResty
  - ✅ 勾选"端口外部访问"选项
  - 📝 记录安装路径
- MySQL 5.7
  > ARM 架构设备推荐使用 MariaDB 替代

#### 2.2 创建数据库
1. 数据库配置：
   - 名称: `xboard`
   - 用户: `xboard`
   - 访问权限: 所有主机(%)
   - 🔐 请安全保存数据库密码

### 3. 站点配置

#### 3.1 创建站点
1. 导航至：网站 > 创建网站 > 反向代理
2. 填写信息：
   - 域名: 您的站点域名
   - 代号: `xboard`
   - 代理地址: `127.0.0.1:7001`

#### 3.2 配置反向代理
在站点配置中添加以下内容：
```nginx
location ^~ / {
    proxy_pass http://127.0.0.1:7001;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Real-PORT $remote_port;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header Server-Protocol $server_protocol;
    proxy_set_header Server-Name $server_name;
    proxy_set_header Server-Addr $server_addr;
    proxy_set_header Server-Port $server_port;
    proxy_cache off;
}
```

### 4. 部署 Xboard

#### 4.1 获取源码
```bash
# 进入站点目录
cd /opt/1panel/apps/openresty/openresty/www/sites/xboard/index

# 安装 Git（如需要）
## Ubuntu/Debian
apt update && apt install -y git
## CentOS/RHEL
yum update && yum install -y git

# 克隆项目
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard ./
```

#### 4.2 配置 Docker Compose
创建或修改 `docker-compose.yml`:
```yaml
services:
  web:
    image: ghcr.io/cedar2025/xboard:latest
    volumes:
      - ./.docker/.data/redis/:/data/
      - ./.env:/www/.env
      - ./.docker/.data/:/www/.docker/.data
      - ./storage/logs:/www/storage/logs
      - ./storage/theme:/www/storage/theme
    environment:
      - docker=true
    depends_on:
      - redis
    command: php artisan octane:start --host=0.0.0.0 --port=7001
    restart: on-failure
    ports:
      - 7001:7001
    networks:
      - 1panel-network

  horizon:
    image: ghcr.io/cedar2025/xboard:latest
    volumes:
      - ./.docker/.data/redis/:/data/
      - ./.env:/www/.env
      - ./.docker/.data/:/www/.docker/.data
      - ./storage/logs:/www/storage/logs
    restart: on-failure
    command: php artisan horizon
    networks:
      - 1panel-network
    depends_on:
      - redis

  redis:
    image: redis:7-alpine
    command: redis-server --unixsocket /data/redis.sock --unixsocketperm 777 --save 900 1 --save 300 10 --save 60 10000
    restart: unless-stopped
    networks:
      - 1panel-network
    volumes:
      - ./.docker/.data/redis:/data
```

#### 4.3 初始化安装
```bash
# 安装并初始化
docker compose run -it --rm web php artisan xboard:install
```

⚠️ 重要配置说明：
1. 数据库配置
   - 数据库地址：填写 `mysql`（注意不是 localhost 或 127.0.0.1）
   - 数据库端口：`3306`
   - 数据库名称：`xboard`（之前创建的数据库名）
   - 数据库用户：`xboard`（之前创建的用户名）
   - 数据库密码：填写之前保存的密码

2. Redis 配置
   - 选择使用内置 Redis
   - 无需额外配置

3. 管理员信息
   - 请妥善保存安装完成后返回的管理员账号和密码
   - 记录后台访问地址

完成配置后启动服务：
```bash
docker compose up -d
```

## 维护指南

### 版本更新
```bash
docker compose pull && \
docker compose run -it --rm web php artisan xboard:update && \
docker compose up -d
```

### 日常维护
- 定期检查日志: `docker compose logs`
- 监控系统资源使用情况
- 定期备份数据库和配置文件

## 故障排查

### 常见问题
1. 无法访问网站
   - 检查防火墙配置
   - 验证端口是否正确开放
   - 检查 Docker 容器状态

2. 数据库连接失败
   - 验证数据库凭据
   - 检查数据库服务状态
   - 确认网络连接

### 安全建议
- ⚠️ 确保 7001 端口不对外开放
- 定期更新系统和组件
- 配置 SSL 证书实现 HTTPS 访问
- 使用强密码策略
- 定期备份数据

### 获取帮助
- 查看官方文档
- 访问项目 GitHub 仓库
- 加入社区讨论组
