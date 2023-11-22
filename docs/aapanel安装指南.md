## aapanel部署指南
> 本文将教你如何使用aapanel进行部署
### 安装
1. 安装aaPanel 

如果是Centos系统
```
yum install -y wget && wget -O install.sh http://www.aapanel.com/script/install_6.0_en.sh && bash install.sh aapanel
```
如果是Ubuntu/Deepin系统
```
wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh && sudo bash install.sh aapanel
``` 
如果是Debian 系统
```
wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh && bash install.sh aapanel
```

安装完成后我们登陆 aaPanel 进行环境的安装。
2. 选择使用LNMP的环境安装方式勾选如下信息  
☑️ Nginx 任意版本  
☑️ MySQL 5.7  
☑️ PHP 8.1 （如果没看到8.1先不选，去App Store安装）
选择 Fast 快速编译后进行安装。

3. 安装扩展 
> aaPanel 面板 > App Store > 找到PHP 8.1点击Setting > Install extentions选择以下扩展进行安装
- redis
- fileinfo
- swoole4
- readline
- event

4. 解除被禁止函数
> aaPanel 面板 > App Store > 找到PHP 8.1点击Setting > Disabled functions 将以下函数从列表中删除
- putenv
- proc_open
- pcntl_alarm
- pcntl_signal

5. 添加站点  
>aaPanel 面板 > Website > Add site。  
>>在 Domain 填入你指向服务器的域名  
>>在 Database 选择MySQL  
>>在 PHP Verison 选择PHP-81 

6. 安装 Xborad  
>通过SSH登录到服务器后访问站点路径如：/www/wwwroot/你的站点域名。
>以下命令都需要在站点目录进行执行。
```
# 删除目录下文件
chattr -i .user.ini
rm -rf .htaccess 404.html index.html .user.ini
```
> 执行命令从 Github 克隆到当前目录。
```
git clone https://github.com/cedar2025/Xboard.git ./
```
> 执行命令安装依赖包以及V2board
```
sh init.sh
```
> 根据提示完成安装
7. 配置站点目录及伪静态
> 添加完成后编辑添加的站点 > Site directory > Running directory 选择 /public 保存。  
> 添加完成后编辑添加的站点 > URL rewrite 填入伪静态信息。
```
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
8. 配置守护进程
>Xboard的系统强依赖队列服务，正常使用XBoard必须启动队列服务。下面以aaPanel中supervisor服务来守护队列服务作为演示。  
- 1️⃣. aaPanel 面板 > App Store > Tools  
- 2️⃣. 找到Supervisor进行安装，安装完成后点击设置 > Add Daemon按照如下填写
- - 在 Name 填写 Xboard  
- - 在 Run User 选择 www  
- - 在 Run Dir 选择 站点目录 在 Start Command 填写 php artisan horizon 在 Processes 填写 1  

>填写后点击Confirm添加即可运行。

9. 配置定时任务#
aaPanel 面板 > Cron。
- 在 Type of Task 选择 Shell Script
- 在 Name of Task 填写 v2board
- 在 Period 选择 N Minutes 1 Minute
- 在 Script content 填写 php /www/wwwroot/路径/artisan schedule:run

根据上述信息添加每1分钟执行一次的定时任务。


### 开启webman
> 在上述安装的基础上开启webman提高性能

1. 配置php.ini
> 通过SSH登录到服务器后访问站点路径如：/www/wwwroot/你的站点域名。
```
cp /www/server/php/81/etc/php.ini cli-php.ini

sed -i 's/^disable_functions[[:space:]]*=[[:space:]]*.*/disable_functions=header,header_remove,headers_sent,http_response_code,setcookie,session_create_id,session_id,session_name,session_save_path,session_status,session_start,session_write_close,session_regenerate_id,set_time_limit/g' cli-php.ini

```
2. 添加守护进程
>下面以aaPanel中supervisor服务来守护队列服务作为演示。  
- 1️⃣. aaPanel 面板 > App Store > Tools 
- 2️⃣. 找到Supervisor进行安装，安装完成后点击设置 > Add Daemon按照如下填写
- - 在 Name 填写 webman
- - 在 Run User 选择 www  
- - 在 Run Dir 选择 站点目录 在 Start Command 填写 /www/server/php/81/bin/php -c cli-php.ini webman start 在 Processes 填写 1  
>填写后点击Confirm添加即可运行。

3. 添加反向代理
> 站点设置 > 反向代理 > 添加反向代理
>> 在 **代理名称** 填入 Xboard  
>> 在 **目标URL** 填入 ```http://127.0.0.1:7010```
> 添加添加后 需要点击该反向代理的```配置文件```编辑反向代理规则做以下修改

```
location ~* \.(jpg|jpeg|png|gif|js|css|svg|woff2|woff|ttf|eot|wasm|json|ico)$ {}
location ^~ /
{
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header REMOTE-HOST $remote_addr;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection $connection_upgrade;
    proxy_http_version 1.1;
    # proxy_hide_header Upgrade;
    add_header X-Cache $upstream_cache_status;

    #Set Nginx Cache
    set $static_filetmMCG7Tk 0;
    if ( $uri ~* "\.(gif|png|jpg|css|js|woff|woff2)$" )
    {
    	set $static_filetmMCG7Tk 1;
    	expires 1m;
        }
    if ( $static_filetmMCG7Tk = 0 )
    {
    add_header Cache-Control no-cache;
    }
}
```

> 在此你的webman已经成功部署了

### 更新

1. 更新代码
> 通过SSH登录到服务器后访问站点路径如：/www/wwwroot/你的站点域名。
```
sh update.sh
```
2. 重启webman 守护进程(如果启用了webman)
- 1️⃣. aaPanel 面板 > App Store > Tools 
- 2️⃣. 找到Supervisor点击设置，找到名为webman的守护进程点击重启即可


