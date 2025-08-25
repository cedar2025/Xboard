# Stripe WeChat Pay & Alipay 插件安装说明

## 问题诊断

如果遇到 "Plugin config file not found" 错误，通常是以下原因：

### 1. 文件夹名称问题

插件管理器期望的文件夹名称必须是 **StudlyCase** 格式。

**正确的文件夹名称：** `StripeWeChatAlipay`
**错误的文件夹名称：** `stripewechatalipay` 或 `stripe-wechat-alipay`

### 2. 文件结构检查

确保插件文件夹结构如下：
```
plugins/StripeWeChatAlipay/
├── Plugin.php                 # 必需
├── config.json               # 必需
├── Controllers/
│   └── PaymentController.php
├── resources/views/
│   └── payment.blade.php
└── routes/
    └── web.php
```

### 3. config.json 格式验证

config.json 必须包含以下必需字段：
```json
{
    "name": "Stripe微信支付宝",
    "code": "stripe_wechat_alipay",  
    "type": "payment",
    "version": "1.0.0",
    "description": "Stripe WeChat Pay and Alipay payment plugin for Xboard",
    "author": "Xboard Team"
}
```

## 安装步骤

### 第一步：正确命名文件夹
1. 确保插件文件夹名称为：`StripeWeChatAlipay`（注意大小写）
2. 将整个文件夹复制到 Docker 容器的 `plugins/` 目录下

### 第二步：检查文件权限
```bash
# 进入 Docker 容器
docker exec -it your_container_name bash

# 检查插件目录
ls -la plugins/
ls -la plugins/StripeWeChatAlipay/

# 确保文件可读
cat plugins/StripeWeChatAlipay/config.json
```

### 第三步：在管理后台安装
1. 登录 Xboard 管理后台
2. 进入 `插件管理` 页面
3. 找到 "Stripe微信支付宝" 插件
4. 点击安装

## 故障排除

### 如果仍然出现错误：

1. **检查文件路径**
   ```bash
   # 在容器内执行
   find /var/www/html/plugins -name "config.json" -type f
   ```

2. **检查文件内容**
   ```bash
   # 验证 JSON 格式
   cat plugins/StripeWeChatAlipay/config.json | python -m json.tool
   ```

3. **查看详细错误日志**
   ```bash
   # 查看 Laravel 日志
   tail -f storage/logs/laravel.log
   ```

4. **重启服务**
   ```bash
   # 重启 Docker 容器
   docker compose restart
   
   # 或清除缓存
   php artisan cache:clear
   php artisan config:clear
   ```

### 插件管理器路径逻辑

插件管理器通过以下逻辑查找插件：
- 插件代码：`stripe_wechat_alipay`
- 转换为文件夹名：`Str::studly('stripe_wechat_alipay')` = `StripeWeChatAlipay`
- 完整路径：`base_path('plugins') . '/StripeWeChatAlipay'`
- 配置文件路径：`plugins/StripeWeChatAlipay/config.json`

## 验证安装成功

安装成功后：
1. 在插件管理页面应该能看到 "Stripe微信支付宝" 插件
2. 插件状态显示为 "已安装"
3. 可以进入插件配置页面设置参数
4. 在支付方式列表中能看到该支付选项

## 配置参数

安装成功后需要配置以下参数：
- **Stripe Secret Key**: sk_live_... 或 sk_test_...
- **Stripe Publishable Key**: pk_live_... 或 pk_test_...
- **Webhook Secret**: whsec_... (可选，提高安全性)
- **支付方式**: 选择微信支付、支付宝或两者
- **货币类型**: 支持 CNY、USD、EUR 等
- **商品描述**: 自定义订单描述
- **自动确认付款**: 是否自动确认支付

## 联系支持

如果仍然无法解决问题，请提供：
1. 完整的错误信息
2. 插件文件夹结构截图
3. config.json 文件内容
4. Laravel 日志中的相关错误信息