## 1Panel 快速部署指南

本指南介绍如何使用 1Panel 部署 Xboard。

### 1. 环境准备

安装 1Panel：
```bash
curl -sSL https://resource.fit2cloud.com/1panel/package/quick_start.sh -o quick_start.sh && \
sudo bash quick_start.sh
```

### 2. 环境配置

1. 在应用商店安装：
   - OpenResty（任意版本）
     - ⚠️ 安装时需要勾选"端口外部访问"以开放防火墙
   - MySQL 5.7（ARM 架构可使用 MariaDB）

2. 创建数据库：
   - 数据库名：`xboard`
   - 用户名：`xboard`
   - 访问权限：所有人(%)
   - 记录数据库密码，后续安装需要使用

### 3. 部署步骤

1. 添加站点：
   - 选择"网站" > "创建网站" > "反向代理"
   - 主域名：填写你的域名
   - 代号：`xboard`
   - 代理地址：`127.0.0.1:7001`

2. 配置反向代理：
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

3. 安装 Xboard：
```bash
# 进入站点目录
cd /opt/1panel/apps/openresty/openresty/www/sites/xboard/index

# 安装 Git（如未安装）
# Ubuntu/Debian
apt update && apt install -y git
# CentOS/RHEL
yum update && yum install -y git

# 克隆代码
git clone -b docker-compose --depth 1 https://github.com/cedar2025/Xboard ./

# 安装依赖并初始化
docker compose run -it --rm web php artisan xboard:install
```
> 安装时选择使用内置 Redis，并输入之前创建的数据库信息
> 安装完成后请保存返回的后台地址和管理员账号密码

4. 启动服务：
```bash
docker compose up -d
```

### 4. 版本更新

```bash
docker compose pull && docker compose up -d
```

### 注意事项

- ⚠️ 请确保防火墙已开启，避免 7001 端口暴露到公网
- 代码修改后需要重启服务才能生效
- 建议配置 SSL 证书以确保安全访问
