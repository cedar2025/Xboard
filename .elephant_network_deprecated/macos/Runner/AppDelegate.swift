import Cocoa
import FlutterMacOS

@main
class AppDelegate: FlutterAppDelegate {
  
  override func applicationDidFinishLaunching(_ notification: Notification) {
    if let controller = mainFlutterWindow?.contentViewController as? FlutterViewController {
      let proxyChannel = FlutterMethodChannel(name: "com.elephant.network/proxy",
                                              binaryMessenger: controller.engine.binaryMessenger)
      proxyChannel.setMethodCallHandler { [weak self] (call, result) in
        switch call.method {
        case "enableProxy":
          if let args = call.arguments as? [String: Any],
             let port = args["port"] as? Int {
            let success = self?.setSystemProxy(enable: true, port: port) ?? false
            result(success)
          } else {
            result(FlutterError(code: "INVALID_ARGS", message: "Port is required", details: nil))
          }
        case "disableProxy":
          let success = self?.setSystemProxy(enable: false, port: 0) ?? false
          result(success)
        default:
          result(FlutterMethodNotImplemented)
        }
      }
    }
  }

  // 辅助函数：执行 shell 命令修改 macOS 网络代理 (SOCKS 和 HTTP/HTTPS)
  private func setSystemProxy(enable: Bool, port: Int) -> Bool {
    // 简单起见：假设用户主力网卡是 Wi-Fi。生产环境可枚举并找出 active interface
    let interface = "Wi-Fi" // TODO: 动态获取当前活动网卡
    let host = "127.0.0.1"

    let task = Process()
    task.launchPath = "/usr/sbin/networksetup"
    
    if enable {
      // 开启 SOCKS 代理
      _ = runShell("/usr/sbin/networksetup", args: ["-setsocksfirewallproxy", interface, host, String(port)])
      _ = runShell("/usr/sbin/networksetup", args: ["-setsocksfirewallproxystate", interface, "on"])
      // 可选：也可开启 web 代理
      // _ = runShell("/usr/sbin/networksetup", args: ["-setwebproxy", interface, host, String(port)])
      // _ = runShell("/usr/sbin/networksetup", args: ["-setsecurewebproxy", interface, host, String(port)])
      return true
    } else {
      // 关闭 SOCKS 代理
      _ = runShell("/usr/sbin/networksetup", args: ["-setsocksfirewallproxystate", interface, "off"])
      // _ = runShell("/usr/sbin/networksetup", args: ["-setwebproxystate", interface, "off"])
      // _ = runShell("/usr/sbin/networksetup", args: ["-setsecurewebproxystate", interface, "off"])
      return true
    }
  }

  private func runShell(_ executableLocation: String, args: [String]) -> Int32 {
    let task = Process()
    task.launchPath = executableLocation
    task.arguments = args
    task.launch()
    task.waitUntilExit()
    return task.terminationStatus
  }

  override func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool {
    // 返回 false：关闭窗口时不退出应用，保持在托盘运行
    return false
  }

  override func applicationSupportsSecureRestorableState(_ app: NSApplication) -> Bool {
    return true
  }

  // 点击 Dock 图标时重新显示窗口
  override func applicationShouldHandleReopen(_ sender: NSApplication, hasVisibleWindows flag: Bool) -> Bool {
    if !flag {
      for window in sender.windows {
        window.makeKeyAndOrderFront(self)
      }
    }
    return true
  }
}
