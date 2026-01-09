# Stripe 支付插件

Xboard 的 Stripe 全方位支付解决方案，支持信用卡、微信支付、支付宝和加密货币。

## 功能特性

- 💳 支持 Card（信用卡/借记卡）
- 💬 支持 WeChat Pay（微信支付）
- 💙 支持 Alipay（支付宝）
- **₿ 支持加密货币支付** 🆕
  - USDC (USD Coin)
  - USDT (Tether)
  - Bitcoin (BTC)
  - 其他 Stripe 支持的加密货币
- **🆕 双模式支付体验：**
  - **Stripe 原生 Checkout 页面**（推荐）- 官方托管，支持所有支付方式
  - **自定义支付页面** - 传统模式，完全控制用户界面
- 多货币支持（CNY, USD, EUR, GBP, HKD, JPY, SGD）
- 自动汇率转换
- Webhook 回调验证
- 与 Xboard 支付系统完美集成

## 🌟 新版本亮点

### v1.3.0 - 加密货币支付支持 🆕
- **✨ 支持 USDC、USDT、Bitcoin 等加密货币支付**
- 与传统支付方式无缝集成
- 使用 Stripe 官方 Checkout 页面，安全可靠
- 支持 USD 和 EUR 货币的加密货币支付
- 详细配置指南：查看 [CRYPTO_SETUP.md](./CRYPTO_SETUP.md)

### Stripe Checkout 全支付方式支持
- **现在 Stripe Checkout 页面支持 Card + WeChat Pay + Alipay + Crypto 全部支付方式** ✨
- 用户在同一个专业页面可以选择任意支付方式
- 更高的用户信任度和转化率
- 内置欺诈检测和 3D Secure 认证
- 支持多种语言自适应
- 自动处理 PCI 合规要求

## 安装配置

### 1. Stripe 账户配置

1. 登录 [Stripe Dashboard](https://dashboard.stripe.com/)
2. 在 Settings > Payment methods 中启用：
   - WeChat Pay（微信支付）
   - Alipay（支付宝）
   - **Customer Balance（用于加密货币支付）** 🆕
3. 获取 API 密钥：
   - Secret Key (sk_live_... 或 sk_test_...)
   - Publishable Key (pk_live_... 或 pk_test_...)
4. 配置 Webhook 端点：
   - URL: `https://yourdomain.com/api/v1/guest/payment/notify/Stripe/{uuid}`
   - 事件: `payment_intent.succeeded`, `payment_intent.payment_failed`, `checkout.session.completed`

### 🆕 1.1 启用加密货币支付（可选）

**如需支持加密货币支付，请完成以下步骤：**

1. 确保您的 Stripe 账号支持加密货币支付（部分地区功能可用）
2. 在 Stripe Dashboard > Settings > Payment methods 中启用 **Customer Balance**
3. 阅读详细配置指南：[CRYPTO_SETUP.md](./CRYPTO_SETUP.md)
4. 在插件配置中：
   - 选择 "Stripe 官方支付页面 + 加密货币" 模式
   - 启用加密货币支付选项
   - 将货币类型设置为 **USD** 或 **EUR**

### 2. 插件配置参数

| 参数 | 说明 | 必填 | 备注 |
|------|------|------|------|
| Stripe Secret Key | Stripe API 密钥 | 是 | sk_live_... 或 sk_test_... |
| Stripe Publishable Key | Stripe 可发布密钥 | 是 | pk_live_... 或 pk_test_... |
| Webhook Secret | Webhook 签名密钥 | 否（推荐） | whsec_... |
| 支付方式模式 | 支付方式组合选择 | 是 | 见下方选项说明 |
| **启用加密货币支付** 🆕 | 是否启用加密货币 | 否 | 启用后支持 USDC/USDT/BTC |
| **启用货币选择** 🆕 | 用户可在支付页面选择货币 | 否 | 例如：网站显示CNY，用户可选USD支付 |
| 货币类型 | 支持的货币代码 | 是 | 加密货币需使用 USD 或 EUR |
| 商品描述 | 显示在支付页面 | 否 | 默认：订阅服务 |
| **Logo URL** 🆕 | 支付页面显示的 Logo | 否 | HTTPS 链接，建议 512x512px |
| 自动确认付款 | 是否自动确认 | 否 | 推荐：是 |

#### 支付方式模式选项

| 选项 | 说明 | 支持的支付方式 |
|------|------|----------------|
| WeChat Pay | 仅微信支付（自定义页面） | 微信支付 |
| Alipay | 仅支付宝（自定义页面） | 支付宝 |
| Card | 仅信用卡（自定义页面） | 信用卡/借记卡 |
| Stripe 官方支付页面 | Stripe Checkout（推荐） | Card + WeChat + Alipay |
| **Stripe 官方支付页面 + 加密货币** 🆕 | Stripe Checkout + Crypto | Card + WeChat + Alipay + Crypto |
| 兼容模式 | 旧版配置兼容 | 全部传统方式 |

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

### Stripe Checkout 流程（🆕 推荐模式）
1. 用户发起支付请求
2. 系统创建 Stripe Checkout Session
3. **用户被重定向到 Stripe 官方专业支付页面**
4. 用户在 Stripe 页面选择支付方式：
   - 💳 **Card** - 输入信用卡/借记卡信息
   - 💬 **WeChat Pay** - 显示微信支付二维码
   - 💙 **Alipay** - 跳转到支付宝支付
5. 支付完成后自动返回到系统
6. 系统通过 Webhook 或回调更新订单状态

### 自定义页面流程（传统模式）
当关闭 Checkout 页面选项时，将使用原有的自定义支付页面流程

## 回调处理

插件支持两种回调方式：

1. **Stripe Webhook**（推荐）
   - 实时性更好
   - 安全性更高
   - 需要配置 Webhook Secret

2. **URL 参数回调**
   - 兼容性更好
   - 处理前端确认的支付
   - 支持 payment_intent 和 session_id 参数验证

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
plugins/Stripe/
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

- **v1.3.0** - 加密货币支付支持 🆕 (2025-01-09)
  - ₿ **新增：支持 USDC、USDT、Bitcoin 等加密货币支付**
  - 🔧 **新增：可配置加密货币支付开关**
  - 🌟 **新增：stripe_checkout_crypto 支付模式**
  - 📚 **新增：详细的加密货币配置指南 (CRYPTO_SETUP.md)**
  - 🛡️ **优化：加密货币支付的日志和错误处理**
  - ⚡ **改进：支持 USD 和 EUR 货币的加密货币支付**

- **v2.0.0** - 重大更新 🎉
  - ✨ **新增：Card 支付使用 Stripe 官方 Checkout 页面**
  - 🔧 **改进：提升支付页面专业度和用户信任度**
  - 🛡️ **增强：内置 PCI 合规和欺诈检测**
  - 🌍 **优化：支持多语言自适应界面**
  - 📚 **更新：完善文档和配置说明**

- **v1.0.0** - 初始版本
  - 支持 WeChat Pay 和 Alipay
  - 多货币支持
  - Webhook 回调处理
  - 完整的错误处理和日志记录

## 推荐配置

### 💡 标准配置（推荐大多数用户）
- **支付方式模式**: `Stripe 官方支付页面 - 支持 Card/WeChat/Alipay`
- **启用加密货币**: `否`
- **货币类型**: 根据您的目标用户选择（CNY/USD/EUR等）

**这样配置的优势：**
- 用户在一个专业页面就能使用所有主流支付方式
- Stripe 官方页面，用户信任度更高
- 自动处理复杂的安全认证流程
- 转化率通常比自定义页面更高
- PCI 合规由 Stripe 自动处理

### ₿ 加密货币配置（面向Web3用户）
如果您的用户群包含加密货币持有者：
- **支付方式模式**: `Stripe 官方支付页面 + 加密货币`
- **启用加密货币**: `是 - 启用 USDC/USDT/Bitcoin 等加密货币`
- **货币类型**: `USD` 或 `EUR`（加密货币仅支持这两种）

**这样配置的优势：**
- 满足 Web3 用户的支付需求
- USDC/USDT 等稳定币交易费用低
- 支持 Bitcoin 等主流加密货币
- 全球通用，无需跨境支付手续费
- 吸引更多加密货币持有者

**⚠️ 注意事项：**
- 加密货币支付目前仅支持 USD 和 EUR 货币
- 需要在 Stripe Dashboard 中启用对应的支付方式
- 建议先在测试环境验证配置

## 支付方式对比

| 特性 | Stripe Checkout 页面 | Stripe Checkout + Crypto 🆕 | 自定义支付页面 |
|------|---------------------|---------------------------|----------------|
| 用户信任度 | ⭐⭐⭐⭐⭐ 官方页面 | ⭐⭐⭐⭐⭐ 官方页面 | ⭐⭐⭐ 自制页面 |
| 安全性 | ⭐⭐⭐⭐⭐ PCI合规自动处理 | ⭐⭐⭐⭐⭐ PCI合规自动处理 | ⭐⭐⭐⭐ 需要自行维护 |
| 支付方式 | Card + WeChat + Alipay | Card + WeChat + Alipay + Crypto | Card + WeChat + Alipay |
| 加密货币支持 | ❌ 不支持 | ✅ USDC/USDT/BTC | ❌ 不支持 |
| 支持货币 | 多种货币 | USD, EUR | 多种货币 |
| 界面定制 | ⭐⭐ 有限定制 | ⭐⭐ 有限定制 | ⭐⭐⭐⭐⭐ 完全控制 |
| 维护成本 | ⭐⭐⭐⭐⭐ 零维护 | ⭐⭐⭐⭐⭐ 零维护 | ⭐⭐⭐ 需要更新 |
| 转化率 | ⭐⭐⭐⭐⭐ 通常更高 | ⭐⭐⭐⭐⭐ Web3用户更高 | ⭐⭐⭐ 取决于设计 |
| 适用场景 | 大多数用户 | Web3/加密货币用户 | 需要特殊定制 |