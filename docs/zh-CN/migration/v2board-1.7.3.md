## V2board 1.7.3 迁移指南

本指南介绍如何将 V2board 1.7.3 版本迁移到 Xboard。

### 1. 数据库变更说明

- `v2_stat_order` 表更名为 `v2_stat`：
  - `order_amount` → `order_total`
  - `commission_amount` → `commission_total`
  - 新增字段：
    - `paid_count` (integer, nullable)
    - `paid_total` (integer, nullable)
    - `register_count` (integer, nullable)
    - `invite_count` (integer, nullable)
    - `transfer_used_total` (string(32), nullable)

- 新增数据表：
  - `v2_log`
  - `v2_server_hysteria`
  - `v2_server_vless`

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
# 请手动导入 V2board 1.7.3 的数据库

# 4. 执行迁移
docker compose run -it --rm web php artisan migratefromv2b 1.7.3
```

#### aaPanel 环境

```bash
# 1. 清空数据库
php artisan db:wipe

# 2. 导入旧数据库（重要）
# 请手动导入 V2board 1.7.3 的数据库

# 3. 执行迁移
php artisan migratefromv2b 1.7.3
```

### 4. 配置迁移

完成数据迁移后，还需要迁移配置文件：
- [配置迁移指南](./config迁移指南.md)
