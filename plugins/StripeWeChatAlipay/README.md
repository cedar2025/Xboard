# Stripe WeChat Pay & Alipay 支付插件

## 功能特性

- 支持 WeChat Pay（微信支付）
- 支持 Alipay（支付宝）
- 支持同时启用两种支付方式
- 多货币支持（CNY, USD, EUR, GBP, HKD, JPY, SGD）
- 自动汇率转换
- Webhook 回调验证
- 与 Xboard 支付系统完美集成

## 安装配置

### 1. Stripe 账户配置

1. 登录 [Stripe Dashboard](https://dashboard.stripe.com/)
2. 在 Settings > Payment methods 中启用 WeChat Pay 和 Alipay
3. 获取 API 密钥：
   - Secret Key (sk_live_... 或 sk_test_...)
   - Publishable Key (pk_live_... 或 pk_test_...)
4. 配置 Webhook 端点：
   - URL: `https://yourdomain.com/api/v1/guest/payment/notify/StripeWeChatAlipay/{uuid}`
   - 事件: `payment_intent.succeeded`, `payment_intent.payment_failed`

### 2. 插件配置参数

| 参数 | 说明 | 必填 |
|------|------|------|
| Stripe Secret Key | Stripe API 密钥 | 是 |
| Stripe Publishable Key | Stripe 可发布密钥 | 是 |
| Webhook Secret | Webhook 签名密钥 | 否 |
| 支付方式 | wechat_pay/alipay/both | 是 |
| 货币类型 | 支持的货币代码 | 是 |
| 商品描述 | 显示在支付页面 | 否 |
| 自动确认付款 | 是否自动确认 | 否 |

## 支付流程

### WeChat Pay 流程
1. 用户选择微信支付
2. 系统创建 PaymentIntent
3. 显示二维码供用户扫描
4. 用户在微信中完成支付
5. Stripe 发送 Webhook 通知
6. 系统更新订单状态

### Alipay 流程
1. 用户选择支付宝
2. 系统创建 PaymentIntent
3. 重定向到支付宝支付页面
4. 用户在支付宝中完成支付
5. 返回到系统并处理回调
6. 系统更新订单状态

## 回调处理

插件支持两种回调方式：

1. **Stripe Webhook**（推荐）
   - 实时性更好
   - 安全性更高
   - 需要配置 Webhook Secret

2. **URL 参数回调**
   - 兼容性更好
   - 处理前端确认的支付
   - 通过 payment_intent 参数验证

## 货币支持

- **CNY** - 人民币（默认）
- **USD** - 美元
- **EUR** - 欧元
- **GBP** - 英镑
- **HKD** - 港币
- **JPY** - 日元
- **SGD** - 新币

系统会自动进行汇率转换，确保符合 Stripe 的最小金额要求。

## 错误处理

插件包含完善的错误处理机制：

- 汇率获取失败时使用备用汇率
- 支付金额验证
- Webhook 签名验证
- 详细的日志记录

## 安全特性

- Webhook 签名验证
- 支付金额验证
- PaymentIntent 状态检查
- 订单号防重复处理
- 敏感信息日志过滤

## 故障排除

### 常见问题

1. **支付失败**
   - 检查 Stripe 账户是否启用了 WeChat Pay/Alipay
   - 确认货币类型支持
   - 检查最小金额限制

2. **回调失败**
   - 确认 Webhook URL 配置正确
   - 检查服务器网络连接
   - 验证 Webhook Secret 配置

3. **汇率问题**
   - 检查网络连接
   - 系统会使用备用固定汇率

### 日志查看

支付相关日志会记录在 Laravel 日志中，可以通过以下方式查看：

```bash
# 查看支付日志
tail -f storage/logs/laravel.log | grep "Stripe WeChat/Alipay"

# 查看错误日志
tail -f storage/logs/laravel.log | grep "ERROR"
```

## 开发说明

### 文件结构
```
plugins/StripeWeChatAlipay/
├── Plugin.php                 # 主插件文件
├── config.json               # 插件配置
├── Controllers/
│   └── PaymentController.php # 支付页面控制器
├── resources/views/
│   └── payment.blade.php     # 支付页面模板
├── routes/
│   └── web.php              # 路由定义
└── README.md                # 说明文档
```

### 扩展开发

如需扩展插件功能，可以：

1. 修改 `form()` 方法添加新的配置项
2. 在 `pay()` 方法中添加新的支付逻辑
3. 扩展 `notify()` 方法处理更多 Webhook 事件
4. 添加新的视图模板和路由

## 版本历史

- **v1.0.0** - 初始版本
  - 支持 WeChat Pay 和 Alipay
  - 多货币支持
  - Webhook 回调处理
  - 完整的错误处理和日志记录