# 下线废弃公开 HTML 页面设计

## 目标

删除以下不再需要的公开静态页面，使对应 URL 在部署后不再提供内容，并由搜索引擎在重新抓取后逐步移出索引：

- `/pricing.html`
- `/privacy.html`
- `/refund.html`
- `/terms.html`
- `/assets/admin/index.html`

## 范围

只删除这五个已纳入 Git 管理的 HTML 文件：

- `public/pricing.html`
- `public/privacy.html`
- `public/refund.html`
- `public/terms.html`
- `public/assets/admin/index.html`

不修改 Laravel 路由、`robots.txt`、主题脚本、下载页、AI 客服入口或其他静态资源。

## 保留行为

- `/download/index.html` 继续供登录用户打开软件下载页。
- `/support/ai` 继续作为 AI 客服兼容入口。
- `/assets/admin/assets/*` 与 `/assets/admin/locales/*` 继续供真实后台页面加载。
- `/` 与 `/app` 的登录入口行为保持不变。
- 当前工作树中的客户端与 `routes/web.php` 未提交改动不属于本次范围。

## 下线行为

部署删除后的文件后，这五个 URL 将由现有 Web 服务器回退规则处理，预期返回现有的未找到响应。此次不新增 `410 Gone` 路由或专用 SEO 中间件。

## 验证

1. 确认五个目标文件已从 Git 工作树删除。
2. 确认 `/download/index.html`、AI 客服视图、后台构建资源仍然存在。
3. 搜索仓库，确认没有误删目录或修改范围外文件。
4. 运行与公开页面、下载页和后台静态资源相关的现有测试；若仓库没有对应测试，则以文件存在性和 Git 差异审计作为主要证据。

## 风险与恢复

- 搜索引擎退索引依赖其重新抓取，删除文件不会保证即时从搜索结果消失。
- 如果线上 Web 服务器保留旧发布文件，部署流程必须同步删除目标文件，不能只覆盖新增内容。
- 恢复时可从 Git 历史还原对应文件，不影响其他页面与路由。
