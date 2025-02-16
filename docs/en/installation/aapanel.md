# Xboard Deployment Guide for aaPanel Environment

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
- Operating System: Ubuntu 20.04+ / Debian 10+ (⚠️ CentOS 7 is not recommended)
- Latest version of aaPanel
- PHP 8.2
- MySQL 5.7+
- Redis
- Nginx (any version)

## Quick Deployment

### 1. Install aaPanel
```bash
URL=https://www.aapanel.com/script/install_6.0_en.sh && \
if [ -f /usr/bin/curl ];then curl -ksSO "$URL" ;else wget --no-check-certificate -O install_6.0_en.sh "$URL";fi && \
bash install_6.0_en.sh aapanel
```

### 2. Basic Environment Setup

#### 2.1 Install LNMP Environment
In the aaPanel dashboard, install:
- Nginx (any version)
- MySQL 5.7
- PHP 8.2

#### 2.2 Install PHP Extensions
Required PHP extensions:
- redis
- fileinfo
- swoole4
- readline
- event

#### 2.3 Enable Required PHP Functions
Functions that need to be enabled:
- putenv
- proc_open
- pcntl_alarm
- pcntl_signal

### 3. Site Configuration

#### 3.1 Create Website
1. Navigate to: aaPanel > Website > Add site
2. Fill in the information:
   - Domain: Enter your site domain
   - Database: Select MySQL
   - PHP Version: Select 8.2

#### 3.2 Deploy Xboard
```bash
# Enter site directory
cd /www/wwwroot/your-domain

# Clean directory
chattr -i .user.ini
rm -rf .htaccess 404.html 502.html index.html .user.ini

# Clone repository
git clone https://github.com/cedar2025/Xboard.git ./

# Install dependencies
sh init.sh
```

#### 3.3 Configure Site
1. Set running directory to `/public`
2. Add rewrite rules:
```nginx
location /downloads {
}

location / {  
    try_files $uri $uri/ /index.php$is_args$query_string;  
}

location ~ .*\.(js|css)?$
{
    expires      1h;
    error_log off;
    access_log /dev/null; 
}
```

## Detailed Configuration

### 1. Configure Daemon Process
1. Install Supervisor
2. Add queue daemon process:
   - Name: `Xboard`
   - Run User: `www`
   - Running Directory: Site directory
   - Start Command: `php artisan horizon`
   - Process Count: 1

### 2. Configure Scheduled Tasks
- Type: Shell Script
- Task Name: v2board
- Run User: www
- Frequency: 1 minute
- Script Content: `php /www/wwwroot/site-directory/artisan schedule:run`

### 3. Octane Configuration (Optional)
#### 3.1 Add Octane Daemon Process
- Name: Octane
- Run User: www
- Running Directory: Site directory
- Start Command: `/www/server/php/82/bin/php artisan octane:start --port 7010`
- Process Count: 1

#### 3.2 Octane-specific Rewrite Rules
```nginx
location ~* \.(jpg|jpeg|png|gif|js|css|svg|woff2|woff|ttf|eot|wasm|json|ico)$ {
}

location ~ .* {
    proxy_pass http://127.0.0.1:7010;
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
}
```

## Maintenance Guide

### Version Updates
```bash
# Enter site directory
cd /www/wwwroot/your-domain

# Execute update script
git fetch --all && git reset --hard origin/master && git pull origin master
sh update.sh

# If Octane is enabled, restart the daemon process
# aaPanel > App Store > Tools > Supervisor > Restart Octane
```

### Routine Maintenance
- Regular log checking
- Monitor system resource usage
- Regular backup of database and configuration files

## Troubleshooting

### Common Issues
1. Changes to admin path require service restart to take effect
2. Any code changes after enabling Octane require restart to take effect
3. When PHP extension installation fails, check if PHP version is correct
4. For database connection failures, check database configuration and permissions 
