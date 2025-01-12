## Docker-Compose 部署教程
本文教你如何在命令行使用docker-compose + sqlite来快速部署Xboard  
如果你需要使用Mysql，你需要自行处理好Mysql的安装。
### 部署 (使用docker-compose 2分钟部署)
> 在此提供Xboard安装、快速体验Xboard的步骤。   
使用docker compose + sqlite 快速部署站点（**无需安装Mysql以及redis**）
1. 安装docker
```
curl -sSL https://get.docker.com | bash
```  
Centos系统可能需要执行下面命令来启动Docker。
```
systemctl enable docker
systemctl start docker
```
2. 获取Docker compose 文件
```
git clone -b  docker-compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```
3. 执行数据库安装命令
> 选择 **启用sqlite** 和 **Docker内置的Redis**
```
docker compose run -it --rm -e enable_sqlite=true -e enable_redis=true -e admin_account=your_admin_email@example.com xboard php artisan xboard:install
```
> 或者根据自己的需要在运行时选择
```
docker compose run -it --rm xboard php artisan xboard:install
```
> 执行这条命令之后，会返回你的后台地址和管理员账号密码（你需要记录下来）  
> 你需要执行下面的 **启动xborad** 步骤之后才能访问后台

4. 启动Xboard
```
docker compose up -d
```
> 安装完成之后即可访问你的站点
5. 访问站点 
> 启动之后网站端口默认为7001, 你可以配置nginx反向代理使用80端口  

网站地址:   http://你的IP:7001/   
在此你已经成功部署了, 你可以访问网址体验Xboard的完整功能， 

> 如果你需要使用mysql，请自行安装Mysql后重新部署

### **更新**
1. 修改版本
```
cd Xboard
vi docker-compose.yaml
```
> 修改docker-compose.yaml 当中image后面的版本号为你需要的版本  
> 如果为版本为latest 则可以忽略这一步，直接进行第二步

2. 更新数据库（可以执行多次都是安全的）
```
docker compose pull
docker compose down
docker compose run -it --rm xboard php artisan xboard:update
docker compose up -d
```
> 即可更新成功

### **回滚**
> 此回滚不回滚数据库，是否回滚数据库请查看相关文档
1. 回退版本  
```
vi docker-compose.yaml
```
> 修改docker-compose.yaml 当中image后面的版本号为更新前的版本号
2. 启动
```
docker compose up -d
```
# English

## Docker-Compose Deployment Guide

This article guides you on how to quickly deploy **Xboard** using `docker-compose` and SQLite via the command line.  
If you prefer using MySQL, you will need to handle the MySQL installation separately.

---

### Deployment (2-Minute Deployment with Docker-Compose)

> Here are the steps to install and quickly experience Xboard.  
Deploy your site efficiently using **docker-compose + SQLite** (no need to install MySQL or Redis).

#### 1. Install Docker
```bash
curl -sSL https://get.docker.com | bash
```
For CentOS systems, you may need to execute the following commands to start Docker:
```bash
systemctl enable docker
systemctl start docker
```

#### 2. Download the Docker Compose Files
```bash
git clone -b docker-compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```

#### 3. Run the Database Installation Command
> Enable **SQLite** and **Redis (Docker-Built)**:
```bash
docker compose run -it --rm -e enable_sqlite=true -e enable_redis=true -e admin_account=your_admin_email@example.com xboard php artisan xboard:install
```
Alternatively, customize your preferences at runtime:
```bash
docker compose run -it --rm xboard php artisan xboard:install
```
> After running this command, it will return your admin panel URL, admin account, and password (make sure to record these).  
> You need to complete the next step, **Start Xboard**, to access the admin panel.

#### 4. Start Xboard
```bash
docker compose up -d
```
> After installation, your site will be ready for access.

#### 5. Access Your Site
> By default, the website runs on port `7001`. You can configure an NGINX reverse proxy to use port `80`.

Website URL:  
`http://your-IP:7001/`  

Congratulations! You’ve successfully deployed Xboard. You can now visit the URL to explore its full functionality.

> If you need MySQL, please install it separately and redeploy.

---

### **Updating Xboard**
#### 1. Modify the Version
```bash
cd Xboard
vi docker-compose.yaml
```
> Update the version number following `image` in the `docker-compose.yaml` file to your desired version.  
> If the version is set to `latest`, you can skip this step and proceed to step 2.

#### 2. Update the Database (Safe to Execute Multiple Times)
```bash
docker compose pull
docker compose down
docker compose run -it --rm xboard php artisan xboard:update
docker compose up -d
```
> The update is now complete.

---

### **Rollback**
> Note: This rollback does not revert the database. Please refer to relevant documentation if a database rollback is required.

#### 1. Revert to a Previous Version
```bash
vi docker-compose.yaml
```
> Update the version number following `image` in the `docker-compose.yaml` file to the previous version.

#### 2. Start the Service
```bash
docker compose up -d
```

---

### Note
Any code modifications made after enabling **webman** require a restart to take effect.
### 注意
启用webman后做的任何代码修改都需要重启生效
