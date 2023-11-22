**本篇教程默认你已经安装了1panel，并部署单个Xboard容器运行成功。**

1.在1panel >文件，复制 Xboard 文件夹，重命名为 Xbaord-1 ，并修改`.env`文件。
```
#省略
DB_CONNECTION=mysql
DB_HOST=mysql               
DB_PORT=3306
DB_DATABASE=xboard1     # 新数据库
DB_USERNAME=xboard1     # 账号密码
DB_PASSWORD=root_password      

REDIS_HOST=/run/redis-socket/redis.sock             # 内置Redis不用变 
REDIS_PASSWORD=null 
REDIS_PORT=0
#省略
```

2.1panel>文件，修改 Xboard-1 默认的docker-compose.yaml
```
version: '3'
services:
  xboard-1:
    image: ghcr.io/cedar2025/xboard:latest
    volumes:
      - ./.env:/www/.env
      - ./.docker/.data/:/www/.docker/.data/
      # - ./config/v2board.php:/www/config/v2board.php
      - redis-socket:/run/redis-socket # 挂载socket
    environment:
      - docker=true #用于给安装脚本判断是否为docker环境
    depends_on:
      - redis-1
    network_mode: 1panel-network
    ports:
      - 127.0.0.1:7002:7001 #端口不要跟原来的冲突
  redis-1:
    build: 
      context: .docker/services/redis
    volumes:
      - ./.docker/.data/redis:/data/ # 挂载redis持久化数据
      - redis-socket:/run/redis-socket # 挂载socket
  
```
启动 Xboard-1
```
docker compose up -d
```

3.在1panel>网站，用openresty分别反代`127.0.0.1:7001` ，`127.0.0.1:7002` 两个站点。
