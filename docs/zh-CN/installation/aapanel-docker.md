# Xboard 在 aaPanel + Docker 环境下的部署指南

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
- aaPanel 最新版
- Docker 和 Docker Compose
- Nginx（任意版本）
- MySQL 5.7+

## 快速部署

### 1. 安装 aaPanel
```bash
curl -sSL https://www.aapanel.com/script/install_6.0_en.sh -o install_6.0_en.sh && \
bash install_6.0_en.sh aapanel
```

### 2. 基础环境配置

#### 2.1 安装 Docker
```bash
# 安装 Docker
curl -sSL https://get.docker.com | bash

# CentOS 系统需要执行：
systemctl enable docker
systemctl start docker
```

#### 2.2 安装必要组件
在 aaPanel 面板中安装：
- Nginx（任意版本）
- MySQL 5.7
- ⚠️ 无需安装 PHP 和 Redis

### 3. 站点配置

#### 3.1 创建站点
1. 导航至：aaPanel > Website > Add site
2. 填写信息：
   - 域名：填写您的站点域名
   - 数据库：选择 MySQL
   - PHP 版本：选择纯静态

#### 3.2 部署 Xboard
```bash
# 进入站点目录
cd /www/wwwroot/你的域名

# 清理目录
chattr -i .user.ini
rm -rf .htaccess 404.html 502.html index.html .user.ini

# 克隆代码
git clone https://github.com/cedar2025/Xboard.git ./

# 准备配置文件
cp compose.sample.yaml compose.yaml

# 安装依赖并初始化
docker compose run -it --rm web sh init.sh
```
> ⚠️ 请妥善保存安装完成后返回的后台地址和管理员账号密码

#### 3.3 启动服务
```bash
docker compose up -d
```

#### 3.4 配置反向代理
在站点配置中添加以下内容：
```nginx
location / {
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

## 维护指南

### 版本更新

> 💡 重要提示：根据您安装的版本不同，更新命令可能略有差异：
> - 如果您是最近安装的新版本，使用下面的命令：
```bash
docker compose pull && \
docker compose run -it --rm web sh update.sh && \
docker compose up -d
```
> - 如果您是较早安装的旧版本，需要将命令中的 `web` 改为 `xboard`，即：
```bash
git config --global --add safe.directory $(pwd)
git fetch --all && git reset --hard origin/master && git pull origin master
docker compose pull && \
docker compose run -it --rm xboard sh update.sh && \
docker compose up -d
```
> 🤔 不确定用哪个？可以先尝试使用新版命令，如果报错再使用旧版命令。

### 日常维护
- 定期检查日志: `docker compose logs`
- 监控系统资源使用情况
- 定期备份数据库和配置文件

## 故障排查
