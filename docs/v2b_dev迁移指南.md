## V2borad Dev版本迁移指南
> 请先按照官方升级指导升级到 2023/10/27的版本后再执行迁移操作

### 迁移脚本会对你的数据库做以下更改
- v2_order
    - 添加 `surplus_order_ids` 字段 类型 text nullable 折抵订单
- v2_plan（影响功能：周期价值、 流量价值）
     - 删除 `daily_unit_price`  字段
     - 删除 `transfer_unit_price` 字段
- v2_server_hysteria （影响：Ignore Client Bandwidth 配置和混淆类型配置）
     - 删除 `ignore_client_bandwidth` 字段
     - 删除 `obfs_type` 字段

## 迁移之前
迁移之前你需要执行正常安装步骤(记得不可选择Sqlite)
> sqlite迁移请自行学习相关知识  
- [Docker Compose 纯命令行快速部署](./docs/docker-compose安装指南.md)
- [aapanel + Docker Compose](./docs/aapanel+docker安装指南.md)
- [aapanel 部署](./docs/)

## 开始迁移
> 针对docker与非docker用户提供不同的迁移步骤，你根据你的安装环境选择其一即可。

### docker 环境
> 以下命令需要你打开SSH进入到项目目录进行执行 
1. 停止Xboard
```
docker compose down
```
2. 清空数据库
```
docker compose run -it --rm xboard php artisan db:wipe
```
3. 导入旧数据库<span style="color:red">(重要)</span>数据库
>导入你dev v2board的数据库到当前数据库当中

4. 执行迁移命令
```
docker compose run -it --rm xboard php artisan migratefromv2b dev231027
```
## aapanel 环境
1. 清空数据库
```
php artisan db:wipe
```
2. 导入旧数据库<span style="color:red">(重要)</span>数据库
>导入你dev v2board的数据库到当前数据库当中

3. 执行迁移命令
```
php artisan migratefromv2b dev231027
```

> 上述迁移完成之后你需要进行 配置文件迁移
## config/v2board.php 配置文件迁移 [点击查看步骤](./config迁移指南.md)
> xboard将配置储存到数据库， 不再使用file进行储存，你需要对配置文件进行迁移。