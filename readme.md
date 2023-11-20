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
- 支持Sqlite安装（代替Mysql，自用用户福音）
- 使用Vue3 + TypeScript + NaiveUI + Unocss + Pinia重构用户前端
- 修复大量BUG

# **系统架构**

- PHP8.1+
- Composer
- MySQL5.7+
- Redis
- Laravel

## 安装 / 更新 / 回滚
> 这里将给你介绍不同方式的 安装、更新、回滚步骤
### 安装前准备
- 安装前你需要自行安装好Mysql数据库（用户量小的可以忽略，使用Sqlite）
- 安装前你需要自行安装好redis
### Docker Compose 方式（推荐） 
#### **安装部署**
1. 安装docker
```
curl -sSL https://get.docker.com | bash
systemctl enable docker
systemctl start docker
```
2. 获取Docker compose 文件
```
git clone -b  docker-compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```
3. 执行数据库安装命令
```
docker compose run -it --rm xboard php artisan xboard:install
```
> 执行这条命令之后，会返回你的后台地址和管理员账号密码（你需要记录下来）  
> 你需要执行下面的 ‘**启动xborad**’ 之后才能访问后台

4. 启动xboard
```
docker compose up -d
```
> 安装完成之后即可访问你的站点
5. 配置nginx代理
> 启动之后网站端口为7001, 你可以配置nginx分流使用80端口
```
location ~ .* {
    proxy_pass http://127.0.0.1:7001;
}
```

#### **更新**
1、 修改版本
```
cd Xboard
vi docker-compose.yaml
```
> 修改docker-compose.yaml 当中image后面的版本号为你需要的版本

2、 更新数据库（可以执行多次都是安全的）
```
docker compose down
docker compose run -it --rm xboard php artisan xboard:update
docker compose up -d
```
> 即可更新成功

### **回滚**
> 需要回滚旧的版本时
1、回滚数据库(不可回滚多次，每一次指定都会回滚到上一个版本)
```
docker compose down
docker compose run -it --rm xboard php artisan xboard:rollback
```
2、回退版本  
```
vi docker-compose.yaml
```
> 修改docker-compose.yaml 当中image后面的版本号为更新前的版本号
3、启动
```
dockcer compose up -d
```

### 从其他版本迁移
#### 数据库迁移
1. 先导入原的数据库。(<span style="color:red;">不要走安装步骤</span>)
2. 手动写好.env 数据库账号密码  
3. 根据你的版本查看对应的迁移指南进行迁移
- v2board dev 23/10/27的版本  [点击跳转迁移指南](./v2b_dev迁移指南.md)
- v2board 1.7.4  [点击跳转迁移指南](./docs/v2b_1.7.4迁移指南.md)
- v2board 1.7.3  [点击跳转迁移指南](./docs/v2b_1.7.3迁移指南.md)
- v2board wyx2685  [点击跳转迁移指南](./docs/v2b_wyx2685迁移指南.md)

#### config/v2board.php 迁移
> xboard将配置储存到数据库了， 不再使用file进行储存，你需要对配置文件进行迁移。
#### docker-compose 环境  
1. 在xboard 目录下创建 config文件夹
2. 复制旧项目的 v2board.php 到config目录
3. 修改docker-compose.yaml 取消下面代码的注释（删除 "#"）
```
  # - ./config/v2board.php:/www/config/v2board.php
```
4. 执行下面的命令即可完成迁移
```
docker compose down
docker compose run -it --rm php artisan migrateFromV2b config 
docker compose up -d
```
#### aapanel 环境
1. 将旧的 ```config/v2board.php``` 文件复制到 xboard的 ```config/v2board.php``` 下
2. 执行下面的命令，即可完成迁移
```
php artisan migrateFromV2b config 
```



### 宝塔方式(aaPanel) （不推荐，太麻烦了）
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
☑️ PHP 8.1  
选择 Fast 快速编译后进行安装。

3. 安装扩展 
> aaPanel 面板 > App Store > 找到PHP 8.1点击Setting > Install extentions选择以下扩展进行安装
- redis
- fileinfo
- swoole5
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
>V2board的系统强依赖队列服务，正常使用V2Board必须启动队列服务。下面以aaPanel中supervisor服务来守护队列服务作为演示。  
1. aaPanel 面板 > App Store > Tools  
2. 找到Supervisor进行安装，安装完成后点击设置 > Add Daemon按照如下填写
- 在 Name 填写 Xboard  
- 在 Run User 选择 www  
- 在 Run Dir 选择 站点目录 在 Start Command 填写 php artisan horizon 在 Processes 填写 1  

>填写后点击Confirm添加即可运行。

9. 配置定时任务#
aaPanel 面板 > Cron。
- 在 Type of Task 选择 Shell Script
- 在 Name of Task 填写 v2board
- 在 Period 选择 N Minutes 1 Minute
- 在 Script content 填写 php /www/wwwroot/路径/artisan schedule:run

根据上述信息添加每1分钟执行一次的定时任务。