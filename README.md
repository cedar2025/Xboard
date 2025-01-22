# About Xboard
Xboard is a panel based on V2board's secondary development, with significant enhancements in both performance and functionality.

# Disclaimer
This project is personally developed and maintained by me for learning purposes. I do not guarantee any availability and am not responsible for any consequences resulting from the use of this software.

# Xboard Features
Based on V2board's secondary development, with the following added features:
- Upgraded to Laravel 10
- Adapted to Laravels (10+ times concurrent improvement)
- Adapted to Webman (about 50% faster than laravels)
- Modified configuration retrieval from database
- Support for Docker deployment and distributed deployment
- Support for subscription distribution based on user IP location
- Added Hy2 support
- Added sing-box distribution
- Support for obtaining real visitor IP directly from Cloudflare
- Support for automatic new protocol distribution based on client version
- Support for route filtering (add &filter=HongKong|USA after subscription URL)
- Support for Sqlite installation (alternative to MySQL, great for personal use)
- User frontend rebuilt using Vue3 + TypeScript + NaiveUI + Unocss + Pinia
- Fixed numerous bugs

# **System Architecture**

- PHP8.1+
- Composer
- MySQL5.7+
- Redis
- Laravel

## Performance Comparison [View Details](./docs/性能对比.md)
> xboard shows tremendous performance improvements in both frontend and backend

|Scenario   | php-fpm(traditional) | php-fpm(traditional with opcache) | laravels | webman(docker)|
|----       |   ----              |----                               |----      | ---|
|Homepage   | 6 req/s             | 157 req/s                         | 477 req/s| 803 req/s|
|User Subscription| 6 req/s       | 196 req/s                         | 586 req/s| 1064 req/s|
|User Homepage Latency| 308ms     | 110ms                            | 101ms    | 98ms|

## Page Display
![Example Image](./docs/images/dashboard.png)

## Installation / Update / Rollback
You can click to view the **installation and update** steps for the following methods:
- [1panel Deployment](./docs/1panel安装指南.md)
- [Docker Compose Command-line Quick Deployment](./docs/docker-compose安装指南.md)
- [aapanel + Docker Compose (Recommended)](./docs/aapanel+docker安装指南.md)
- [aapanel Deployment](./docs/aapanel安装指南.md)

### Migrating from Other Versions
#### Database Migration
**Check the corresponding migration guide according to your version**
- v2board dev version 23/10/27 [Jump to Migration Guide](./docs/v2b_dev迁移指南.md)
- v2board 1.7.4 [Jump to Migration Guide](./docs/v2b_1.7.4迁移指南.md)
- v2board 1.7.3 [Jump to Migration Guide](./docs/v2b_1.7.3迁移指南.md)
- v2board wyx2685 [Jump to Migration Guide](./docs/v2b_wyx2685迁移指南.md)

### Note
> Modifying the admin path requires a restart to take effect
```
docker compose restart
```
> If using aapanel installation, you need to restart the webman daemon process
