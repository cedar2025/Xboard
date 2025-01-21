## V2board Dev 迁移指南

本指南介绍如何将 V2board Dev（2023/10/27）版本迁移到 Xboard。

⚠️ 请先按照官方指南升级到 2023/10/27 版本后再执行迁移。

### 1. 数据库变更说明

- `v2_order` 表：
  - 新增 `surplus_order_ids` (text, nullable) - 折抵订单

- `v2_plan` 表：
  - 删除 `daily_unit_price` - 影响周期价值
  - 删除 `transfer_unit_price` - 影响流量价值

- `v2_server_hysteria` 表：
  - 删除 `ignore_client_bandwidth` - 影响带宽配置
  - 删除 `obfs_type` - 影响混淆类型配置

### 2. 准备工作

⚠️ 请先完成 Xboard 基础安装（不支持 SQLite）：
- [Docker Compose 部署](./docker-compose安装指南.md)
- [aaPanel + Docker 部署](./aapanel+docker安装指南.md)
- [aaPanel 部署](./aapanel安装指南.md)

### 3. 迁移步骤

#### Docker 环境

```bash
# 1. 停止服务
docker compose down

# 2. 清空数据库
docker compose run -it --rm web php artisan db:wipe

# 3. 导入旧数据库（重要）
# 请手动导入 V2board Dev 的数据库

# 4. 执行迁移
docker compose run -it --rm web php artisan migratefromv2b dev231027
```

#### aaPanel 环境

```bash
# 1. 清空数据库
php artisan db:wipe

# 2. 导入旧数据库（重要）
# 请手动导入 V2board Dev 的数据库

# 3. 执行迁移
php artisan migratefromv2b dev231027
```

### 4. 配置迁移

完成数据迁移后，还需要迁移配置文件：
- [配置迁移指南](./config迁移指南.md)