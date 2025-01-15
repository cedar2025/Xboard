## aaPanel 快速部署指南

本指南介绍如何使用 aaPanel 部署 Xboard。

⚠️ 不建议在 CentOS 7 上部署，可能会遇到兼容性问题。

### 1. 环境准备

安装 aaPanel：
```bash
URL=https://www.aapanel.com/script/install_6.0_en.sh && \
if [ -f /usr/bin/curl ];then curl -ksSO "$URL" ;else wget --no-check-certificate -O install_6.0_en.sh "$URL";fi && \
bash install_6.0_en.sh aapanel
```

### 2. 环境配置

1. 在 aaPanel 中安装 LNMP：
   - Nginx（任意版本）
   - MySQL 5.7
   - PHP 8.2

2. 安装 PHP 扩展：
   - redis
   - fileinfo
   - swoole4
   - readline
   - event

3. 解除 PHP 禁用函数：
   - putenv
   - proc_open
   - pcntl_alarm
   - pcntl_signal

### 3. 部署步骤

1. 添加站点：
   - 进入 aaPanel > Website > Add site
   - 填写域名
   - 数据库选择 MySQL
   - PHP 版本选择 8.1

2. 安装 Xboard：
```bash
# 进入站点目录
cd /www/wwwroot/你的域名

# 清理目录
chattr -i .user.ini
rm -rf .htaccess 404.html 502.html index.html .user.ini

# 克隆代码
git clone -b new https://github.com/cedar2025/Xboard.git ./

# 安装依赖
sh init.sh
```

3. 配置站点：
   - 设置运行目录为 `/public`
   - 配置伪静态规则：
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

4. 配置守护进程：
   - 安装 Supervisor
   - 添加队列守护进程：
     - 名称：`Xboard`
     - 运行用户：`www`
     - 运行目录：站点目录
     - 启动命令：`php artisan horizon`
     - 进程数：1

5. 添加计划任务：
   - 类型：Shell Script
   - 任务名：v2board
   - 周期：1分钟
   - 脚本内容：`php /www/wwwroot/站点目录/artisan schedule:run`

### 4. 开启 Octane（可选）
1. 添加 Octane 守护进程：
   - 名称：Octane
   - 运行用户：www
   - 运行目录：站点目录
   - 启动命令：`/www/server/php/81/bin/php artisan octane:start --port 7010`
   - 进程数：1

2. 更新伪静态规则：
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

### 5. 版本更新

```bash
# 更新代码
cd /www/wwwroot/你的域名
sh update.sh

# 如果启用了 Octane，需要重启守护进程
# aaPanel > App Store > Tools > Supervisor > 重启 Octane
```

### 注意事项

- 修改后台路径需要重启服务才能生效
- 启用 octane 后的任何代码修改都需要重启才能生效
