# Hiddify Panel Integration

该插件用于将 XBoard 与 Hiddify 管理面板对接，自动在用户支付后创建 Hiddify 用户并将订阅链接返回给客户端。

## 安装步骤

1. 在后台插件管理页面安装并启用 `Hiddify Panel` 插件。
2. 进入插件配置，填写：
   - `Hiddify 管理面板地址`
   - `管理员账号`
   - `管理员密码`
3. 启用自动销售功能。
4. 如果面板使用自签名证书，请关闭 SSL 验证。
5. 如 Hiddify API 未直接返回订阅链接，可使用 `订阅链接模板` 定制 `/{username}` 或 `/sub/{id}` 形式的链接。

## 功能说明

- 订单完成后自动创建 Hiddify 面板用户。
- 若 Hiddify 返回订阅链接，则自动保存并在用户订阅接口中展示。
- 通过 `订阅链接模板` 可以自定义链接生成规则。
- 实现基于 Hiddify Manager API 文档：https://hiddify.com/manager/contribution/How-to-use-API-in-HiddifyManager-project/
