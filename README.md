# 关于Xboard
Xboard是基于V2board二次开发，在性能上和功能上都有大部分增强的**面板

# 免责声明
本项目只是本人个人学习开发并维护，本人不保证任何可用性，也不对使用本软件造成的任何后果负责。





### Docker-compose部署

下载项目
```
git clone https://github.com/admin8800/xboard.git
cd Xboard
```

安装和导入数据库
```
docker compose run -it --rm xboard php artisan xboard:install
```

运行
```
docker compose up -d
```

- 启动之后面板端口默认为`9000` 自行配置反代到域名
