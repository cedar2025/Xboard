# Xboard Deployment Guide for aaPanel + Docker Environment

## Table of Contents
1. [Requirements](#requirements)
2. [Quick Deployment](#quick-deployment)
3. [Detailed Configuration](#detailed-configuration)
4. [Maintenance Guide](#maintenance-guide)
5. [Troubleshooting](#troubleshooting)

## Requirements

### Hardware Requirements
- CPU: 1 core or above
- Memory: 2GB or above
- Storage: 10GB+ available space

### Software Requirements
- Operating System: Ubuntu 20.04+ / CentOS 7+ / Debian 10+
- Latest version of aaPanel
- Docker and Docker Compose
- Nginx (any version)
- MySQL 5.7+

## Quick Deployment

### 1. Install aaPanel
```bash
curl -sSL https://www.aapanel.com/script/install_6.0_en.sh -o install_6.0_en.sh && \
bash install_6.0_en.sh aapanel
```

### 2. Basic Environment Setup

#### 2.1 Install Docker
```bash
# Install Docker
curl -sSL https://get.docker.com | bash

# For CentOS systems, also run:
systemctl enable docker
systemctl start docker
```

#### 2.2 Install Required Components
In the aaPanel dashboard, install:
- Nginx (any version)
- MySQL 5.7
- ⚠️ PHP and Redis are not required

### 3. Site Configuration

#### 3.1 Create Website
1. Navigate to: aaPanel > Website > Add site
2. Fill in the information:
   - Domain: Enter your site domain
   - Database: Select MySQL
   - PHP Version: Select Pure Static

#### 3.2 Deploy Xboard
```bash
# Enter site directory
cd /www/wwwroot/your-domain

# Clean directory
chattr -i .user.ini
rm -rf .htaccess 404.html 502.html index.html .user.ini

# Clone the compose branch
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard.git ./

# Prepare configuration file
cp compose.host.sample.yaml compose.yaml

# Install dependencies and initialize
docker compose run -it --rm xboard php artisan xboard:install
```
> ⚠️ Please save the admin dashboard URL, username, and password shown after installation

#### 3.3 Start Services
```bash
docker compose up -d
```

#### 3.4 Configure Reverse Proxy
Add the following content to your site configuration:
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

## Maintenance Guide

### Version Updates

```bash
docker compose pull && docker compose up -d
```

The container always runs `php artisan xboard:update` (migrate + plugin install + version cache + theme refresh) on boot, so no extra command is required.

### Routine Maintenance
- Regular log checking: `docker compose logs`
- Monitor system resource usage
- Regular backup of database and configuration files

## Troubleshooting

If you encounter any issues during installation or operation, please check:
1. **Empty Admin Dashboard**: If the admin panel is blank, run `git submodule update --init --recursive --force` to restore the theme files.
2. System requirements are met
3. All required ports are available
3. Docker services are running properly
4. Nginx configuration is correct
5. Check logs for detailed error messages

> The node will automatically detect WebSocket availability during handshake. No extra configuration is needed on the node side. 
