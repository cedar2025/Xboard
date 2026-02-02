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

# Clone repository
git clone https://github.com/cedar2025/Xboard.git ./

# Prepare configuration file
cp compose.sample.yaml compose.yaml

# Install dependencies and initialize
docker compose run -it --rm web sh init.sh
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
    proxy_set_header Connection "";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Real-PORT $remote_port;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header Server-Protocol $server_protocol;
    proxy_set_header Server-Name $server_name;
    proxy_set_header Server-Addr $server_addr;
    proxy_set_header Server-Port $server_port;
    proxy_cache off;
}
```

## Maintenance Guide

### Version Updates

> 💡 Important Note: Update commands may vary depending on your installed version:
> - For recent installations (new version), use:
```bash
docker compose pull && \
docker compose run -it --rm web sh update.sh && \
docker compose up -d
```
> - For older installations, replace `web` with `xboard`:
```bash
git config --global --add safe.directory $(pwd)
git fetch --all && git reset --hard origin/master && git pull origin master
docker compose pull && \
docker compose run -it --rm xboard sh update.sh && \
docker compose up -d
```
> 🤔 Not sure which to use? Try the new version command first, if it fails, use the old version command.

### Routine Maintenance
- Regular log checking: `docker compose logs`
- Monitor system resource usage
- Regular backup of database and configuration files

## Troubleshooting

If you encounter any issues during installation or operation, please check:
1. System requirements are met
2. All required ports are available
3. Docker services are running properly
4. Nginx configuration is correct
5. Check logs for detailed error messages 
