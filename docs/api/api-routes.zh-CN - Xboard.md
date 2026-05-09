# Xboard API 中文说明
## 路由装载规则

- `/api/v1/*`：来自 `app/Http/Routes/V1/*.php`。
- `/api/v2/*`：来自 `app/Http/Routes/V2/*.php`。
- `/api/v2/{admin_path}/*`：后台管理接口，`{admin_path}` 是后台安全路径，由系统配置动态生成。
- `/`：用户前台入口。
- `/{admin_path}`：后台前端入口。
- `/{subscribe_path}/{token}`：订阅链接入口。

本地扫描到的固定路由总数：**224 条**。

注意：启用的第三方插件可以从自己的 `routes/web.php` 或 `routes/api.php` 动态增加接口。本地下载包里没有发现已存在的插件路由文件。

## 认证方式

| 标识 | 含义 | 常见位置 |
|---|---|---|
| `api` | Laravel API 中间件组，当前未启用统一限流 | 所有 API |
| `client` | 使用用户订阅 token 认证 | 客户端订阅、客户端配置 |
| `user` | 使用 Sanctum 登录态认证普通用户 | 用户中心接口 |
| `admin` | 使用 Sanctum 登录态认证管理员 | 后台管理接口 |
| `log` | 后台 POST 操作审计日志 | 后台管理接口 |
| `server` | V1 节点 token 认证 | 老节点协议接口 |
| `server.v2` | V2 节点/机器 token 认证 | 新节点与机器接口 |
| `web` | Web 路由中间件组 | 前台、后台页面入口 |

## 公开接口

这些接口不需要用户登录，主要用于注册登录、公共配置、支付回调和 Telegram webhook。

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v1/guest/plan/fetch` | 获取公开套餐列表 |
| `POST` | `/api/v1/guest/telegram/webhook` | Telegram Bot webhook 回调 |
| `GET/POST` | `/api/v1/guest/payment/notify/{method}/{uuid}` | 支付平台异步通知回调 |
| `GET` | `/api/v1/guest/comm/config` | 获取游客公共配置 |
| `POST` | `/api/v1/passport/auth/register` | 用户注册 |
| `POST` | `/api/v1/passport/auth/login` | 用户登录 |
| `GET` | `/api/v1/passport/auth/token2Login` | token 快速登录 |
| `POST` | `/api/v1/passport/auth/forget` | 忘记密码 |
| `POST` | `/api/v1/passport/auth/getQuickLoginUrl` | 获取邮件/快速登录链接 |
| `POST` | `/api/v1/passport/auth/loginWithMailLink` | 邮件链接登录 |
| `POST` | `/api/v1/passport/comm/sendEmailVerify` | 发送邮箱验证码 |
| `POST` | `/api/v1/passport/comm/pv` | 访问统计 |

V2 的 `passport` 路由基本复用 V1 控制器，路径为 `/api/v2/passport/...`。

二开注意：

- 登录、注册、找回密码、发送邮箱验证码目前没有统一 API 限流，建议补上 throttle。
- 支付回调是否安全取决于各支付插件的签名校验。
- Telegram webhook 应确保 token/webhook 地址不可预测。

## 客户端订阅接口

需要 `client` 中间件，也就是需要用户订阅 token。

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v1/client/subscribe` | 老版订阅接口 |
| `GET` | `/api/v1/client/app/getConfig` | 获取客户端配置 |
| `GET` | `/api/v1/client/app/getVersion` | 获取客户端版本 |
| `GET` | `/api/v2/client/app/getConfig` | V2 客户端配置 |
| `GET` | `/api/v2/client/app/getVersion` | V2 客户端版本 |
| `GET` | `/{subscribe_path}/{token}` | Web 订阅入口 |

二开注意：

- 订阅 token 等同用户敏感凭证，日志和前端展示要避免泄漏。
- 如果要做订阅短链、一次性订阅或设备限制，建议从这里扩展。

## 用户中心接口

需要 `user` 中间件，即用户已登录。

### 用户资料

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v1/user/info` | 获取当前用户信息 |
| `GET` | `/api/v1/user/resetSecurity` | 重置订阅安全信息 |
| `POST` | `/api/v1/user/changePassword` | 修改密码 |
| `POST` | `/api/v1/user/update` | 更新用户资料 |
| `GET` | `/api/v1/user/getSubscribe` | 获取订阅信息 |
| `GET` | `/api/v1/user/getStat` | 获取用户统计 |
| `GET` | `/api/v1/user/checkLogin` | 检查登录状态 |
| `POST` | `/api/v1/user/transfer` | 余额/佣金转换 |
| `POST` | `/api/v1/user/getQuickLoginUrl` | 获取快速登录链接 |
| `GET` | `/api/v1/user/getActiveSession` | 获取活跃会话 |
| `POST` | `/api/v1/user/removeActiveSession` | 移除活跃会话 |

V2 当前只保留：

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/user/resetSecurity` | 重置订阅安全信息 |
| `GET` | `/api/v2/user/info` | 获取当前用户信息 |

### 用户订单

| 方法 | 路径 | 说明 |
|---|---|---|
| `POST` | `/api/v1/user/order/save` | 创建订单 |
| `POST` | `/api/v1/user/order/checkout` | 订单结账 |
| `GET` | `/api/v1/user/order/check` | 检查订单支付状态 |
| `GET` | `/api/v1/user/order/detail` | 订单详情 |
| `GET` | `/api/v1/user/order/fetch` | 订单列表 |
| `GET` | `/api/v1/user/order/getPaymentMethod` | 获取可用支付方式 |
| `POST` | `/api/v1/user/order/cancel` | 取消订单 |

### 用户内容与工单

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v1/user/plan/fetch` | 获取用户可见套餐 |
| `GET` | `/api/v1/user/invite/save` | 生成邀请码 |
| `GET` | `/api/v1/user/invite/fetch` | 邀请列表 |
| `GET` | `/api/v1/user/invite/details` | 邀请明细 |
| `GET` | `/api/v1/user/notice/fetch` | 用户公告 |
| `POST` | `/api/v1/user/ticket/save` | 新建工单 |
| `POST` | `/api/v1/user/ticket/reply` | 回复工单 |
| `POST` | `/api/v1/user/ticket/close` | 关闭工单 |
| `GET` | `/api/v1/user/ticket/fetch` | 工单列表 |
| `POST` | `/api/v1/user/ticket/withdraw` | 撤回工单消息 |
| `GET` | `/api/v1/user/knowledge/fetch` | 知识库列表 |
| `GET` | `/api/v1/user/knowledge/getCategory` | 知识库分类 |

### 用户服务与营销

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v1/user/server/fetch` | 获取可用节点 |
| `POST` | `/api/v1/user/coupon/check` | 检查优惠券 |
| `POST` | `/api/v1/user/gift-card/check` | 检查礼品卡 |
| `POST` | `/api/v1/user/gift-card/redeem` | 兑换礼品卡 |
| `GET` | `/api/v1/user/gift-card/history` | 礼品卡兑换记录 |
| `GET` | `/api/v1/user/gift-card/detail` | 礼品卡详情 |
| `GET` | `/api/v1/user/gift-card/types` | 礼品卡类型 |
| `GET` | `/api/v1/user/telegram/getBotInfo` | 获取 Telegram Bot 信息 |
| `GET` | `/api/v1/user/comm/config` | 获取用户端公共配置 |
| `POST` | `/api/v1/user/comm/getStripePublicKey` | 获取 Stripe 公钥 |
| `GET` | `/api/v1/user/stat/getTrafficLog` | 获取流量日志 |

## 节点与机器接口

### V1 老节点接口

需要 `server` 或 `server:{type}` 中间件。

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v1/server/UniProxy/config` | 节点拉取配置 |
| `GET` | `/api/v1/server/UniProxy/user` | 节点拉取用户 |
| `POST` | `/api/v1/server/UniProxy/push` | 节点上报流量 |
| `POST` | `/api/v1/server/UniProxy/alive` | 节点在线心跳 |
| `GET` | `/api/v1/server/UniProxy/alivelist` | 在线列表 |
| `POST` | `/api/v1/server/UniProxy/status` | 节点状态上报 |
| `GET` | `/api/v1/server/ShadowsocksTidalab/user` | Shadowsocks 用户拉取 |
| `POST` | `/api/v1/server/ShadowsocksTidalab/submit` | Shadowsocks 流量提交 |
| `GET` | `/api/v1/server/TrojanTidalab/config` | Trojan 配置 |
| `GET` | `/api/v1/server/TrojanTidalab/user` | Trojan 用户拉取 |
| `POST` | `/api/v1/server/TrojanTidalab/submit` | Trojan 流量提交 |

### V2 节点接口

需要 `server.v2` 中间件。

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET/POST` | `/api/v2/server/handshake` | 节点握手 |
| `POST` | `/api/v2/server/report` | 节点上报 |
| `GET` | `/api/v2/server/config` | 拉取节点配置 |
| `GET` | `/api/v2/server/user` | 拉取节点用户 |
| `POST` | `/api/v2/server/push` | 推送流量数据 |
| `POST` | `/api/v2/server/alive` | 心跳 |
| `GET` | `/api/v2/server/alivelist` | 在线列表 |
| `POST` | `/api/v2/server/status` | 状态上报 |

### V2 机器接口

这两个接口在路由层只有 `api` 中间件，但控制器内部会校验 `machine_id` 和 `token`。

| 方法 | 路径 | 说明 |
|---|---|---|
| `POST` | `/api/v2/server/machine/nodes` | 机器拉取自己绑定的节点 |
| `POST` | `/api/v2/server/machine/status` | 机器上报 CPU、内存、磁盘、网络状态 |

二开注意：

- `server_token` 和机器 token 都是高敏感凭证。
- 节点接口适合加 IP 频率限制或签名时间戳，降低 token 泄漏后的滥用风险。

## 后台管理接口

后台接口统一位于：

```text
/api/v2/{admin_path}/...
```

其中 `{admin_path}` 是后台安全路径，不是固定字符串。接口需要 `admin` 中间件，大多数 POST 还会进入 `log` 审计日志。

### 系统配置

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/{admin_path}/config/fetch` | 获取系统配置 |
| `POST` | `/api/v2/{admin_path}/config/save` | 保存系统配置 |
| `GET` | `/api/v2/{admin_path}/config/getEmailTemplate` | 获取邮件模板 |
| `GET` | `/api/v2/{admin_path}/config/getThemeTemplate` | 获取主题模板 |
| `POST` | `/api/v2/{admin_path}/config/setTelegramWebhook` | 设置 Telegram webhook |
| `POST` | `/api/v2/{admin_path}/config/testSendMail` | 测试发信 |

### 邮件模板

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/{admin_path}/mail/template/list` | 邮件模板列表 |
| `GET` | `/api/v2/{admin_path}/mail/template/get` | 获取单个模板 |
| `POST` | `/api/v2/{admin_path}/mail/template/save` | 保存模板 |
| `POST` | `/api/v2/{admin_path}/mail/template/reset` | 重置模板 |
| `POST` | `/api/v2/{admin_path}/mail/template/test` | 测试模板发送 |

### 套餐管理

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/{admin_path}/plan/fetch` | 套餐列表 |
| `POST` | `/api/v2/{admin_path}/plan/save` | 新建/保存套餐 |
| `POST` | `/api/v2/{admin_path}/plan/drop` | 删除套餐 |
| `POST` | `/api/v2/{admin_path}/plan/update` | 更新套餐 |
| `POST` | `/api/v2/{admin_path}/plan/sort` | 套餐排序 |

### 节点管理

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/{admin_path}/server/group/fetch` | 节点分组列表 |
| `POST` | `/api/v2/{admin_path}/server/group/save` | 保存节点分组 |
| `POST` | `/api/v2/{admin_path}/server/group/drop` | 删除节点分组 |
| `GET` | `/api/v2/{admin_path}/server/route/fetch` | 路由规则列表 |
| `POST` | `/api/v2/{admin_path}/server/route/save` | 保存路由规则 |
| `POST` | `/api/v2/{admin_path}/server/route/drop` | 删除路由规则 |
| `GET` | `/api/v2/{admin_path}/server/manage/getNodes` | 获取节点列表 |
| `POST` | `/api/v2/{admin_path}/server/manage/save` | 保存节点 |
| `POST` | `/api/v2/{admin_path}/server/manage/update` | 更新节点 |
| `POST` | `/api/v2/{admin_path}/server/manage/drop` | 删除节点 |
| `POST` | `/api/v2/{admin_path}/server/manage/copy` | 复制节点 |
| `POST` | `/api/v2/{admin_path}/server/manage/sort` | 节点排序 |
| `POST` | `/api/v2/{admin_path}/server/manage/batchDelete` | 批量删除节点 |
| `POST` | `/api/v2/{admin_path}/server/manage/batchUpdate` | 批量更新节点 |
| `POST` | `/api/v2/{admin_path}/server/manage/resetTraffic` | 重置节点流量 |
| `POST` | `/api/v2/{admin_path}/server/manage/batchResetTraffic` | 批量重置节点流量 |
| `GET` | `/api/v2/{admin_path}/server/manage/generateEchKey` | 生成 ECH key |

### 机器管理

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/{admin_path}/server/machine/fetch` | 机器列表 |
| `POST` | `/api/v2/{admin_path}/server/machine/save` | 保存机器 |
| `POST` | `/api/v2/{admin_path}/server/machine/drop` | 删除机器 |
| `POST` | `/api/v2/{admin_path}/server/machine/resetToken` | 重置机器 token |
| `GET` | `/api/v2/{admin_path}/server/machine/getToken` | 获取机器 token |
| `GET` | `/api/v2/{admin_path}/server/machine/installCommand` | 获取机器安装命令 |
| `GET` | `/api/v2/{admin_path}/server/machine/nodes` | 获取机器节点 |
| `GET` | `/api/v2/{admin_path}/server/machine/history` | 获取机器负载历史 |

### 订单与用户

| 方法 | 路径 | 说明 |
|---|---|---|
| `ANY` | `/api/v2/{admin_path}/order/fetch` | 订单列表 |
| `POST` | `/api/v2/{admin_path}/order/update` | 更新订单 |
| `POST` | `/api/v2/{admin_path}/order/assign` | 分配订单 |
| `POST` | `/api/v2/{admin_path}/order/paid` | 手动标记支付 |
| `POST` | `/api/v2/{admin_path}/order/cancel` | 取消订单 |
| `POST` | `/api/v2/{admin_path}/order/detail` | 订单详情 |
| `ANY` | `/api/v2/{admin_path}/user/fetch` | 用户列表 |
| `POST` | `/api/v2/{admin_path}/user/update` | 更新用户 |
| `GET` | `/api/v2/{admin_path}/user/getUserInfoById` | 按 ID 获取用户 |
| `POST` | `/api/v2/{admin_path}/user/generate` | 生成用户 |
| `POST` | `/api/v2/{admin_path}/user/dumpCSV` | 导出用户 CSV |
| `POST` | `/api/v2/{admin_path}/user/sendMail` | 群发邮件 |
| `POST` | `/api/v2/{admin_path}/user/ban` | 封禁用户 |
| `POST` | `/api/v2/{admin_path}/user/resetSecret` | 重置用户密钥 |
| `POST` | `/api/v2/{admin_path}/user/setInviteUser` | 设置邀请人 |
| `POST` | `/api/v2/{admin_path}/user/destroy` | 删除用户 |

二开注意：

- `user/fetch`、`order/fetch`、`coupon/fetch` 支持前端传 filter/sort，但字段未做白名单，建议二开时补上。
- `sendMail`、`ban` 支持批量范围操作，建议加二次确认、权限分级和操作日志脱敏。

### 统计、公告、工单

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/{admin_path}/stat/getOverride` | 总览统计 |
| `GET` | `/api/v2/{admin_path}/stat/getStats` | 图表统计 |
| `GET` | `/api/v2/{admin_path}/stat/getServerLastRank` | 节点最近排行 |
| `GET` | `/api/v2/{admin_path}/stat/getServerYesterdayRank` | 节点昨日排行 |
| `GET` | `/api/v2/{admin_path}/stat/getOrder` | 订单统计 |
| `ANY` | `/api/v2/{admin_path}/stat/getStatUser` | 用户统计 |
| `GET` | `/api/v2/{admin_path}/stat/getRanking` | 排行榜 |
| `GET` | `/api/v2/{admin_path}/stat/getStatRecord` | 统计记录 |
| `GET` | `/api/v2/{admin_path}/stat/getTrafficRank` | 流量排行 |
| `GET` | `/api/v2/{admin_path}/notice/fetch` | 公告列表 |
| `POST` | `/api/v2/{admin_path}/notice/save` | 保存公告 |
| `POST` | `/api/v2/{admin_path}/notice/update` | 更新公告 |
| `POST` | `/api/v2/{admin_path}/notice/drop` | 删除公告 |
| `POST` | `/api/v2/{admin_path}/notice/show` | 显示/隐藏公告 |
| `POST` | `/api/v2/{admin_path}/notice/sort` | 公告排序 |
| `ANY` | `/api/v2/{admin_path}/ticket/fetch` | 工单列表 |
| `POST` | `/api/v2/{admin_path}/ticket/reply` | 回复工单 |
| `POST` | `/api/v2/{admin_path}/ticket/close` | 关闭工单 |

### 优惠券、礼品卡、知识库

| 方法 | 路径 | 说明 |
|---|---|---|
| `ANY` | `/api/v2/{admin_path}/coupon/fetch` | 优惠券列表 |
| `POST` | `/api/v2/{admin_path}/coupon/generate` | 生成优惠券 |
| `POST` | `/api/v2/{admin_path}/coupon/drop` | 删除优惠券 |
| `POST` | `/api/v2/{admin_path}/coupon/show` | 显示/隐藏优惠券 |
| `POST` | `/api/v2/{admin_path}/coupon/update` | 更新优惠券 |
| `ANY` | `/api/v2/{admin_path}/gift-card/templates` | 礼品卡模板列表 |
| `POST` | `/api/v2/{admin_path}/gift-card/create-template` | 创建礼品卡模板 |
| `POST` | `/api/v2/{admin_path}/gift-card/update-template` | 更新礼品卡模板 |
| `POST` | `/api/v2/{admin_path}/gift-card/delete-template` | 删除礼品卡模板 |
| `POST` | `/api/v2/{admin_path}/gift-card/generate-codes` | 生成礼品卡兑换码 |
| `ANY` | `/api/v2/{admin_path}/gift-card/codes` | 礼品卡兑换码列表 |
| `POST` | `/api/v2/{admin_path}/gift-card/toggle-code` | 启用/禁用兑换码 |
| `GET` | `/api/v2/{admin_path}/gift-card/export-codes` | 导出兑换码 |
| `POST` | `/api/v2/{admin_path}/gift-card/update-code` | 更新兑换码 |
| `POST` | `/api/v2/{admin_path}/gift-card/delete-code` | 删除兑换码 |
| `ANY` | `/api/v2/{admin_path}/gift-card/usages` | 礼品卡使用记录 |
| `ANY` | `/api/v2/{admin_path}/gift-card/statistics` | 礼品卡统计 |
| `GET` | `/api/v2/{admin_path}/gift-card/types` | 礼品卡类型 |
| `GET` | `/api/v2/{admin_path}/knowledge/fetch` | 知识库列表 |
| `GET` | `/api/v2/{admin_path}/knowledge/getCategory` | 知识库分类 |
| `POST` | `/api/v2/{admin_path}/knowledge/save` | 保存知识库文章 |
| `POST` | `/api/v2/{admin_path}/knowledge/show` | 显示/隐藏文章 |
| `POST` | `/api/v2/{admin_path}/knowledge/drop` | 删除文章 |
| `POST` | `/api/v2/{admin_path}/knowledge/sort` | 知识库排序 |

### 支付、系统、主题、插件

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/{admin_path}/payment/fetch` | 支付配置列表 |
| `GET` | `/api/v2/{admin_path}/payment/getPaymentMethods` | 获取支付插件方法 |
| `POST` | `/api/v2/{admin_path}/payment/getPaymentForm` | 获取支付配置表单 |
| `POST` | `/api/v2/{admin_path}/payment/save` | 保存支付配置 |
| `POST` | `/api/v2/{admin_path}/payment/drop` | 删除支付配置 |
| `POST` | `/api/v2/{admin_path}/payment/show` | 显示/隐藏支付方式 |
| `POST` | `/api/v2/{admin_path}/payment/sort` | 支付方式排序 |
| `GET` | `/api/v2/{admin_path}/system/getSystemStatus` | 系统状态 |
| `GET` | `/api/v2/{admin_path}/system/getQueueStats` | 队列统计 |
| `GET` | `/api/v2/{admin_path}/system/getQueueWorkload` | 队列负载 |
| `GET` | `/api/v2/{admin_path}/system/getQueueMasters` | Horizon master 信息 |
| `GET` | `/api/v2/{admin_path}/system/getHorizonFailedJobs` | Horizon 失败任务 |
| `ANY` | `/api/v2/{admin_path}/system/getAuditLog` | 后台审计日志 |
| `GET` | `/api/v2/{admin_path}/theme/getThemes` | 主题列表 |
| `POST` | `/api/v2/{admin_path}/theme/upload` | 上传主题包 |
| `POST` | `/api/v2/{admin_path}/theme/delete` | 删除主题 |
| `POST` | `/api/v2/{admin_path}/theme/saveThemeConfig` | 保存主题配置 |
| `POST` | `/api/v2/{admin_path}/theme/getThemeConfig` | 获取主题配置 |
| `GET` | `/api/v2/{admin_path}/plugin/types` | 插件类型 |
| `GET` | `/api/v2/{admin_path}/plugin/getPlugins` | 插件列表 |
| `POST` | `/api/v2/{admin_path}/plugin/upload` | 上传插件包 |
| `POST` | `/api/v2/{admin_path}/plugin/delete` | 删除插件 |
| `POST` | `/api/v2/{admin_path}/plugin/install` | 安装插件 |
| `POST` | `/api/v2/{admin_path}/plugin/uninstall` | 卸载插件 |
| `POST` | `/api/v2/{admin_path}/plugin/enable` | 启用插件 |
| `POST` | `/api/v2/{admin_path}/plugin/disable` | 禁用插件 |
| `GET` | `/api/v2/{admin_path}/plugin/config` | 获取插件配置 |
| `POST` | `/api/v2/{admin_path}/plugin/config` | 保存插件配置 |
| `POST` | `/api/v2/{admin_path}/plugin/upgrade` | 升级插件 |

二开注意：

- `theme/upload` 和 `plugin/upload/install/enable` 都应视为可信代码部署能力。
- 如果后台有多个管理员，建议把主题/插件/系统配置拆成超管权限。
- 审计日志目前对嵌套密钥脱敏不足，保存支付配置、插件配置时尤其要小心。

### 流量重置

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/api/v2/{admin_path}/traffic-reset/logs` | 流量重置日志 |
| `GET` | `/api/v2/{admin_path}/traffic-reset/stats` | 流量重置统计 |
| `GET` | `/api/v2/{admin_path}/traffic-reset/user/{userId}/history` | 用户流量重置历史 |
| `POST` | `/api/v2/{admin_path}/traffic-reset/reset-user` | 手动重置用户流量 |

## Web 页面入口

| 方法 | 路径 | 说明 |
|---|---|---|
| `GET` | `/` | 用户前台页面 |
| `GET` | `/{admin_path}` | 后台管理页面 |
| `GET` | `/{subscribe_path}/{token}` | 订阅入口 |