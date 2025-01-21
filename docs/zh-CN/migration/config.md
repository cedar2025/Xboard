## 配置迁移指南

本指南介绍如何将 v2board 的配置文件迁移到 Xboard。Xboard 使用数据库存储配置，不再使用文件存储。

### 1. Docker Compose 环境

1. 准备配置文件：
```bash
# 创建配置目录
mkdir config

# 复制旧配置文件
cp 旧项目路径/config/v2board.php config/
```

2. 修改 `docker-compose.yaml`，取消以下行的注释：
```yaml
- ./config/v2board.php:/www/config/v2board.php
```

3. 执行迁移：
```bash
docker compose run -it --rm web php artisan migrateFromV2b config
```

### 2. aaPanel 环境

1. 复制配置文件：
```bash
cp 旧项目路径/config/v2board.php config/v2board.php
```

2. 执行迁移：
```bash
php artisan migrateFromV2b config
```

### 3. aaPanel + Docker 环境

1. 复制配置文件：
```bash
cp 旧项目路径/config/v2board.php config/v2board.php
```

2. 执行迁移：
```bash
docker compose run -it --rm web php artisan migrateFromV2b config
```

### 注意事项

- 修改后台路径后需要重启服务：
  - Docker 环境：`docker compose restart`
  - aaPanel 环境：重启 Octane 守护进程
