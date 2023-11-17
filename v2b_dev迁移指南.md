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

### 迁移命令
#### 手动部署(aapanel)
> 如果你是手动(aapanel)部署的，执行以下命令
```
php artisan migratefromv2b dev231027
```

#### docker部署
> 如果你是使用的docker 部署，清执行以下命令
```
docker compose down
docker compose run -it --rm xboard php artisan migratefromv2b dev231027
docker compose up -d
```