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

# Configure Docker Compose
```

4. Edit docker-compose.yml:
```yaml
services:
  web:
    image: ghcr.io/cedar2025/xboard:new
    volumes:
      - ./.docker/.data/redis/:/data/
      - ./.env:/www/.env
      - ./.docker/.data/:/www/.docker/.data
      - ./storage/logs:/www/storage/logs
      - ./storage/theme:/www/storage/theme
      - ./plugins:/www/plugins
    environment:
      - docker=true
    depends_on:
      - redis
    command: php artisan octane:start --host=0.0.0.0 --port=7001
    restart: on-failure
    ports:
      - 7001:7001
    networks:
      - 1panel-network

  horizon:
    image: ghcr.io/cedar2025/xboard:new
    volumes:
      - ./.docker/.data/redis/:/data/
      - ./.env:/www/.env
      - ./.docker/.data/:/www/.docker/.data
      - ./storage/logs:/www/storage/logs
      - ./plugins:/www/plugins
    restart: on-failure
    command: php artisan horizon
    networks:
      - 1panel-network
    depends_on:
      - redis

  redis:
    image: redis:7-alpine
    command: redis-server --unixsocket /data/redis.sock --unixsocketperm 777 --save 900 1 --save 300 10 --save 60 10000
    restart: unless-stopped
    networks:
      - 1panel-network
    volumes:
      - ./.docker/.data/redis:/data

networks:
  1panel-network:
    external: true
```

5. Initialize Installation:
```bash
# Install dependencies and initialize
docker compose run -it --rm web php artisan xboard:install
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

> 💡 Important Note: The update command varies depending on your installation version:
> - If you installed recently (new version), use this command:
```bash
docker compose pull && \
docker compose run -it --rm web php artisan xboard:update && \
docker compose up -d
```
> - If you installed earlier (old version), replace `web` with `xboard`:
```bash
docker compose pull && \
docker compose run -it --rm xboard php artisan xboard:update && \
docker compose up -d
```
> 🤔 Not sure which to use? Try the new version command first, if it fails, use the old version command.

## Important Notes

- ⚠️ Ensure firewall is enabled to prevent port 7001 exposure to public
- Service restart is required after code modifications
- SSL certificate configuration is recommended for secure access 
