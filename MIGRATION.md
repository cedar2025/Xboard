This document details the usage of the `migrate:sqlite-to-db` Artisan command, which provides a robust and interactive way to migrate data from a SQLite database to a specified relational database (MySQL or PostgreSQL). It handles schema migration, data transfer, and updates your `.env` configuration.

Here's the detailed breakdown of the tool:



#### 1. 交互式选择（推荐）

如果你不确定或想每次都选择，直接运行命令，它会提示你：

```bash
php artisan migrate:sqlite-to-db
```

#### 2. 直接指定目标数据库

如果你确定目标数据库类型，可以使用 `--target` 选项：

```bash
php artisan migrate:sqlite-to-db --target=mysql
# 或
php artisan migrate:sqlite-to-db --target=pgsql
```

#### 3. 生产环境运行

在生产环境中运行此命令需要添加 `--force` 选项：

```bash
php artisan migrate:sqlite-to-db --force
# 或
php artisan migrate:sqlite-to-db --target=mysql --force
```

**重要提示:**

*   运行此命令前，请确保目标数据库服务器正在运行，并且您已创建了一个空的数据库。
*   此命令会运行 `migrate:fresh`，这意味着它会删除目标数据库中的所有表并重新创建它们。
*   完成迁移后，请务必重启您的 Web 服务器和 PHP-FPM 进程，以使新的数据库配置生效。例如：
    ```bash
    sudo systemctl restart nginx
    sudo systemctl restart php8.1-fpm # 使用您的 PHP 版本
    ```
