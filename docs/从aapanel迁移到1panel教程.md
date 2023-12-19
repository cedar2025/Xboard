**本篇教程适用于原来网站搭建在宝塔或者aapanel上的v2board/xboard,以及新安装xboard。**
---
1.迁移之前，更新至v2board/xboard 分支最新版。

2.登录 aapanel后台> 数据库，点击备份并下载你的旧版数据库。  
v2board用户还需要在 >网站>站点目录，把`config/v2board.php`下载。

## 新安装从这里开始  
3.安装1panel ，安装依赖，不喜欢vim可以用 nano 或者 Finalshell 直接编辑
```
apt install -y curl vim 
```
```
curl -sSL https://resource.fit2cloud.com/1panel/package/quick_start.sh -o quick_start.sh && bash quick_start.sh
```
4.登录到1panel >应用商店，安装 OpenResty、MySql 5.7。  
安装完成后，1panel > 数据库，创建MySQL数据库。  
1panel > 网站，使用 OpenResty 创建一个反向代理到 127.0.0.1:7001 。  
5.回到SSH终端，获取Docker compose 文件
```
git clone https://github.com/cedar2025/Xboard && cd Xboard && cp docker-compose.sample.yaml docker-compose.yaml
```
6. *初始化，用于生成 .env文件*
```
rm -f .env && touch .env && docker compose run -it --rm xboard sh init.sh 
```
7. 迁移用户此时可以导入xboard数据库。  
xboard迁移用户和新安装用户，可以启动容器。
```
docker compose up -d 
```
## 新安装和Xboard迁移教程到此结束，自行设置网站。
v2board用户迁移需要：
1. 停止Xboard
```
docker compose down
```
2. 清空数据库
```
docker compose run -it --rm xboard php artisan db:wipe
```
3.v2board用户根据对应的数据库版本执行迁移命令。
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
4.v2board用户，在1panel >文件，上传v2board.php到Xboard目录下，接着执行命令迁移配置到数据库。
```
docker compose run -it --rm xboard php artisan migrateFromV2b config
```
5.启动容器，否则网站上不去。
```
docker compose up -d
```
6.后续更新。
```
docker compose pull && docker compose up -d 
```
