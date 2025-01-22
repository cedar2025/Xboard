# Quick Deployment Guide with Docker Compose

This guide explains how to quickly deploy Xboard using Docker Compose. By default, it uses SQLite database, eliminating the need for a separate MySQL installation.

### 1. Environment Preparation

Install Docker:
```bash
curl -sSL https://get.docker.com | bash

# For CentOS systems, also run:
systemctl enable docker
systemctl start docker
```

### 2. Deployment Steps

1. Get project files:
```bash
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```

2. Install database:  

- Quick installation (Recommended for beginners)
```bash
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    web php artisan xboard:install
```
- Custom configuration installation (Advanced users)
```bash
docker compose run -it --rm web php artisan xboard:install
```
> Please save the admin dashboard URL, username, and password shown after installation

3. Start services:
```bash
docker compose up -d
```

4. Access the site:
- Default port: 7001
- Website URL: http://your-server-ip:7001

### 3. Version Updates

> 💡 Important Note: Update commands may vary depending on your installed version:
> - For recent installations (new version), use:
```bash
cd Xboard
docker compose pull && \
docker compose run -it --rm web php artisan xboard:update && \
docker compose up -d
```
> - For older installations, replace `web` with `xboard`:
```bash
cd Xboard
docker compose pull && \
docker compose run -it --rm xboard php artisan xboard:update && \
docker compose up -d
```
> 🤔 Not sure which to use? Try the new version command first, if it fails, use the old version command.

### 4. Version Rollback

1. Modify the version number in `docker-compose.yaml` to the version you want to roll back to
2. Execute: `docker compose up -d`

### Important Notes

- If you need to use MySQL, please install it separately and redeploy
- Code changes require service restart to take effect
- You can configure Nginx reverse proxy to use port 80 