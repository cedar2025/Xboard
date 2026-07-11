# macOS ARM 无证书正式版发布

## 支持范围

- macOS 13 及以上
- Apple Silicon `arm64`
- 官网 DMG 分发
- ad-hoc 签名，不使用 Developer ID，不提交 Apple 公证

由于没有 Developer ID，官网下载后 Gatekeeper 会拦截首次启动。用户需要打开
“系统设置 > 隐私与安全性”，点击“仍要打开”。首次开启 TUN 或后台组件版本变化时，
应用会显示 macOS 标准管理员授权框；授权成功后正常连接和断开不再重复询问密码。

## 构建

```bash
./build_macos_beta.sh
```

可选环境变量：

```bash
BASE_URL=https://www.elephant223.com
APP_DISTRIBUTION_URL=https://www.elephant223.com
ALLOW_INSECURE_CERTS=false
```

最终只输出一个发布文件：

- `build/macos-beta/ElephantRoute-macos-arm64.dmg`

脚本会删除 Intel 和 Windows 核心、将通用 Mach-O 裁剪为 arm64、重新进行
ad-hoc 签名、执行 `codesign --verify --deep --strict`，并打印 DMG 的 SHA-256。
DMG 内包含应用、`Applications` 快捷入口和安装说明。外部 `.app` 仅作为构建、
裁剪和签名的中间产物，DMG 创建成功后会自动删除。

`BASE_URL` 只作为动态域名解析失败时的内置回退地址。构建脚本默认不写入
`APP_DISTRIBUTION_URL`，因此更新检查与业务 API 共用启动时解析出的动态域名；
只有发布环境显式传入 `APP_DISTRIBUTION_URL` 时才使用独立的固定更新入口。

## 发布元数据

上传 DMG 时使用：

```text
app_key=elephant-route-desktop
platform=macos
arch=arm64
channel=stable
sha256=<构建脚本输出的完整 SHA-256>
```

macOS 客户端拒绝下载没有完整 SHA-256 或校验不一致的更新包。

## 更新与卸载

更新时客户端停止当前连接、下载并校验 DMG，然后打开 DMG。用户退出旧版本，
把新应用拖入 `Applications` 并选择替换，再重新打开。旧版本不会在下载阶段注销
helper，避免用户取消更新后 TUN 失效。新版本首次连接时会比较应用内和系统已安装
helper 的 SHA-256，仅在发生变化时要求管理员授权升级。

卸载前先在应用内断开连接，然后在终端执行固定卸载脚本并输入管理员密码：

```bash
sudo "/Applications/大象网络.app/Contents/Resources/ElephantTunHelper/install-helper.sh" uninstall
```

确认 `/Library/PrivilegedHelperTools/ElephantTunHelper` 和
`/Library/LaunchDaemons/com.elphantroute.elephantNetwork.tunhelper.plist` 已删除后，
再删除应用。如需清除用户数据，再删除：

```text
~/Library/Application Support/com.elphantroute.elephantNetwork
~/Library/Preferences/com.elphantroute.elephantNetwork.plist
```

## 发布硬门槛

必须在从未安装过本应用的独立 ARM Mac 上验证：Gatekeeper 放行、拖入
`Applications`、管理员授权安装后台组件、XPC 状态、TUN 连接/断开、节点切换、
测速、托盘、重启恢复、DMG 覆盖升级及卸载无网络残留。开发机验证不能替代该闸门。
