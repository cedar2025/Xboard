## Xboard 在 aaPanel 环境下的部署指南

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
- 操作系统: Ubuntu 20.04+ / Debian 10+ (⚠️ 不建议使用 CentOS 7)
- aaPanel 最新版
- PHP 8.2
- MySQL 5.7+
- Redis
- Nginx（任意版本）

## 快速部署

### 1. 安装 aaPanel
```bash
URL=https://www.aapanel.com/script/install_6.0_en.sh && \
if [ -f /usr/bin/curl ];then curl -ksSO "$URL" ;else wget --no-check-certificate -O install_6.0_en.sh "$URL";fi && \
bash install_6.0_en.sh aapanel
```

### 2. 基础环境配置

#### 2.1 安装 LNMP 环境
在 aaPanel 面板中安装：
- Nginx（任意版本）
- MySQL 5.7
- PHP 8.2

#### 2.2 安装 PHP 扩展
必需的 PHP 扩展：
- redis
- fileinfo
- swoole4
- readline
- event

#### 2.3 解除 PHP 禁用函数
需要启用的函数：
- putenv
- proc_open
- pcntl_alarm
- pcntl_signal

### 3. 站点配置

#### 3.1 创建站点
1. 导航至：aaPanel > Website > Add site
2. 填写信息：
   - 域名：填写您的站点域名
   - 数据库：选择 MySQL
   - PHP 版本：选择 8.2

#### 3.2 部署 Xboard
```bash
# 进入站点目录
cd /www/wwwroot/你的域名

# 清理目录
chattr -i .user.ini
rm -rf .htaccess 404.html 502.html index.html .user.ini

# 克隆代码
git clone https://github.com/cedar2025/Xboard.git ./

# 安装依赖
sh init.sh
```

#### 3.3 配置站点
1. 设置运行目录为 `/public`
2. 添加伪静态规则：
```nginx
location /downloads {
}

location / {  
    try_files $uri $uri/ /index.php$is_args$query_string;  
}

location ~ .*\.(js|css)?$
{
    expires      1h;
    error_log off;
    access_log /dev/null; 
}
```

## 详细配置

### 1. 配置守护进程
1. 安装 Supervisor
2. 添加队列守护进程：
   - 名称：`Xboard`
   - 运行用户：`www`
   - 运行目录：站点目录
   - 启动命令：`php artisan horizon`
   - 进程数：1

### 2. 配置计划任务
- 类型：Shell Script
- 任务名：v2board
- 周期：1分钟
- 脚本内容：`php /www/wwwroot/站点目录/artisan schedule:run`

### 3. Octane 配置（可选）
#### 3.1 添加 Octane 守护进程
- 名称：Octane
- 运行用户：www
- 运行目录：站点目录
- 启动命令：`/www/server/php/81/bin/php artisan octane:start --port 7010`
- 进程数：1

#### 3.2 Octane 专用伪静态规则
```nginx
location ~* \.(jpg|jpeg|png|gif|js|css|svg|woff2|woff|ttf|eot|wasm|json|ico)$ {
}

location ~ .* {
    proxy_pass http://127.0.0.1:7010;
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
}
```

## 维护指南

### 版本更新
```bash
# 进入站点目录
cd /www/wwwroot/你的域名

# 执行更新脚本
git fetch --all && git reset --hard origin/master && git pull origin master
sh update.sh

# 如果启用了 Octane，需要重启守护进程
# aaPanel > App Store > Tools > Supervisor > 重启 Octane
```

### 日常维护
- 定期检查日志
- 监控系统资源使用情况
- 定期备份数据库和配置文件

## 故障排查

### 常见问题
1. 修改后台路径需要重启服务才能生效
2. 启用 Octane 后的任何代码修改都需要重启才能生效
3. PHP 扩展安装失败时，请检查 PHP 版本是否正确
4. 数据库连接失败时，请检查数据库配置和权限
