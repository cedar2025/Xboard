# V2board Dev Migration Guide

This guide explains how to migrate from V2board Dev version (2023/10/27) to Xboard.

⚠️ Please upgrade to version 2023/10/27 following the official guide before proceeding with migration.

### 1. Database Changes Overview

- `v2_order` table:
  - Added `surplus_order_ids` (text, nullable) - Deduction orders

- `v2_plan` table:
  - Removed `daily_unit_price` - Affects period value
  - Removed `transfer_unit_price` - Affects traffic value

- `v2_server_hysteria` table:
  - Removed `ignore_client_bandwidth` - Affects bandwidth configuration
  - Removed `obfs_type` - Affects obfuscation type configuration

### 2. Prerequisites

⚠️ Please complete the basic Xboard installation first (SQLite not supported):
- [Docker Compose Deployment](../installation/docker-compose.md)
- [aaPanel + Docker Deployment](../installation/aapanel-docker.md)
- [aaPanel Deployment](../installation/aapanel.md)

### 3. Migration Steps

#### Docker Environment

```bash
# 1. Stop services
docker compose down

# 2. Clear database
docker compose run -it --rm web php artisan db:wipe

# 3. Import old database (Important)
# Please manually import the V2board Dev database

# 4. Execute migration
docker compose run -it --rm web php artisan migratefromv2b dev231027
```

#### aaPanel Environment

```bash
# 1. Clear database
php artisan db:wipe

# 2. Import old database (Important)
# Please manually import the V2board Dev database

# 3. Execute migration
php artisan migratefromv2b dev231027
```

### 4. Configuration Migration

After completing the data migration, you need to migrate the configuration file:
- [Configuration Migration Guide](./config.md) 