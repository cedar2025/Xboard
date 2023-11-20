# Xboard用户手册（aapanel版）
# 关于Xboard
Xborad是基于V2board二次开发，在性能上和功能上都有大部分增强的**面板

# 免责声明
本项目只是本人个人开发维护，本人不保证任何可用性，也不对使用本软件造成的任何后果负责。
# 捐赠
> 如果本项目帮助到了你，你可以对作者进行捐赠，感谢你的支持  

Tron： TLypStEWsVrj6Wz9mCxbXffqgt5yz3Y4XB
# Xborad 特点 
基于V2board 二次开发，增加了以下特性
- 升级Laravel10
- 适配Laravels  （提升至10+倍并发）
- 适配Webman    （比laravels快50%左右）
- 修改配置从数据库中获取
- 支持Docker部署、分布式部署
- 支持根据用户IP归属地来下发订阅
- 增加Hy2支持
- 增加sing-box下发
- 支持直接从cloudflare获取访问者真实IP
- 支持根据客户端版本自动下发新协议
- 支持线路筛选（订阅地址后面增加 &filter=香港｜美国）
- 使用Vue3 + TypeScript + NaiveUI + Unocss + Pinia重构用户前端
- 修复大量BUG

# **系统架构**

- PHP8.1+
- Composer
- MySQL5.7+
- Redis
- Laravel


### 本文专注于aapanel

### 宝塔方式(aaPanel) 
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
☑️ PHP 8.1#弹窗的最高只有8.0版本，8.1版本自行前往appstore安装  
选择 Fast 快速编译后进行安装。

3. 安装扩展 
> aaPanel 面板 > App Store > 找到PHP 8.1点击Setting > Install extentions选择以下扩展进行安装
- redis
- fileinfo
- swoole4
- readline
- event

4. 解除被禁止函数
> aaPanel 面板 > App Store > 找到PHP 7.4点击Setting > Disabled functions 将以下函数从列表中删除
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
# 删除目录下文件（此时必须在站点目录下进行）
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
#### 数据库迁移

### 迁移的用户请注意！！！！
> init.sh部分输入的管理员邮箱不会生效
执行以下命令清空数据库
```
php artisan db:wipe
```
到aapanel的database页面导入数据库
> 导入完毕执行以下命令
### Dev版本
```
php artisan migratefromv2b dev231027

```
### 1.7.3版本
```
php artisan migratefromv2b 1.7.3
```
### 1.7.4版本
```
php artisan migratefromv2b 1.7.4
```
### wyx2685版本
```
php artisan migratefromv2b wyx2685
```
#### config/v2board.php 迁移
> 将旧的 config/v2board.php 文件复制到 xboard的 config/v2board.php 下
> 执行下面的命令，即可使v2board.php生效
```
php artisan migrateFromV2b config

sh init.sh
```



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
8. 配置定时任务#
aaPanel 面板 > Cron。
- 在 Type of Task 选择 Shell Script
- 在 Name of Task 填写 xboard
- 在 Period 选择 N Minutes 1 Minute
- 在 Script content 填写 php /www/wwwroot/路径/artisan schedule:run
根据上述信息添加每1分钟执行一次的定时任务。

9. 配置守护进程
>V2board的系统强依赖队列服务，正常使用V2Board必须启动队列服务。下面以aaPanel中supervisor服务来守护队列服务作为演示。  
1. aaPanel 面板 > App Store > Tools  
2. 找到Supervisor进行安装，安装完成后点击设置 > Add Daemon按照如下填写
- 在 Name 填写 Xboard  
- 在 Run User 选择 www  
- 在 Run Dir 选择 站点目录 在 Start Command 填写 php artisan horizon 在 Processes 填写 1  

>填写后点击Confirm添加即可运行。


