# V2board 1.7.4 Migration Guide

This guide explains how to migrate from V2board version 1.7.4 to Xboard.

### 1. Database Changes Overview

- New table added:
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
# Please manually import the V2board 1.7.4 database

# 4. Execute migration
docker compose run -it --rm web php artisan migratefromv2b 1.7.4
```

#### aaPanel Environment

```bash
# 1. Clear database
php artisan db:wipe

# 2. Import old database (Important)
# Please manually import the V2board 1.7.4 database

# 3. Execute migration
php artisan migratefromv2b 1.7.4
```

### 4. Configuration Migration

After completing the data migration, you need to migrate the configuration file:
- [Configuration Migration Guide](./config.md) 