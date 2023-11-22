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
docker compose run -it --rm xboard php artisan migrateFromV2b config 
docker compose up -d
```
#### aapanel 环境
1. 将旧的 ```config/v2board.php``` 文件复制到 xboard的 ```config/v2board.php``` 下
2. 执行下面的命令，即可完成迁移
```
php artisan migrateFromV2b config 
```
### aapanel + docker 环境
1. 将旧的 ```config/v2board.php``` 文件复制到 xboard的 ```config/v2board.php``` 下
2. 执行下面的命令，即可完成迁移
```
docker compose down
docker compose run -it --rm xboard php artisan migrateFromV2b config
docker compose up -d
```

## 注意
> 修改后台路径需要重启才能生效
```
docker compose restart
```
> 如果是是aapanel安装则需要重启 webman守护进程
