# macOS VPN 生命周期稳定性设计

## 背景

macOS 客户端最近一次实机日志显示，TUN 在成功连接约 3 分 17 秒后收到了明确的 `stopTun requested`。该会话没有 `sing-box` 崩溃或五分钟超时，因此故障边界位于 App/VPN 生命周期，而不是节点或内核定时器。

当前实现同时存在三条停止路径：

- 顶层 `Provider<VpnManager>` 销毁时调用 `VpnManager.dispose()`；
- `VpnProvider.dispose()` 再次调用同一个 `VpnManager.dispose()`；
- `MacosVpnService.dispose()` 以未等待的异步调用执行 `stop()`。

原生端还会在 App 终止时执行 `stopCoreInternal()`。今天的系统崩溃报告进一步显示，`stopCoreInternal()` 中旧式 `Process.launch()` 产生了无法由现有代码捕获的异常，最终触发 `Abort trap: 6`。

## 目标

- VPN 管理器只有一个明确的生命周期所有者。
- 关闭或隐藏主窗口、页面跳转和普通 Widget 销毁不得停止 VPN。
- 用户关闭 VPN、从托盘退出 App、安装更新或使用 `Command+Q` 真正退出 App 时，必须断开 VPN并恢复网络状态。
- 多条停止请求同时到达时，停止操作只执行一次，其他调用复用同一个结果。
- 原生命令启动失败必须返回结构化错误，不能使 App 崩溃。
- 日志能够区分 VPN 停止来源。

## 非目标

- 不改变订阅、节点选择、DNS 策略或代理协议配置。
- 不让 VPN 在用户彻底退出 App 后继续运行。
- 不重构 Windows、Android 的 VPN 生命周期。
- 不在本次修复中替换现有 LaunchDaemon/TUN Helper 架构。

## 方案选择

采用“单一所有权 + 幂等停止”方案。

仅删除一次重复 `dispose()` 无法处理原生并发停止和 `Process.launch()` 崩溃；让 Helper 在 App 退出后继续运行又不符合产品要求。因此，由顶层 `Provider<VpnManager>` 作为唯一对象所有者，所有产品行为通过显式、可等待的停止入口完成。

## 生命周期设计

### Flutter 层

- 顶层 `Provider<VpnManager>` 继续负责最终释放对象。
- `VpnProvider.dispose()` 只清理它自己创建的流量轮询和状态订阅，不再释放注入的 `VpnManager`。
- `MacosVpnService.dispose()` 不再以 fire-and-forget 方式调用 `stop()`；对象释放仅关闭自身 Dart 资源。
- 用户点击关闭开关时，`VpnProvider.disconnect()` 以 `user_toggle` 作为停止来源并等待停止结束。
- 托盘“退出应用”先以 `tray_exit` 断开，再销毁窗口和退出进程。
- macOS 更新安装前以 `update_install` 停止内核。

### 原生层

- `applicationWillTerminate` 保留为最后一道同步兜底，并记录 `app_terminate`。
- `stopCore` MethodChannel 接受可选的 `reason`，原生日志必须输出该来源。
- `stopCoreInternal()` 通过串行保护确保幂等；并发调用不得同时执行 `pkill`、Helper XPC 停止和代理恢复。
- `runCommand()` 改用 `Process.executableURL` 与可抛错的 `process.run()`；启动失败写入日志并返回失败结果，不再使用旧式 `launch()`。
- 停止流程必须继续尝试 Helper 停止和代理恢复，即使某个清理命令失败。

## 状态与错误处理

- 第一个停止调用把 VPN 状态切换为 `disconnecting`。
- 同期重复调用等待正在进行的停止 Future，不重复触发原生清理。
- 停止成功后进入 `disconnected`；代理恢复失败进入现有的 `restoreFailed`。
- 原生命令失败记录 executable、参数和错误文本，但日志不得包含用户凭据或完整订阅内容。
- `dispose()` 关闭流和订阅前必须避免向已关闭的 `StreamController` 继续发送状态。

## 日志

停止日志至少包含：

- `stop requested reason=<reason>`；
- 是否复用了正在执行的停止；
- core/helper 是否仍在运行；
- 系统代理恢复结果；
- 命令启动或退出错误。

允许的停止来源包括 `user_toggle`、`tray_exit`、`app_terminate`、`update_install`、`node_switch` 和 `startup_recovery`。

## 测试与验收

### 自动验证

- 单元测试证明 `VpnProvider.dispose()` 不释放外部注入的 `VpnManager`。
- 单元测试证明 macOS VPN 的并发 `stop()` 只调用一次原生停止。
- 现有 Flutter、Node 测试全部通过。
- `flutter analyze` 无问题。
- macOS Debug 和 Release 构建成功。

### 实机验收

先关闭 V2BOX、Clash Verge、Karing 等其他 VPN，避免多个 TUN 干扰：

1. 开启 VPN 并持续访问网络至少 10 分钟，期间不得自行停止。
2. 关闭主窗口并等待至少 5 分钟，VPN 必须保持连接。
3. 再次显示窗口，状态与实际网络必须一致。
4. 点击关闭开关，TUN 内核停止且普通网络立即恢复。
5. 再次连接后从托盘退出，VPN 自动断开且无残留大象网络进程或路由。
6. 再次连接后使用 `Command+Q`，结果与托盘退出一致。

## 回滚

改动限定在 macOS 生命周期、停止协议和对应测试。若实机验证出现回归，可整体回滚本修复提交；订阅配置和用户数据格式未发生变化，不需要数据迁移。
