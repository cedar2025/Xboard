**本篇教程默认你具备一定的Linux基础操作，原来网站搭建在宝塔或者aapanel上。**
---
1.迁移之前，登录aapanel后台>网站>站点目录，把`config/v2board.php`下载，复制 `网站路径`，SSH连接到v2board的VPS。
```
cd /www/wwwroot/你的站点域名
bash update.sh
```
更新至v2board 1.7.4 / dev 或者wyx2685 分支最新版。

2.在aaPanel 面板 > 数据库，点击备份你的旧版数据库。

3.安装并登录到1panel >应用商店，安装 OpenResty、MySql。
```
apt install curl vim -y # 安装依赖，不喜欢vim可以用 nano 或者 Finalshell 直接编辑
```
```
curl -sSL https://resource.fit2cloud.com/1panel/package/quick_start.sh -o quick_start.sh && bash quick_start.sh
```

4.登录到1panel > 数据库，创建MySQL数据库，上传并导入v2board数据库备份。

5.回到SSH终端，获取Docker compose 文件
```
git clone -b  docker-compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```
在1panel >文件，上传v2board.php到Xboard目录下。

6.编辑`.env` 文件,填写Mysql数据库信息
```
vim .env #编辑环境变量
```
```
APP_NAME=XBoard
APP_ENV=local
APP_KEY=base64:PZXk5vTuTinfeEVG5FpYv2l6WEhLsyvGpiWK7IgJJ60=
APP_DEBUG=false
APP_URL=http://localhost

ADMIN_SETTING_CACHE=60 #设置缓存时间（单位秒）
#LaravelS配置
LARAVELS_LISTEN_IP=0.0.0.0
LARAVELS_LISTEN_PORT=80
LARAVELS_HANDLE_STATIC=true
LARAVELS_MAX_REQUEST=1000
LARAVELS_WORKER_NUM=2
LARAVELS_TIMER=true

APP_RUNNING_IN_CONSOLE=true

LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=mysql 				# 桥接模式连接容器可以写成mysql
DB_PORT=3306
DB_DATABASE=xboard
DB_USERNAME=xboard
DB_PASSWORD=root_password 		# 点击 1panel>数据库>Mysql 复制密码

REDIS_HOST=/run/redis-socket/redis.sock 			# 内置Redis 
REDIS_PASSWORD=null 
REDIS_PORT=0

#默认将队列驱动和缓存驱动都修改为了redis，请务必安装redis
BROADCAST_DRIVER=log
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME=null
MAILGUN_DOMAIN=
MAILGUN_SECRET=

# 用于阻止重复安装
INSTALLED=true
```

7.修改docker-compose.yaml
```
vim docker-compose.yaml  
```
```
version: '3'
services:
  xboard:
    image: ghcr.io/cedar2025/xboard:latest
    volumes:
      - ./.env:/www/.env
      - ./.docker/.data/:/www/.docker/.data/
      # - ./config/v2board.php:/www/config/v2board.php
      - redis-socket:/run/redis-socket # 挂载socket
    environment:
      - docker=true #用于给安装脚本判断是否为docker环境
    depends_on:
      - redis
    network_mode: 1panel-network
    ports:
      - 127.0.0.1:7001:7001
  redis:
    build: 
      context: .docker/services/redis
    volumes:
      - ./.docker/.data/redis:/data/ # 挂载redis持久化数据
      - redis-socket:/run/redis-socket # 挂载socket
volumes:
  redis-socket:
networks:
  persist:
    external: true
```
8.根据v2board数据库版本执行对应的迁移数据库命令
```
docker compose run -it --rm xboard php artisan migratefromv2b 1.7.3
```
```
docker compose run -it --rm xboard php artisan migratefromv2b 1.7.4
```
```
docker compose run -it --rm xboard php artisan migratefromv2b dev231027
```
```
docker compose run -it --rm xboard php artisan migratefromv2b wyx2685
```
9.执行迁移配置命令
```
docker compose run -it --rm xboard php artisan migrateFromV2b config
```
10.启动
```
docker compose up -d
```
11.在Xboard目录下停止并更新后启动。
```
docker compose down && docker compose pull && docker compose up -d 
```
