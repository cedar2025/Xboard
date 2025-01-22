# Configuration Migration Guide

This guide explains how to migrate configuration files from v2board to Xboard. Xboard stores configurations in the database instead of files.

### 1. Docker Compose Environment

1. Prepare configuration file:
```bash
# Create config directory
mkdir config

# Copy old configuration file
cp old-project-path/config/v2board.php config/
```

2. Modify `docker-compose.yaml`, uncomment the following line:
```yaml
- ./config/v2board.php:/www/config/v2board.php
```

3. Execute migration:
```bash
docker compose run -it --rm web php artisan migrateFromV2b config
```

### 2. aaPanel Environment

1. Copy configuration file:
```bash
cp old-project-path/config/v2board.php config/v2board.php
```

2. Execute migration:
```bash
php artisan migrateFromV2b config
```

### 3. aaPanel + Docker Environment

1. Copy configuration file:
```bash
cp old-project-path/config/v2board.php config/v2board.php
```

2. Execute migration:
```bash
docker compose run -it --rm web php artisan migrateFromV2b config
```

### Important Notes

- After modifying the admin path, service restart is required:
  - Docker environment: `docker compose restart`
  - aaPanel environment: Restart the Octane daemon process 