# 项目需求文档：大象网络（Elephant Network） - Flutter 渐进式混合客户端

## 1. 项目概述
- 项目名称：**大象网络**（Elephant Route）
- 项目 Slogan：CONNECT THE UNSEEN  
  极速 · 隐秘 · 无界  
  专为极客打造的下一代全球网络加速服务。突破物理边界，重塑数字自由。
- 面板地址：https://www.elphantroute.com/
- 目标平台（当前阶段）：macOS + Windows + Android
- 暂不开发：iOS（后续视情况考虑）
- 核心架构：**渐进式混合开发**  
  - 高频核心功能（登录、首页、节点选择、代理控制、流量统计）→  原生实现  
  - 低频/复杂/强后台依赖功能（套餐购买详情、订单历史、公告、用户协议、客服等）→ WebView 嵌入面板对应页面  
-

## 3. 关键技术流程

### 3.1 登录 & Token 管理
- Flutter 原生登录页 → POST https://www.elphantroute.com/api/v1/passport/auth/login
- 保存 token（flutter_secure_storage）
- 全局 dio 拦截器自动带 Bearer Token
- token 失效 → 清空 + 跳转登录页

### 3.2 代理核心控制（全原生）
- sing-box 启动/停止（TUN 模式优先）
- Android：VpnService 前台服务 + 通知栏网速
- Windows/macOS：TUN 或系统代理 fallback + 托盘菜单
- 实时流量：轮询 clash_api /traffic 接口

### 3.3 订阅与节点（全原生）
- GET https://www.elphantroute.com/api/v1/client/subscribe → 解析 sing-box 配置
- 原生展示节点列表、分组、延迟测试
- 支持手动/自动切换

### 3.4 支付 & 套餐（混合）
- 原生套餐列表页：展示基础信息（名称、价格、流量、周期）
- 点击订阅 → 打开套餐详情页，然后创建订单 API → 拿到 pay_url
- 打开 WebView 加载 pay_url（全屏或半屏）
- 支付成功判断：监听 URL 跳转 / 轮询订单状态 / JS 通信
- 成功后返回原生页面刷新用户信息

### 3.5 WebView 嵌入规则
- 统一封装 WebViewPage，支持：
  - 自动注入 token（URL 参数或 JS）
  - 隐藏非必要元素（header、footer、sidebar）
  - 与原生通信（支付成功、页面关闭等事件）
  - 深色模式适配、加载动画、错误重试
- 优先 bypass 面板域名（direct 规则），避免代理循环

## 4. 渐进迭代路线图

Phase 1（MVP - 核心闭环，2.5–4 个月）
- 原生：登录、首页仪表盘、节点列表、代理开关、实时流量
- WebView：套餐购买页、支付页
- 桌面端：托盘菜单 + 开机自启

Phase 2（体验优化，+1–2 个月）
- 原生：简版套餐列表、用户中心基础信息
- WebView：订单历史、公告
- 优化：节点自动选择、通知栏/托盘网速美化

Phase 3（高级功能，+2–4 个月）
- 原生：完整设置页、日志查看
- WebView：客服、帮助中心
- 扩展：Android per-app proxy、中国 App 绕过

Phase 4（深度原生化，长期目标）
- 逐步把高频 WebView 页面替换为原生（用户中心、套餐详情等）
- 目标：WebView 仅保留极低频页面（发票、工单等）
