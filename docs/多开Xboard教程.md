**本篇教程默认你已经安装了1panel，并部署单个Xboard容器运行成功。**

1.在/opt/1panel/apps/redis/redis目录新建一个redis1文件夹，并复制data、conf、logs进去。

2.在1panel>容器>编排，修改Redis默认的docker-compose.yaml
```
networks:
    1panel-network:
        external: true
services:
    redis0:
        image: redis:7.2.3
        container_name: redis0
        networks:
            - 1panel-network
        ports:
            - 127.0.0.1:6379:6379
        restart: always
        volumes:
            - ./data:/data
            - ./conf/redis.conf:/etc/redis/redis.conf
            - ./logs:/logs
        command: redis-server --requirepass root_password
    redis1:
        image: redis:7.2.3
        container_name: redis1
        networks:
            - 1panel-network
        ports:
            - 127.0.0.1:6378:6379
        restart: always
        volumes:
            - /opt/1panel/apps/redis/redis/redis1/data:/data
            - /opt/1panel/apps/redis/redis/redis1/conf/redis.conf:/etc/redis/redis.conf
            - /opt/1panel/apps/redis/redis/redis1/logs:/logs
        command: redis-server --requirepass root_password    
version: "3"
```
改完重启，应该有两个Redis启动了,看日志是否报错。

3.1panel>文件，在Xbaord目录修改`.env`文件。
```
DB_HOST=mysql 
#省略  
REDIS_HOST=redis 
```
4.在Xbaord目录新建一个xbaord1文件夹，并创建第二个容器的`.env`文件。
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
DB_HOST=mysql         # 桥接模式连接容器可以写成mysql
DB_PORT=3306
DB_DATABASE=xboard
DB_USERNAME=xboard
DB_PASSWORD=root_password     # 点击 1panel>数据库>Mysql 复制密码

REDIS_HOST=redis1      # 桥接模式连接容器可以写成redis
REDIS_PASSWORD=root_password  # 点击 1panel>数据库>redis 连接信息 复制密码
REDIS_PORT=6378        # Redis1的端口需要修改

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

5.1panel>容器>编排，修改Xboard默认的docker-compose.yaml
```
version: '3'
services:
  xboard:
    image: ghcr.io/cedar2025/xboard:latest
    volumes:
      - ./.env:/www/.env
      - ./.docker/.data/:/www/.docker/.data/
      # - ./config/v2board.php:/www/config/v2board.php
    network_mode: 1panel-network
    ports:
      - 127.0.0.1:7001:7001

  xboard1:
    image: ghcr.io/cedar2025/xboard:latest
    volumes:
      - ./xboard1/.env:/www/.env
      - ./xboard1/.docker/.data/:/www/.docker/.data/
      # - ./config1/v2board.php:/www/config/v2board.php
    network_mode: 1panel-network
    ports:
      - 127.0.0.1:7002:7001
networks:
  persist:
    external: true
```
改完重启，应该有两个Xboard启动了。

6.在用openresty分别反代`127.0.0.1:7001` ，`127.0.0.1:7002` 两个站点。