# Quick Deployment Guide for 1Panel

This guide explains how to deploy Xboard using 1Panel.

## 1. Environment Preparation

Install 1Panel:
```bash
curl -sSL https://resource.fit2cloud.com/1panel/package/quick_start.sh -o quick_start.sh && \
sudo bash quick_start.sh
```

## 2. Environment Configuration

1. Install from App Store:
   - OpenResty (any version)
     - ⚠️ Check "External Port Access" to open firewall
   - MySQL 5.7 (Use MariaDB for ARM architecture)

2. Create Database:
   - Database name: `xboard`
   - Username: `xboard`
   - Access rights: All hosts (%)
   - Save the database password for installation

## 3. Deployment Steps

1. Add Website:
   - Go to "Website" > "Create Website" > "Reverse Proxy"
   - Domain: Enter your domain
   - Code: `xboard`
   - Proxy address: `127.0.0.1:7001`

2. Configure Reverse Proxy:
```nginx
location ^~ / {
    proxy_pass http://127.0.0.1:7001;
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection $http_connection;
    proxy_read_timeout 60s;
    proxy_buffering off;
    proxy_cache off;
}
```
> The all-in-one container's embedded Caddy fuses HTTP and the panel↔node WebSocket on port 7001. The single `Upgrade`/`Connection` pair above is enough; no separate `/ws/` location is needed. To opt out and expose Octane / `:8076` directly, set `ENABLE_CADDY=false` in `compose.yaml`.

3. Install Xboard:
```bash
# Enter site directory
cd /opt/1panel/apps/openresty/openresty/www/sites/xboard/index

# Install Git (if not installed)
## Ubuntu/Debian
apt update && apt install -y git
## CentOS/RHEL
yum update && yum install -y git

# Clone repository
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard ./
# (Optional shortcut: skip the clone and just fetch the sample file with
#  curl -fsSL https://raw.githubusercontent.com/cedar2025/Xboard/master/compose.sample.yaml -o compose.yaml
#  — the running PHP code is in the Docker image, not in the clone.)

# Configure Docker Compose
```

4. Prepare `compose.yaml` from the **1Panel-specific** sample. This sample joins the external `1panel-network` so the container can reach the 1Panel-managed MySQL/Redis containers by their hostname:
```bash
cp compose.1panel.sample.yaml compose.yaml
```
The file is gitignored so your edits survive `git pull`. See [docker-compose.md](./docker-compose.md) for tuning environment variables (`RESOURCE_PROFILE`, `ENABLE_HORIZON`, `ENABLE_REDIS`, etc.) and the other `compose.*.sample.yaml` alternatives.

5. Initialize Installation:
```bash
docker compose run -it --rm xboard php artisan xboard:install
```

⚠️ Important Configuration Notes:
1. Database Configuration
   - Database Host: Choose based on your deployment:
     1. If database and Xboard are in the same network, use `mysql`
     2. If connection fails, go to: Database -> Select Database -> Connection Info -> Container Connection, and use the "Host" value
     3. If using external database, enter your actual database host
   - Database Port: `3306` (default port unless configured otherwise)
   - Database Name: `xboard` (the database created earlier)
   - Database User: `xboard` (the user created earlier)
   - Database Password: Enter the password saved earlier

2. Redis Configuration
   - Choose to use built-in Redis
   - No additional configuration needed

3. Administrator Information
   - Save the admin credentials displayed after installation
   - Note down the admin panel access URL

After configuration, start the services:
```bash
docker compose up -d
```

6. Start Services:
```bash
docker compose up -d
```

## 4. Version Update

```bash
docker compose pull && docker compose up -d
```

The container always runs `php artisan xboard:update` (migrate + plugin install + version cache + theme refresh) on boot, so no extra command is required.

> **Using a `compose.yaml` from before 2026-04-19?** That template did not auto-run `xboard:update` on container start, so use the following command to upgrade instead:
> ```bash
> docker compose pull && docker compose run -it --rm web php artisan xboard:update && docker compose up -d
> ```

## Important Notes

- ⚠️ Ensure firewall is enabled to prevent port 7001 exposure to public
- Service restart is required after code modifications
- SSL certificate configuration is recommended for secure access

> The node will automatically detect WebSocket availability during handshake. No extra configuration is needed on the node side. 
