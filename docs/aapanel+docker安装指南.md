## aaPanel + Docker 快速部署指南

本指南介绍如何使用 aaPanel + Docker Compose 部署 Xboard。

### 1. 环境准备

1. 安装 Docker：
```bash
# 安装 Docker
curl -sSL https://get.docker.com | bash

# CentOS 系统需要执行：
systemctl enable docker
systemctl start docker
```

2. 安装 aaPanel：
```bash
curl -sSL https://www.aapanel.com/script/install_6.0_en.sh -o install_6.0_en.sh && \
bash install_6.0_en.sh aapanel
```

### 2. 环境配置

在 aaPanel 中安装 LNMP：
- Nginx（任意版本）
- MySQL 5.7
- ⚠️ 无需安装 PHP 和 Redis

### 3. 部署步骤

1. 添加站点：
   - 进入 aaPanel > Website > Add site
   - 域名：填写你的域名
   - 数据库：选择 MySQL
   - PHP 版本：选择纯静态

2. 安装 Xboard：
```bash
# 进入站点目录
cd /www/wwwroot/你的域名

# 清理目录
chattr -i .user.ini
rm -rf .htaccess 404.html 502.html index.html .user.ini

# 克隆代码
git clone -b new https://github.com/cedar2025/Xboard.git ./

# 准备配置文件
cp compose.sample.yaml compose.yaml

# 安装依赖并初始化
docker compose run -it --rm web sh init.sh
```
> 安装完成后请保存返回的后台地址和管理员账号密码

3. 启动服务：
```bash
docker compose up -d
```

4. 配置反向代理：
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

### 4. 版本更新

```bash
docker compose pull && docker compose up -d
```

### 注意事项

- ⚠️ 请确保防火墙已开启，避免 7001 端口暴露到公网
- 代码修改后需要重启服务才能生效
- 建议配置 SSL 证书以确保安全访问
