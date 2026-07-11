# macOS 无证书管理员 LaunchDaemon 设计

## 背景与结论

当前 Apple Silicon macOS 26.5.2 已验证：ad-hoc 签名的 `ElephantTunHelper`
通过 `SMAppService` 注册后，会在执行 helper 代码前被系统以
`SIGKILL (Code Signature Invalid)` 终止，崩溃原因为
`Launch Constraint Violation`。因此现有 `SMAppService + ad-hoc` 不能作为
无证书正式版的 TUN 权限方案。

本设计保持“不使用 Developer ID、不公证、只发布一个 ARM64 DMG”，将 privileged
helper 的安装方式改为管理员授权安装的传统 LaunchDaemon。首次安装以及 helper
版本变化时需要输入管理员密码，正常启动、连接、断开、节点切换不再重复授权。

## 架构

应用包继续内置以下只读资源：

- `Contents/MacOS/ElephantTunHelper`
- `Contents/Resources/ElephantTunHelper/install-helper.sh`
- `Contents/Resources/ElephantTunHelper/com.elphantroute.elephantNetwork.tunhelper.plist`

管理员授权后，固定安装为：

- `/Library/PrivilegedHelperTools/ElephantTunHelper`
- `/Library/LaunchDaemons/com.elphantroute.elephantNetwork.tunhelper.plist`
- `/Library/Application Support/ElephantRoute/helper-install.json`

LaunchDaemon 继续发布固定 Mach service：
`com.elphantroute.elephantNetwork.tunhelper`。Flutter 与原生层仍通过现有
`com.elephant.network/runtime` MethodChannel 和 NSXPC 接口通信，不改变后端 API。

## 安装与升级流程

1. 应用只允许从 `/Applications/大象网络.app` 发起安装；其他位置返回
   `APP_NOT_IN_APPLICATIONS`，不弹管理员授权。
2. 原生层校验应用、内置 helper、安装脚本和 plist 均存在，并校验 helper 为
   ARM64 Mach-O、代码签名结构有效、签名标识为固定 helper 标识。
3. 原生层注销旧的同名 `SMAppService` 记录，避免 BTM 与传统 LaunchDaemon 同名冲突。
4. 原生层使用 `/usr/bin/osascript` 的 `with administrator privileges` 执行固定安装脚本；
   Flutter 不允许传入 shell 文本、路径或命令参数。
5. 安装脚本再次校验所有源路径必须位于固定 `/Applications` 应用包内，然后停止旧
   launchd job，原子覆盖 helper 和 plist，设置 `root:wheel` 所有权以及 0755/0644
   权限，最后执行 `launchctl bootstrap system`。
6. 安装成功后原生层通过 XPC `getStatus` 进行健康检查。成功才返回 `enabled`；失败返回
   `connectionFailed`，同时带上 launchd 状态和明确错误文案。
7. 应用启动时比较已安装 helper 的 SHA-256 与当前应用包内 helper 的 SHA-256。一致则
   不授权、不重装；不一致时状态为 `refreshRequired`，用户点击连接后触发一次管理员授权升级。
8. 用户取消密码框时返回 `AUTHORIZATION_CANCELLED`，保持旧 helper 和旧版本应用可用，
   不进入循环重试。

## 安全边界

- 管理员脚本只支持 `install` 和 `uninstall` 两个固定动作。
- 所有目标路径、launchd label、Mach service、文件名都写死在脚本中。
- 源应用必须解析为 `/Applications/大象网络.app`，拒绝符号链接、`..` 和其他应用路径。
- 安装使用临时文件加原子移动，失败时不删除当前可工作的 helper。
- helper 继续只接受位于当前控制台用户 Application Support 目录下的固定
  `sing-box/config.json` 与 `sing-box-darwin-arm64` 路径。
- XPC 连接至少校验调用方有效 UID 等于当前控制台用户，拒绝其他本地用户；由于没有
  Apple 颁发的 Team ID，同一用户下的进程身份无法达到 Developer ID 方案的强认证等级，
  这是无证书 root helper 的已知风险。
- 不提供任意命令执行、任意文件复制、任意配置路径或来自网络的管理员脚本。

## 状态与界面

公开 helper 状态继续保持：

- `enabled`
- `notRegistered`
- `notFound`
- `refreshRequired`
- `connectionFailed`

新增稳定错误码：

- `APP_NOT_IN_APPLICATIONS`
- `HELPER_INSTALL_REQUIRED`
- `HELPER_REFRESH_REQUIRED`
- `AUTHORIZATION_CANCELLED`
- `HELPER_INSTALL_FAILED`
- `HELPER_HEALTH_CHECK_FAILED`

首次设置文案改为“需要管理员授权安装后台网络组件”，不再引导用户前往“登录项与扩展”。
主开关、托盘连接和自动恢复全部调用同一 `ensureTunHelper` 状态机。只有状态为
`enabled` 且 XPC 健康检查通过时才允许启动 TUN 内核。

## 卸载

DMG 安装说明提供卸载命令入口。卸载需要一次管理员授权，依次执行：

1. 停止 TUN 和 sing-box；
2. 恢复系统代理；
3. 删除本应用创建的固定路由；
4. `launchctl bootout` 同名 LaunchDaemon；
5. 删除固定 helper、plist 和安装元数据；
6. 保留用户登录、订阅和偏好数据，除非用户另外选择清除个人数据。

卸载脚本即使部分资源不存在也应幂等成功。

## 打包与更新

- 继续只输出 `ElephantRoute-macos-arm64.dmg`。
- 继续使用 ad-hoc 签名，不启用 hardened runtime，不调用 Developer ID 或公证脚本。
- DMG 真实启动冒烟测试必须通过，不能只依赖 `codesign --verify`。
- 更新下载仍强制校验 SHA-256。覆盖应用后，新版本首次连接比较 helper 哈希并在需要时
  触发管理员授权升级；取消升级不破坏旧 helper。

## 测试与验收

自动化覆盖：

- 固定路径和命令注入拒绝测试；
- helper 哈希一致、缺失、变化的状态机测试；
- 管理员取消、安装失败、bootstrap 失败和 XPC 健康检查失败映射；
- 安装脚本 shell 语法、plist 合法性、权限与固定目标契约；
- Flutter analyze、全量 Flutter tests、ARM64 包内容和真实启动冒烟测试。

本机集成验收覆盖：

- 清理旧 `SMAppService` 注册；
- 首次管理员授权安装；
- launchd job 以 root 运行并可响应 XPC；
- TUN 连接、断开、节点切换、测速；
- 应用退出和重启后的状态恢复；
- helper 覆盖升级；
- 用户取消授权不破坏当前版本；
- 卸载后无 helper、launchd job、sing-box、路由和系统代理残留。

发布硬门槛仍是在一台未安装过本应用的 ARM Mac 上完成同一流程；开发机成功仅作为
集成验证，不替代干净环境验收。
