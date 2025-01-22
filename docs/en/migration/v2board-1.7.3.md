# V2board 1.7.3 Migration Guide

This guide explains how to migrate from V2board version 1.7.3 to Xboard.

### 1. Database Changes Overview

- `v2_stat_order` table renamed to `v2_stat`:
  - `order_amount` → `order_total`
  - `commission_amount` → `commission_total`
  - New fields added:
    - `paid_count` (integer, nullable)
    - `paid_total` (integer, nullable)
    - `register_count` (integer, nullable)
    - `invite_count` (integer, nullable)
    - `transfer_used_total` (string(32), nullable)

- New tables added:
  - `v2_log`
  - `v2_server_hysteria`
  - `v2_server_vless`

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
# Please manually import the V2board 1.7.3 database

# 4. Execute migration
docker compose run -it --rm web php artisan migratefromv2b 1.7.3
```

#### aaPanel Environment

```bash
# 1. Clear database
php artisan db:wipe

# 2. Import old database (Important)
# Please manually import the V2board 1.7.3 database

# 3. Execute migration
php artisan migratefromv2b 1.7.3
```

### 4. Configuration Migration

After completing the data migration, you need to migrate the configuration file:
- [Configuration Migration Guide](./config.md) 