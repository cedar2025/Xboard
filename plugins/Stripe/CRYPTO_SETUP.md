# Stripe 加密货币支付配置指南

## 🚀 功能概述

Stripe 支付插件现已支持加密货币支付！用户可以使用以下方式进行支付：

### 支持的支付方式
- 💳 **信用卡/借记卡** (Card)
- 💬 **微信支付** (WeChat Pay)
- 💙 **支付宝** (Alipay)
- ₿ **稳定币** (Stablecoin)
  - USDC (USD Coin) - 以太坊、Solana、Polygon、Base 网络
  - USDP (Pax Dollar) - 以太坊、Solana 网络
  - USDG (Global Dollar) - 以太坊网络

## 📋 前置要求

### 1. Stripe 账号要求
- 需要拥有 Stripe 企业账号
- 账号需要完成 KYC 验证
- 账号状态为正常可用

### 2. 启用稳定币支付

⚠️ **重要限制**（基于 [Stripe 官方文档](https://docs.stripe.com/payments/stablecoin-payments)）：

- **仅限美国企业**：目前只有美国注册的企业账户可以启用此功能
- **仅支持 USD**：稳定币支付只能以美元结算
- **单笔限额**：最高 $10,000 USD
- **需要身份验证**：客户需完成 KYC 认证

**启用步骤**：

1. 登录 [Stripe Dashboard](https://dashboard.stripe.com/)
2. 进入 **Settings** → **Payment methods**
3. 找到 **Stablecoin payments** 或 **Crypto**（显示名称可能不同）
4. 点击 **Enable** 启用
5. 完成必要的配置和合规要求

### 3. 货币限制
⚠️ **重要提示**：稳定币支付**仅支持 USD 货币**

- ✅ **USD** (美元) - 必须使用
- ❌ **其他货币**（CNY、EUR、GBP 等）- 不支持稳定币

**解决方案**：如果您的网站使用其他货币（如 CNY），可以启用**多货币选择功能**：
- 主货币显示：CNY ¥50
- 备选货币：USD $7.15 ← 用户可选择此项使用稳定币支付
- 配置方法：启用 "货币选择（多货币显示）" 选项

## ⚙️ 插件配置步骤

### 步骤 1：基础配置
在 Xboard 管理后台的插件配置中填写：

| 配置项 | 说明 | 示例 |
|--------|------|------|
| Stripe Secret Key | 从 Stripe Dashboard 获取 | `sk_live_...` 或 `sk_test_...` |
| Stripe Publishable Key | 从 Stripe Dashboard 获取 | `pk_live_...` 或 `pk_test_...` |
| Webhook Secret | 用于验证回调（推荐配置） | `whsec_...` |

### 步骤 2：选择支付方式模式
在 **支付方式模式** 下拉菜单中选择：

**🆕 推荐选项：**
```
Stripe 官方支付页面 + 加密货币 - 支持 Card/WeChat/Alipay/Crypto
```

此选项将启用所有支持的支付方式，包括加密货币。

### 步骤 3：启用加密货币（标记功能）
在 **启用加密货币支付** 选项中选择：
```
是 - 启用 USDC/USDT/Bitcoin 等加密货币
```

**注意**：这个选项主要用于配置标记。稳定币支付会由 **Stripe 自动检测和显示**，只要：
1. ✅ 在 Stripe Dashboard 中已启用稳定币支付
2. ✅ 使用 USD 货币
3. ✅ 客户位于支持的地区

### 步骤 4：设置货币类型
⚠️ **关键步骤**：稳定币**必须使用 USD**

**选项 A：主货币直接使用 USD**
- 货币类型：**USD (美元)**
- 效果：所有金额直接显示为美元

**选项 B：使用多货币选择（推荐给非美国网站）**
- 货币类型：**CNY** 或其他
- 启用货币选择：**是**
- 效果：主货币显示 CNY，用户可选择 USD 支付（使用稳定币）

### 步骤 5：其他配置
- **商品描述**：自定义显示在支付页面的商品描述（可选）
- **Logo URL**：自定义支付页面 Logo（可选）
- **启用货币选择**：如果主货币不是 USD，建议启用（可选）
- **自动确认付款**：建议选择 "是"

## 🔗 配置 Webhook

为确保支付回调正常工作，需要在 Stripe Dashboard 配置 Webhook：

### Webhook 端点 URL
```
https://yourdomain.com/api/v1/guest/payment/notify/Stripe/{uuid}
```

将 `yourdomain.com` 替换为您的实际域名，`{uuid}` 会自动填充。

### 需要监听的事件
在 Stripe Dashboard 的 Webhook 配置中，选择以下事件：
- ✅ `payment_intent.succeeded` - 支付成功
- ✅ `payment_intent.payment_failed` - 支付失败
- ✅ `checkout.session.completed` - Checkout 会话完成

### 获取 Webhook Secret
配置完成后，Stripe 会生成一个 Webhook Secret（格式：`whsec_...`），将其填入插件配置的 **Webhook Secret** 字段。

## 💡 用户支付流程

### 使用加密货币支付的步骤

1. **选择商品/套餐** → 用户在您的网站选择要购买的商品
2. **选择支付方式** → 选择 "Stripe 支付 + 加密货币"
3. **跳转 Stripe Checkout** → 系统自动跳转到 Stripe 官方支付页面
4. **选择加密货币** → 在 Stripe 页面选择加密货币支付选项
   - 可选择 USDC、USDT、Bitcoin 等
5. **完成支付** → 按照 Stripe 页面提示完成加密货币转账
6. **自动返回** → 支付完成后自动返回您的网站
7. **订单激活** → 系统通过 Webhook 自动激活订单

### 支付页面示例
用户在 Stripe Checkout 页面会看到：
```
┌─────────────────────────────────────┐
│  Stripe 支付页面                     │
├─────────────────────────────────────┤
│  支付方式选择:                       │
│  ○ 信用卡/借记卡                     │
│  ○ 微信支付                          │
│  ○ 支付宝                            │
│  ● 加密货币 (USDC/USDT/BTC)         │
├─────────────────────────────────────┤
│  选择加密货币:                       │
│  ○ USDC (USD Coin)                  │
│  ○ USDT (Tether)                    │
│  ○ Bitcoin                          │
├─────────────────────────────────────┤
│  [继续支付]                          │
└─────────────────────────────────────┘
```

## 🔍 测试验证

### 测试模式（推荐先测试）
1. 使用测试密钥：`sk_test_...` 和 `pk_test_...`
2. 进行小额测试支付
3. 检查 Webhook 是否正常触发
4. 确认订单状态是否正确更新

### 生产环境上线
确认测试通过后：
1. 切换为生产密钥：`sk_live_...` 和 `pk_live_...`
2. 更新 Webhook 配置为生产环境 URL
3. 监控第一笔真实交易

## 📊 日志和调试

### 查看支付日志
```bash
# 查看加密货币支付相关日志
tail -f storage/logs/laravel.log | grep "Stripe\|crypto"

# 查看 Stripe Checkout 会话创建日志
tail -f storage/logs/laravel.log | grep "Checkout会话"

# 查看 Webhook 回调日志
tail -f storage/logs/laravel.log | grep "webhook"
```

### 常见日志信息
成功启用加密货币时，您会看到：
```
[时间] local.INFO: 已启用加密货币支付 {"currency":"USD","payment_method":"stripe_checkout_crypto","supported_crypto":"USDC, USDT, etc."}
```

创建 Checkout 会话成功时：
```
[时间] local.INFO: Stripe Checkout会话创建成功 {"session_id":"cs_test_...","payment_method_types":["card","wechat_pay","alipay","customer_balance"]}
```

## ⚠️ 常见问题

### Q1: 用户在支付页面看不到加密货币选项？
**A:** 检查以下几点：
- ✅ 货币类型是否设置为 USD 或 EUR
- ✅ 是否已在 Stripe Dashboard 启用了加密货币支付
- ✅ 插件配置中是否已启用加密货币
- ✅ 是否选择了 "stripe_checkout_crypto" 支付模式

### Q2: 支付完成后订单未激活？
**A:** 可能原因：
- ❌ Webhook URL 配置错误
- ❌ Webhook Secret 未配置或错误
- ❌ 服务器防火墙拦截了 Stripe 的回调请求
- **解决方案**：检查 Webhook 配置和日志

### Q3: 加密货币支付失败？
**A:** 检查：
- 支付金额是否符合 Stripe 最小金额要求（USD: $0.50 / EUR: €0.50）
- 用户的加密货币钱包余额是否足够
- 网络连接是否正常

### Q4: 使用 CNY 货币时如何支持加密货币？
**A:** 加密货币支付目前仅支持 USD 和 EUR。如果您使用 CNY：
- **方案 1**：将货币改为 USD，系统会自动进行汇率转换
- **方案 2**：创建两个支付插件实例，一个用 CNY（不含加密货币），一个用 USD（含加密货币）

## 🔒 安全建议

### 1. 密钥管理
- ⚠️ 永远不要将 Secret Key 提交到代码仓库
- 🔐 定期轮换 API 密钥
- 🛡️ 生产环境使用 `sk_live_...`，测试使用 `sk_test_...`

### 2. Webhook 安全
- ✅ 务必配置 Webhook Secret 进行签名验证
- ✅ 仅处理来自 Stripe 的合法请求
- ✅ 验证支付金额和订单信息

### 3. 金额验证
- ✅ 在服务端验证支付金额
- ✅ 不要信任客户端传递的金额
- ✅ 确认货币类型正确

## 📈 性能优化

### 汇率缓存
系统会自动缓存汇率 5 分钟，减少 API 调用：
```php
// 汇率会缓存 5 分钟
cache()->put("stripe_exchange_rate_{$from}_{$to}", $rate, 300);
```

### Stripe API 调用优化
- 使用 Stripe Checkout 可以减少自定义页面的维护成本
- Webhook 异步处理避免阻塞用户请求

## 📞 技术支持

如果遇到问题：

1. **查看日志**：`storage/logs/laravel.log`
2. **检查 Stripe Dashboard**：查看支付事件和 Webhook 日志
3. **参考官方文档**：[Stripe Checkout 文档](https://stripe.com/docs/payments/checkout)
4. **社区支持**：提交 Issue 到 GitHub 仓库

## 🎉 完成配置

配置完成后，您的网站将支持：
- ✅ 信用卡/借记卡支付
- ✅ 微信支付（中国用户）
- ✅ 支付宝（中国用户）
- ✅ Google Pay（自动检测）
- ✅ 加密货币支付（USDC/USDT/BTC）

所有支付方式都在同一个专业的 Stripe Checkout 页面完成，提供最佳的用户体验和最高的转化率！

---

**版本信息**：v1.3.0
**更新日期**：2025-01-09
**作者**：Xboard Team
