
# 性能对比总结

## 测试使用机器配置
CPU型号： Intel 8255C
CPU核心数量： 4核
内存：8G


## 不同环境并发对比
> php-fpm指的就是我们平时使用的aapanel（宝塔）的安装方式, 并发测试使用的是wrk

|场景      | php-fpm(传统) | php-fpm(传统开启opcache) | laravels | webman(docker)|
|----     |   ----   |----   |----| ---|
|首页      | 6请求/秒      | 157请求/秒        |   477请求/秒    | 803请求/秒   |
|用户订阅   | 6请求/秒      | 196请求/秒         | 586请求/秒    | 1064请求/秒  |
|用户首页延迟| 308ms        |  110ms           |  101ms   |    98ms      |

## 前端加载速度对比（v2b原版/Xboard）
> FCP（First Contentful Paint） 指的是 首次内容渲染 耗费的时间

> FCP(原版耗时/xboard耗时) 结果越低越好

|场景      | php-fpm | php-fpm(开启opcache)|laravels | webman(docker)|
|----      |   ----                 |----     |--- |----     | 
| 登录页面  | FCP(7秒/2.9秒)           |  FCP  (7秒/2.9秒)           |    FCP(7.1秒/2.7秒)     |  FCP(7.3秒/2.9秒) |
| 注册页面  | FCP(7.1秒/3秒)           |  FCP  (7秒/2.8秒)            |   FCP(7.1秒/2.7秒)   |  FCP(7.3秒/2.9秒) |