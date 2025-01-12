English l [Chinese](/README.md)
## Docker-Compose Deployment Guide

This guide teaches you how to quickly deploy **Xboard** using `docker-compose` and SQLite via the command line.  
If you wish to use MySQL, you must handle its installation separately.

### Deployment (Deploy in 2 Minutes with Docker-Compose)

> Steps to install and quickly experience Xboard.  
Deploy your site rapidly using **docker-compose + SQLite** (no need to install MySQL or Redis).

#### 1. Install Docker
```bash
curl -sSL https://get.docker.com | bash
```  
For CentOS systems, you may need to execute the following commands to start Docker:
```bash
systemctl enable docker
systemctl start docker
```

#### 2. Retrieve the Docker Compose File
```bash
git clone -b docker-compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```

#### 3. Execute the Database Installation Command
> Choose **Enable SQLite** and **Docker-Built Redis**
```bash
docker compose run -it --rm -e enable_sqlite=true -e enable_redis=true -e admin_account=your_admin_email@example.com xboard php artisan xboard:install
```
> Or customize your options at runtime:
```bash
docker compose run -it --rm xboard php artisan xboard:install
```
> After running the above command, your admin panel address, admin account, and password will be returned (make sure to note these down).  
> You need to complete the next step, **Start Xboard**, before accessing the admin panel.

#### 4. Start Xboard
```bash
docker compose up -d
```
> Once installation is complete, you can access your site.

#### 5. Access the Site
> After startup, the website port defaults to `7001`. You can configure an NGINX reverse proxy to use port `80`.

Website URL:  
http://your-IP:7001/  

Congratulations, you’ve successfully deployed Xboard! You can now visit the site and experience all of Xboard’s features.

> If you need to use MySQL, please install MySQL separately and redeploy.

---

### **Updating Xboard**
#### 1. Modify the Version
```bash
cd Xboard
vi docker-compose.yaml
```
> Edit the `docker-compose.yaml` file and update the version number following `image` to your desired version.  
> If the version is `latest`, you can skip this step and proceed to step 2.

#### 2. Update the Database (Safe to run multiple times)
```bash
docker compose pull
docker compose down
docker compose run -it --rm xboard php artisan xboard:update
docker compose up -d
```
> The update is now complete.

---

### **Rollback**
> Note: This rollback does not revert the database. Refer to relevant documentation for database rollbacks.

#### 1. Revert the Version
```bash
vi docker-compose.yaml
```
> Edit the `docker-compose.yaml` file and change the version number following `image` to the previous version.

#### 2. Start the Service
```bash
docker compose up -d
```

---

### Note
Any code changes made after enabling **webman** require a restart to take effect.
