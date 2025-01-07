## V2board wyx2685 迁移指南

本指南介绍如何将 V2board wyx2685（2023/11/17）版本迁移到 Xboard。

⚠️ 迁移注意事项：
- 将失去设备限制功能
- 将失去 Trojan 的特殊功能
- Hysteria2 线路需要重新配置

### 1. 数据库变更说明

- `v2_plan` 表：
  - 删除 `device_limit` (nullable)

- `v2_server_hysteria` 表：
  - 删除 `version`
  - 删除 `obfs`
  - 删除 `obfs_password`

- `v2_server_trojan` 表：
  - 删除 `network`
  - 删除 `network_settings`

- `v2_user` 表：
  - 删除 `device_limit`

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
# 请手动导入 V2board wyx2685 的数据库

# 4. 执行迁移
docker compose run -it --rm web php artisan migratefromv2b wyx2685
```

#### aaPanel 环境

```bash
# 1. 清空数据库
php artisan db:wipe

# 2. 导入旧数据库（重要）
# 请手动导入 V2board wyx2685 的数据库

# 3. 执行迁移
php artisan migratefromv2b wyx2685
```

### 4. 配置迁移

完成数据迁移后，还需要迁移配置文件：
- [配置迁移指南](./config迁移指南.md)