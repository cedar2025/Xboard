## Docker-Compose 部署教程
本文教你如何在命令行使用aapanel + docker-compose来快速Xboard  

### 部署
1. 安装aaPanel + 和docker 

如果是Centos系统
```
yum install -y wget && wget -O install.sh http://www.aapanel.com/script/install_6.0_en.sh && bash install.sh aapanel
```
如果是Ubuntu/Deepin系统
```
wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh && sudo bash install.sh aapanel
``` 
如果是Debian 系统
```
wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh && bash install.sh aapanel
```

安装完成后我们登陆 aaPanel 进行环境的安装。
2. 选择使用LNMP的环境安装方式勾选如下信息 
☑️ Nginx 任意版本  
☑️ MySQL 5.7  
选择 Fast 快速编译后进行安装。  
>安装过程中点击 Docker 去安装一下Docker   

<span style="color:yellow">⚠️ ：无需安装php 与 redis</span>

3. 添加站点  
>aaPanel 面板 > Website > Add site。  
>>在 Domain 填入你指向服务器的域名  
>>在 Database 选择MySQL  
>>在 PHP Verison 选择纯静态

4. 安装 Xborad
>通过SSH登录到服务器后访问站点路径如：/www/wwwroot/你的站点域名。
>以下命令都需要在站点目录进行执行。
```
# 删除目录下文件
chattr -i .user.ini
rm -rf .htaccess 404.html index.html .user.ini
```
> 执行命令从 Github 克隆到当前目录。
```
git clone https://github.com/cedar2025/Xboard.git ./
```
> 复制一份docker-compose.yaml文件
```
cp docker-compose.sample.yaml docker-compose.yaml
```
> 执行命令安装依赖包以及Xboard
```
docker compose run -it --rm xboard sh init.sh
```
> 根据提示完成安装
> 执行这条命令之后，会返回你的后台地址和管理员账号密码（你需要记录下来）    
> 你需要执行下面的 **启动xborad** 步骤之后才能访问后台  

5. 启动xboard
```
docker compose up -d
```
6. 设置反向代理
> 站点设置 > 反向代理 > 添加反向代理
>> 在 **代理名称** 填入 Xboard  
>> 在 **目标URL** 填入 ```http://127.0.0.1:7001```

🎉： 到这里，你可以已经可以通过域名访问你的站点了

### 更新
1. 更新代码
>通过SSH登录到服务器后访问站点路径如：/www/wwwroot/你的站点域名。  
>以下命令都需要在站点目录进行执行。
```
docker compose run -it --rm xboard sh update.sh
```
2. 重启Xboard
```
docker compose restart
```
🎉： 在此你已完成Xboard的更新

### 注意
启用webman后做的任何代码修改都需要重启生效