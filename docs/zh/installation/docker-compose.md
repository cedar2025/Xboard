# 使用 Docker Compose 部署

## 前置要求

- 安装 [Docker](https://docs.docker.com/get-docker/)
- 安装 [Docker Compose](https://docs.docker.com/compose/install/)
- 安装 Git

## 安装

1. 获取代码
   ```bash
   git clone -b compose --depth 1 https://github.com/cedar2025/Xboard
   ```

2. 进入目录
   ```bash
   cd Xboard
   ```

3. 安装 Xboard
   ```bash
   docker compose run -it --rm \
       -e ENABLE_SQLITE=true \
       -e ENABLE_REDIS=true \
       -e ADMIN_ACCOUNT=admin@demo.com \
       web php artisan xboard:install
   ```
   > 你可以根据需要自定义变量，例如启用邮箱验证 `ENABLE_EMAIL_VERIFY=true`
   > ⚠️ 请务必保存安装过程中显示的管理员账号密码

4. 启动服务
   ```bash
   docker compose up -d
   ```

## 更新

运行以下脚本进行更新：

```bash
sh update.sh
```

**全局一键更新设置:**

如需在服务器任何地方一键更新，请在项目根目录运行：

```bash
chmod +x update.sh && sudo ln -sf $(pwd)/update.sh /usr/local/bin/xb-update
```

之后即可使用 `xb-update` 命令进行更新。