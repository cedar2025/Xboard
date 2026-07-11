import Cocoa
import CryptoKit
import FlutterMacOS
import Security
import ServiceManagement

@objc(ElephantTunHelperProtocol)
protocol ElephantTunHelperProtocol {
  func getStatus(withReply reply: @escaping (NSDictionary) -> Void)
  func startTun(configPath: String, withReply reply: @escaping (NSDictionary) -> Void)
  func stopTun(withReply reply: @escaping (NSDictionary) -> Void)
  func exportDiagnostics(withReply reply: @escaping (NSString?) -> Void)
}

@main
class AppDelegate: FlutterAppDelegate {
  private enum HelperInstallerAction: String {
    case install
    case uninstall
  }

  private let runtimeChannelName = "com.elephant.network/runtime"
  private let proxyChannelName = "com.elephant.network/proxy"
  private let secureServiceName = "com.elphantroute.elephantNetwork.secure"
  private let tunHelperLabel = "com.elphantroute.elephantNetwork.tunhelper"
  private let tunHelperPlistName = "com.elphantroute.elephantNetwork.tunhelper.plist"
  private let installedTunHelperPath = "/Library/PrivilegedHelperTools/ElephantTunHelper"
  private let installedTunHelperPlistPath = "/Library/LaunchDaemons/com.elphantroute.elephantNetwork.tunhelper.plist"

  private var coreProcess: Process?
  private let runtimeStopLock = NSLock()
  private var runtimeState: [String: Any] = [
    "status": "disconnected",
    "mode": "unknown",
    "lastError": ""
  ]

  private lazy var supportDirectoryURL: URL = {
    let bundleId = Bundle.main.bundleIdentifier ?? "com.elphantroute.elephantNetwork"
    let root = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask).first!
    return root.appendingPathComponent(bundleId, isDirectory: true)
  }()

  private lazy var logsDirectoryURL: URL = supportDirectoryURL.appendingPathComponent("logs", isDirectory: true)
  private lazy var runtimeStateURL: URL = supportDirectoryURL.appendingPathComponent("runtime-state.json")
  private lazy var proxyBackupURL: URL = supportDirectoryURL.appendingPathComponent("proxy-backup.json")
  private lazy var nativeLogURL: URL = logsDirectoryURL.appendingPathComponent("native.log")
  private lazy var coreProcessPattern: String = supportDirectoryURL
    .appendingPathComponent("sing-box", isDirectory: true)
    .path

  override func applicationDidFinishLaunching(_ notification: Notification) {
    ensureSupportDirectories()
    recoverRuntimeOnLaunch()

    if let controller = mainFlutterWindow?.contentViewController as? FlutterViewController {
      let runtimeChannel = FlutterMethodChannel(
        name: runtimeChannelName,
        binaryMessenger: controller.engine.binaryMessenger
      )
      runtimeChannel.setMethodCallHandler { [weak self] call, result in
        self?.handleRuntimeCall(call, result: result)
      }

      let proxyChannel = FlutterMethodChannel(
        name: proxyChannelName,
        binaryMessenger: controller.engine.binaryMessenger
      )
      proxyChannel.setMethodCallHandler { [weak self] call, result in
        self?.handleLegacyProxyCall(call, result: result)
      }
    }

    super.applicationDidFinishLaunching(notification)
  }

  override func applicationWillTerminate(_ notification: Notification) {
    _ = stopCoreInternal(
      restoreProxy: true,
      updateRuntime: false,
      reason: "app_terminate"
    )
  }

  override func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool {
    false
  }

  override func applicationSupportsSecureRestorableState(_ app: NSApplication) -> Bool {
    true
  }

  override func applicationShouldHandleReopen(_ sender: NSApplication, hasVisibleWindows flag: Bool) -> Bool {
    if !flag {
      for window in sender.windows {
        window.makeKeyAndOrderFront(self)
      }
    }
    return true
  }

  private func handleRuntimeCall(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
    switch call.method {
    case "listNetworkServices":
      result(listNetworkServices())
    case "startProxyMode":
      guard
        let args = call.arguments as? [String: Any],
        let configPath = args["configPath"] as? String,
        let binaryPath = args["binaryPath"] as? String,
        let proxyPort = args["proxyPort"] as? Int
      else {
        result(FlutterError(code: "INVALID_ARGS", message: "configPath/binaryPath/proxyPort are required", details: nil))
        return
      }
      performAsync(result) {
        self.startProxyMode(configPath: configPath, binaryPath: binaryPath, proxyPort: proxyPort)
      }
    case "startTunMode":
      guard
        let args = call.arguments as? [String: Any],
        let configPath = args["configPath"] as? String,
        let binaryPath = args["binaryPath"] as? String
      else {
        result(FlutterError(code: "INVALID_ARGS", message: "configPath/binaryPath are required", details: nil))
        return
      }
      performAsync(result) {
        self.startTunMode(configPath: configPath, binaryPath: binaryPath)
      }
    case "ensureTunHelper":
      performAsync(result) {
        self.ensureTunHelper()
      }
    case "getTunHelperStatus":
      performAsync(result) {
        self.getTunHelperStatus()
      }
    case "getSetupStatus":
      performAsync(result) {
        self.getSetupStatus()
      }
    case "refreshTunHelper":
      performAsync(result) {
        self.refreshTunHelper()
      }
    case "uninstallTunHelper":
      performAsync(result) {
        self.uninstallTunHelper()
      }
    case "openSystemSettingsLoginItems":
      SMAppService.openSystemSettingsLoginItems()
      result(["ok": true])
    case "getLaunchAtLoginStatus":
      result(self.launchAtLoginStatus())
    case "setLaunchAtLoginEnabled":
      guard let args = call.arguments as? [String: Any], let enabled = args["enabled"] as? Bool else {
        result(FlutterError(code: "INVALID_ARGS", message: "enabled is required", details: nil))
        return
      }
      performAsync(result) {
        self.setLaunchAtLoginEnabled(enabled)
      }
    case "stopCore":
      let args = call.arguments as? [String: Any]
      let reason = args?["reason"] as? String ?? "unspecified"
      performAsync(result) {
        self.stopCoreInternal(
          restoreProxy: true,
          updateRuntime: true,
          reason: reason
        )
      }
    case "applySystemProxy":
      guard let args = call.arguments as? [String: Any], let proxyPort = args["proxyPort"] as? Int else {
        result(FlutterError(code: "INVALID_ARGS", message: "proxyPort is required", details: nil))
        return
      }
      performAsync(result) {
        ["restored": self.applySystemProxyInternal(proxyPort: proxyPort)]
      }
    case "restoreSystemProxy":
      performAsync(result) {
        ["restored": self.restoreSystemProxyInternal()]
      }
    case "getRuntimeStatus":
      performAsync(result) {
        self.getRuntimeStatus()
      }
    case "exportDiagnostics":
      performAsync(result) {
        self.exportDiagnostics()
      }
    case "writeSecureValue":
      guard
        let args = call.arguments as? [String: Any],
        let key = args["key"] as? String,
        let value = args["value"] as? String
      else {
        result(FlutterError(code: "INVALID_ARGS", message: "key/value are required", details: nil))
        return
      }
      result(writeSecureValue(key: key, value: value))
    case "readSecureValue":
      guard let args = call.arguments as? [String: Any], let key = args["key"] as? String else {
        result(FlutterError(code: "INVALID_ARGS", message: "key is required", details: nil))
        return
      }
      result(readSecureValue(key: key))
    case "deleteSecureValue":
      guard let args = call.arguments as? [String: Any], let key = args["key"] as? String else {
        result(FlutterError(code: "INVALID_ARGS", message: "key is required", details: nil))
        return
      }
      result(deleteSecureValue(key: key))
    case "deleteAllSecureValues":
      result(deleteAllSecureValues())
    default:
      result(FlutterMethodNotImplemented)
    }
  }

  private func performAsync(_ result: @escaping FlutterResult, work: @escaping () -> Any?) {
    DispatchQueue.global(qos: .userInitiated).async {
      let value = work()
      DispatchQueue.main.async {
        result(value)
      }
    }
  }

  private func handleLegacyProxyCall(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
    switch call.method {
    case "enableProxy":
      guard let args = call.arguments as? [String: Any], let port = args["port"] as? Int else {
        result(false)
        return
      }
      result(applySystemProxyInternal(proxyPort: port))
    case "disableProxy":
      result(restoreSystemProxyInternal())
    default:
      result(FlutterMethodNotImplemented)
    }
  }

  // MARK: - Runtime

  private func startProxyMode(configPath: String, binaryPath: String, proxyPort: Int) -> [String: Any] {
    log("Starting proxy mode with binary=\(binaryPath)")
    _ = stopCoreInternal(
      restoreProxy: true,
      updateRuntime: false,
      reason: "start_proxy_cleanup"
    )

    guard launchCore(binaryPath: binaryPath, configPath: configPath) else {
      let error = "Failed to launch sing-box core"
      updateRuntimeState(status: "error", mode: "proxy", lastError: error, proxyPort: proxyPort, configPath: configPath, binaryPath: binaryPath)
      return ["ok": false, "error": error]
    }

    guard applySystemProxyInternal(proxyPort: proxyPort) else {
      _ = stopCoreInternal(
        restoreProxy: true,
        updateRuntime: false,
        reason: "start_proxy_failure"
      )
      let error = "Failed to apply system proxy"
      updateRuntimeState(status: "error", mode: "proxy", lastError: error, proxyPort: proxyPort, configPath: configPath, binaryPath: binaryPath)
      return ["ok": false, "error": error]
    }

    updateRuntimeState(status: "connected", mode: "proxy", lastError: "", proxyPort: proxyPort, configPath: configPath, binaryPath: binaryPath)
    return ["ok": true, "mode": "proxy", "proxyPort": proxyPort]
  }

  private func startTunMode(configPath: String, binaryPath: String) -> [String: Any] {
    log("Starting TUN mode with binary=\(binaryPath)")
    _ = stopCoreInternal(
      restoreProxy: true,
      updateRuntime: false,
      reason: "start_tun_cleanup"
    )

    if let conflict = activeTunnelConflictDescription() {
      updateRuntimeState(status: "error", mode: "tun", lastError: conflict, configPath: configPath, binaryPath: binaryPath)
      return ["ok": false, "error": conflict, "code": "TUN_CONFLICT"]
    }

    let helperStatus = getTunHelperStatus()
    if helperStatus["status"] as? String != "enabled" {
      let error = helperStatus["message"] as? String ?? "后台网络组件未启用"
      updateRuntimeState(status: "error", mode: "tun", lastError: error, configPath: configPath, binaryPath: binaryPath)
      return ["ok": false, "error": error, "code": helperStatus["code"] as? String ?? "HELPER_NOT_ENABLED"]
    }

    let startResult = callTunHelper { helper, reply in
      helper.startTun(configPath: configPath, withReply: reply)
    }

    guard startResult["ok"] as? Bool == true else {
      let error = (startResult["error"] as? String) ?? "Failed to launch TUN core"
      updateRuntimeState(status: "error", mode: "tun", lastError: error, configPath: configPath, binaryPath: binaryPath)
      return [
        "ok": false,
        "error": error,
        "code": (startResult["code"] as? String) ?? "CORE_START_FAILED"
      ]
    }

    updateRuntimeState(status: "connected", mode: "tun", lastError: "", configPath: configPath, binaryPath: binaryPath)
    return ["ok": true, "mode": "tun", "helper": startResult]
  }

  private func stopCoreInternal(
    restoreProxy: Bool,
    updateRuntime: Bool,
    reason: String
  ) -> [String: Any] {
    runtimeStopLock.lock()
    defer { runtimeStopLock.unlock() }

    log("Stopping macOS runtime reason=\(reason)")
    if let process = coreProcess, process.isRunning {
      process.terminate()
      waitForCoreExit(timeout: 1.5)
    }
    coreProcess = nil

    _ = runCommand("/usr/bin/pkill", args: ["-f", coreProcessPattern])

    var helperStopResult: [String: Any]?
    if FileManager.default.fileExists(atPath: installedTunHelperPath),
       FileManager.default.fileExists(atPath: installedTunHelperPlistPath) {
      helperStopResult = callTunHelperIfAvailable(timeout: 2) { helper, reply in
        helper.stopTun(withReply: reply)
      }
    }

    waitForCoreExit(timeout: 2.0)

    let restored = restoreProxy ? restoreSystemProxyInternal() : true
    if updateRuntime {
      updateRuntimeState(
        status: restored ? "disconnected" : "restore_failed",
        mode: "unknown",
        lastError: restored ? "" : "System proxy restore failed"
      )
    }

    return [
      "stopped": true,
      "reason": reason,
      "proxyRestored": restored,
      "helperStop": helperStopResult ?? [:]
    ]
  }

  private func getRuntimeStatus() -> [String: Any] {
    var status = runtimeState
    status["coreRunning"] = isCoreRunning()
    status["activeNetworkServices"] = activeNetworkServices().map { $0["name"] as? String ?? "" }
    status["hasProxyBackup"] = FileManager.default.fileExists(atPath: proxyBackupURL.path)
    status["tunHelper"] = getTunHelperStatus()
    return status
  }

  private func recoverRuntimeOnLaunch() {
    if let persisted = readJSON(from: runtimeStateURL) {
      runtimeState.merge(persisted) { _, new in new }
    }

    let hadBackup = FileManager.default.fileExists(atPath: proxyBackupURL.path)
    if hadBackup || isCoreRunning() {
      log("Recovering previous runtime state")
      _ = stopCoreInternal(
        restoreProxy: true,
        updateRuntime: false,
        reason: "startup_recovery"
      )
      updateRuntimeState(status: "disconnected", mode: "unknown", lastError: "Recovered previous unfinished session")
    } else {
      persistRuntimeState()
    }
  }

  // MARK: - Proxy management

  private func listNetworkServices() -> [[String: Any]] {
    let services = allNetworkServices()
    return services.map { service in
      [
        "name": service,
        "active": isServiceActive(service)
      ]
    }
  }

  private func activeNetworkServices() -> [[String: Any]] {
    let services = allNetworkServices().map { service in
      [
        "name": service,
        "active": isServiceActive(service)
      ] as [String: Any]
    }
    let active = services.filter { ($0["active"] as? Bool) == true }
    return active.isEmpty ? services : active
  }

  private func applySystemProxyInternal(proxyPort: Int) -> Bool {
    let services = activeNetworkServices()
    guard !services.isEmpty else {
      log("No active network services found for proxy apply")
      return false
    }

    var backup = [String: Any]()
    for service in services {
      guard let name = service["name"] as? String else { continue }
      backup[name] = currentProxySettings(for: name)
    }
    writeJSON(backup, to: proxyBackupURL)

    var success = true
    for service in services {
      guard let name = service["name"] as? String else { continue }
      success = success && configureProxy(service: name, type: .web, host: "127.0.0.1", port: proxyPort, enabled: true)
      success = success && configureProxy(service: name, type: .secureWeb, host: "127.0.0.1", port: proxyPort, enabled: true)
      success = success && configureProxy(service: name, type: .socks, host: "127.0.0.1", port: proxyPort, enabled: true)
    }

    log("Applied system proxy on \(services.count) service(s), success=\(success)")
    return success
  }

  private func restoreSystemProxyInternal() -> Bool {
    let fileManager = FileManager.default
    var success = true

    if let backup = readJSON(from: proxyBackupURL) {
      for (service, rawValue) in backup {
        guard let settings = rawValue as? [String: Any] else { continue }
        success = success && restoreProxy(service: service, type: .web, config: settings["web"] as? [String: Any])
        success = success && restoreProxy(service: service, type: .secureWeb, config: settings["secureWeb"] as? [String: Any])
        success = success && restoreProxy(service: service, type: .socks, config: settings["socks"] as? [String: Any])
      }
      try? fileManager.removeItem(at: proxyBackupURL)
    } else {
      for service in activeNetworkServices() {
        guard let name = service["name"] as? String else { continue }
        success = success && disableProxy(service: name, type: .web)
        success = success && disableProxy(service: name, type: .secureWeb)
        success = success && disableProxy(service: name, type: .socks)
      }
    }

    log("Restored system proxy, success=\(success)")
    return success
  }

  private enum ProxyType {
    case web
    case secureWeb
    case socks

    var getFlag: String {
      switch self {
      case .web: return "-getwebproxy"
      case .secureWeb: return "-getsecurewebproxy"
      case .socks: return "-getsocksfirewallproxy"
      }
    }

    var setFlag: String {
      switch self {
      case .web: return "-setwebproxy"
      case .secureWeb: return "-setsecurewebproxy"
      case .socks: return "-setsocksfirewallproxy"
      }
    }

    var stateFlag: String {
      switch self {
      case .web: return "-setwebproxystate"
      case .secureWeb: return "-setsecurewebproxystate"
      case .socks: return "-setsocksfirewallproxystate"
      }
    }

    var backupKey: String {
      switch self {
      case .web: return "web"
      case .secureWeb: return "secureWeb"
      case .socks: return "socks"
      }
    }
  }

  private func currentProxySettings(for service: String) -> [String: Any] {
    [
      ProxyType.web.backupKey: proxyConfig(service: service, type: .web),
      ProxyType.secureWeb.backupKey: proxyConfig(service: service, type: .secureWeb),
      ProxyType.socks.backupKey: proxyConfig(service: service, type: .socks)
    ]
  }

  private func proxyConfig(service: String, type: ProxyType) -> [String: Any] {
    let output = runCommand("/usr/sbin/networksetup", args: [type.getFlag, service])
    return parseProxyConfig(output: output)
  }

  private func parseProxyConfig(output: String) -> [String: Any] {
    var config: [String: Any] = [:]
    for line in output.split(separator: "\n") {
      let parts = line.split(separator: ":", maxSplits: 1).map { String($0).trimmingCharacters(in: .whitespacesAndNewlines) }
      guard parts.count == 2 else { continue }
      switch parts[0] {
      case "Enabled":
        config["enabled"] = parts[1] == "Yes"
      case "Server":
        config["server"] = parts[1]
      case "Port":
        config["port"] = Int(parts[1]) ?? 0
      default:
        continue
      }
    }
    return config
  }

  private func configureProxy(service: String, type: ProxyType, host: String, port: Int, enabled: Bool) -> Bool {
    let setOutput = runCommand("/usr/sbin/networksetup", args: [type.setFlag, service, host, "\(port)"])
    let stateOutput = runCommand("/usr/sbin/networksetup", args: [type.stateFlag, service, enabled ? "on" : "off"])
    return !setOutput.contains("Error") && !stateOutput.contains("Error")
  }

  private func disableProxy(service: String, type: ProxyType) -> Bool {
    let output = runCommand("/usr/sbin/networksetup", args: [type.stateFlag, service, "off"])
    return !output.contains("Error")
  }

  private func restoreProxy(service: String, type: ProxyType, config: [String: Any]?) -> Bool {
    guard let config else {
      return disableProxy(service: service, type: type)
    }
    let enabled = config["enabled"] as? Bool ?? false
    if enabled {
      let host = config["server"] as? String ?? "127.0.0.1"
      let port = config["port"] as? Int ?? 0
      return configureProxy(service: service, type: type, host: host, port: port, enabled: true)
    }
    return disableProxy(service: service, type: type)
  }

  private func allNetworkServices() -> [String] {
    let output = runCommand("/usr/sbin/networksetup", args: ["-listallnetworkservices"])
    return output
      .split(separator: "\n")
      .map { String($0).trimmingCharacters(in: .whitespacesAndNewlines) }
      .filter { !$0.isEmpty && !$0.hasPrefix("An asterisk") && !$0.hasPrefix("*") }
  }

  private func isServiceActive(_ service: String) -> Bool {
    let output = runCommand("/usr/sbin/networksetup", args: ["-getinfo", service])
    if output.contains("IP address: none") && output.contains("IPv6 IP address: none") {
      return false
    }
    return !output.isEmpty
  }

  // MARK: - Process management

  private func launchCore(binaryPath: String, configPath: String) -> Bool {
    let process = Process()
    process.executableURL = URL(fileURLWithPath: binaryPath)
    process.arguments = ["run", "-c", configPath]
    process.currentDirectoryURL = URL(fileURLWithPath: URL(fileURLWithPath: configPath).deletingLastPathComponent().path)

    let pipe = Pipe()
    process.standardOutput = pipe
    process.standardError = pipe
    pipe.fileHandleForReading.readabilityHandler = { [weak self] handle in
      let data = handle.availableData
      guard !data.isEmpty, let text = String(data: data, encoding: .utf8) else { return }
      self?.log("[sing-box] \(text.trimmingCharacters(in: .whitespacesAndNewlines))")
    }

    do {
      try process.run()
      coreProcess = process
      return true
    } catch {
      log("Failed to run process: \(error)")
      return false
    }
  }

  private func isCoreRunning() -> Bool {
    let output = runCommand("/usr/bin/pgrep", args: ["-f", coreProcessPattern])
    return !output.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
  }

  private func waitForCoreExit(timeout: TimeInterval) {
    let deadline = Date().addingTimeInterval(timeout)
    while Date() < deadline {
      if !isCoreRunning() {
        return
      }
      Thread.sleep(forTimeInterval: 0.2)
    }
  }

  private func activeTunnelConflictDescription() -> String? {
    let output = runCommand("/usr/sbin/netstat", args: ["-rn", "-f", "inet"])
    let routeLines = output
      .split(separator: "\n")
      .map { String($0).trimmingCharacters(in: .whitespacesAndNewlines) }
      .filter { $0.contains("utun") }

    guard !routeLines.isEmpty else {
      return nil
    }

    let defaultTunnelRoutes = routeLines.filter {
      $0.hasPrefix("default") || $0.hasPrefix("0/1") || $0.hasPrefix("128.0/1") || $0.contains("198.18.0/15")
    }
    guard !defaultTunnelRoutes.isEmpty else {
      return nil
    }

    let interfaceName = defaultTunnelRoutes
      .flatMap { $0.split(whereSeparator: \.isWhitespace) }
      .map(String.init)
      .first { $0.hasPrefix("utun") } ?? "utun"

    return "检测到系统中已有其他 TUN/VPN 会话（\(interfaceName)）。请先断开其他 VPN 或网络过滤器后再启动 TUN 模式。"
  }

  // MARK: - Diagnostics and state

  private func ensureSupportDirectories() {
    try? FileManager.default.createDirectory(at: supportDirectoryURL, withIntermediateDirectories: true)
    try? FileManager.default.createDirectory(at: logsDirectoryURL, withIntermediateDirectories: true)
    if !FileManager.default.fileExists(atPath: nativeLogURL.path) {
      FileManager.default.createFile(atPath: nativeLogURL.path, contents: nil)
    }
    persistRuntimeState()
  }

  private func exportDiagnostics() -> String? {
    let fileManager = FileManager.default
    let exportRoot = URL(fileURLWithPath: NSTemporaryDirectory(), isDirectory: true)
      .appendingPathComponent("elephant-diagnostics-\(Int(Date().timeIntervalSince1970))", isDirectory: true)
    do {
      try fileManager.createDirectory(at: exportRoot, withIntermediateDirectories: true)
      writeJSON(getRuntimeStatus(), to: exportRoot.appendingPathComponent("runtime-status.json"))
      if fileManager.fileExists(atPath: runtimeStateURL.path) {
        try? fileManager.copyItem(at: runtimeStateURL, to: exportRoot.appendingPathComponent("runtime-state.json"))
      }
      if fileManager.fileExists(atPath: proxyBackupURL.path) {
        try? fileManager.copyItem(at: proxyBackupURL, to: exportRoot.appendingPathComponent("proxy-backup.json"))
      }
      if fileManager.fileExists(atPath: nativeLogURL.path) {
        try? fileManager.copyItem(at: nativeLogURL, to: exportRoot.appendingPathComponent("native.log"))
      }
      let dartLogURL = logsDirectoryURL.appendingPathComponent("dart.log")
      if fileManager.fileExists(atPath: dartLogURL.path) {
        try? fileManager.copyItem(at: dartLogURL, to: exportRoot.appendingPathComponent("dart.log"))
      }
      if let helperExport = callTunHelperIfAvailableString({ helper, reply in
        helper.exportDiagnostics(withReply: reply)
      }) {
        let helperURL = URL(fileURLWithPath: helperExport)
        if fileManager.fileExists(atPath: helperURL.path) {
          try? fileManager.copyItem(at: helperURL, to: exportRoot.appendingPathComponent("tun-helper", isDirectory: true))
        }
      }
      return exportRoot.path
    } catch {
      log("Failed to export diagnostics: \(error)")
      return nil
    }
  }

  // MARK: - TUN helper management

  private func tunHelperService() -> SMAppService {
    SMAppService.daemon(plistName: tunHelperPlistName)
  }

  private func getSetupStatus() -> [String: Any] {
    let bundlePath = Bundle.main.bundlePath
    return [
      "ok": true,
      "bundlePath": bundlePath,
      "installedInApplications": isInstalledInApplications(),
      "helper": getTunHelperStatus(),
      "launchAtLogin": launchAtLoginStatus()
    ]
  }

  private func ensureTunHelper() -> [String: Any] {
    guard isInstalledInApplications() else {
      return appNotInstalledHelperStatus()
    }
    let currentStatus = getTunHelperStatus()
    if currentStatus["status"] as? String == "enabled" {
      return currentStatus
    }

    return installTunHelperWithAdministratorPrivileges()
  }

  private func getTunHelperStatus() -> [String: Any] {
    guard isInstalledInApplications() else {
      return appNotInstalledHelperStatus()
    }

    guard bundledTunHelperBinaryExists(), bundledAdminInstallerExists() else {
      return helperState(
        status: "notFound",
        code: "HELPER_NOT_FOUND",
        message: "应用包缺少后台网络组件，请重新安装客户端"
      )
    }

    let fileManager = FileManager.default
    guard fileManager.fileExists(atPath: installedTunHelperPath),
          fileManager.fileExists(atPath: installedTunHelperPlistPath)
    else {
      return helperState(
        status: "notRegistered",
        code: "HELPER_INSTALL_REQUIRED",
        message: "需要管理员授权安装后台网络组件"
      )
    }

    let bundledHash = sha256(of: bundledTunHelperURL())
    let installedHash = sha256(of: URL(fileURLWithPath: installedTunHelperPath))
    guard let bundledHash, let installedHash else {
      return helperState(
        status: "connectionFailed",
        code: "HELPER_INSTALL_FAILED",
        message: "无法校验后台网络组件完整性"
      )
    }
    if bundledHash != installedHash {
      return helperState(
        status: "refreshRequired",
        code: "HELPER_REFRESH_REQUIRED",
        message: "后台网络组件需要管理员授权升级",
        extra: ["bundledSha256": bundledHash, "installedSha256": installedHash]
      )
    }

    let launchd = launchdTunHelperStatus()
    guard launchd.contains("state =") || launchd.contains("active count =") else {
      return helperState(
        status: "notRegistered",
        code: "HELPER_INSTALL_REQUIRED",
        message: "后台网络组件尚未加载",
        extra: ["launchd": launchd]
      )
    }

    let ping = callTunHelperIfAvailable(timeout: 2) { helper, reply in
      helper.getStatus(withReply: reply)
    }
    guard ping?["ok"] as? Bool == true else {
      return helperState(
        status: "connectionFailed",
        code: "HELPER_HEALTH_CHECK_FAILED",
        message: (ping?["error"] as? String) ?? "后台网络组件健康检查失败",
        extra: ["launchd": launchd]
      )
    }

    return helperState(
      status: "enabled",
      code: "OK",
      message: "后台网络组件已启用",
      extra: ["helperSha256": installedHash, "launchd": launchd]
    )
  }

  private func refreshTunHelper() -> [String: Any] {
    guard isInstalledInApplications() else {
      return appNotInstalledHelperStatus()
    }
    return installTunHelperWithAdministratorPrivileges()
  }

  private func uninstallTunHelper() -> [String: Any] {
    guard isInstalledInApplications() else {
      return appNotInstalledHelperStatus()
    }
    _ = stopCoreInternal(
      restoreProxy: true,
      updateRuntime: true,
      reason: "helper_uninstall"
    )
    let uninstallResult = runAdministratorInstaller(.uninstall)
    guard uninstallResult["ok"] as? Bool == true else {
      return uninstallResult
    }
    return helperState(
      status: "notRegistered",
      code: "HELPER_INSTALL_REQUIRED",
      message: "后台网络组件已卸载"
    )
  }

  private func installTunHelperWithAdministratorPrivileges() -> [String: Any] {
    if let unregisterError = unregisterManagedTunHelperIfNeeded() {
      return helperState(
        status: "connectionFailed",
        code: "HELPER_INSTALL_FAILED",
        message: "无法清理旧后台组件注册：\(unregisterError)"
      )
    }

    let installResult = runAdministratorInstaller(.install)
    guard installResult["ok"] as? Bool == true else {
      return installResult
    }

    for _ in 0..<12 {
      let ping = callTunHelperIfAvailable(timeout: 1) { helper, reply in
        helper.getStatus(withReply: reply)
      }
      if ping?["ok"] as? Bool == true {
        return helperState(
          status: "enabled",
          code: "OK",
          message: "后台网络组件安装成功"
        )
      }
      Thread.sleep(forTimeInterval: 0.25)
    }

    return helperState(
      status: "connectionFailed",
      code: "HELPER_HEALTH_CHECK_FAILED",
      message: "后台网络组件已安装，但健康检查失败",
      extra: ["launchd": launchdTunHelperStatus()]
    )
  }

  private func runAdministratorInstaller(_ action: HelperInstallerAction) -> [String: Any] {
    let installerURL = bundledAdminInstallerURL()
    guard FileManager.default.isExecutableFile(atPath: installerURL.path) else {
      return helperState(
        status: "notFound",
        code: "HELPER_NOT_FOUND",
        message: "应用包缺少管理员安装程序"
      )
    }

    let appleScript = """
    on run argv
      set installerPath to item 1 of argv
      set helperAction to item 2 of argv
      if helperAction is not "install" and helperAction is not "uninstall" then error "Invalid helper action"
      set commandText to quoted form of installerPath & " " & quoted form of helperAction
      do shell script commandText with administrator privileges
    end run
    """

    let process = Process()
    process.executableURL = URL(fileURLWithPath: "/usr/bin/osascript")
    process.arguments = ["-e", appleScript, "--", installerURL.path, action.rawValue]
    let output = Pipe()
    process.standardOutput = output
    process.standardError = output

    do {
      try process.run()
      let data = output.fileHandleForReading.readDataToEndOfFile()
      process.waitUntilExit()
      let message = String(data: data, encoding: .utf8) ?? ""
      if process.terminationStatus == 0 {
        return ["ok": true, "action": action.rawValue]
      }
      if message.contains("-128") ||
          message.localizedCaseInsensitiveContains("user canceled") ||
          message.contains("用户已取消") {
        return helperState(
          status: "notRegistered",
          code: "AUTHORIZATION_CANCELLED",
          message: "已取消管理员授权"
        )
      }
      return helperState(
        status: "connectionFailed",
        code: "HELPER_INSTALL_FAILED",
        message: message.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
          ? "后台网络组件安装失败"
          : message.trimmingCharacters(in: .whitespacesAndNewlines)
      )
    } catch {
      return helperState(
        status: "connectionFailed",
        code: "HELPER_INSTALL_FAILED",
        message: "无法启动管理员安装程序：\(error.localizedDescription)"
      )
    }
  }

  private func unregisterManagedTunHelperIfNeeded() -> String? {
    let service = tunHelperService()
    if service.status == .notRegistered || service.status == .notFound {
      return nil
    }
    let semaphore = DispatchSemaphore(value: 0)
    var unregisterError: Error?
    service.unregister { error in
      unregisterError = error
      semaphore.signal()
    }
    if semaphore.wait(timeout: .now() + 8) == .timedOut {
      return "注销旧后台组件超时"
    }
    return unregisterError?.localizedDescription
  }

  private func helperState(
    status: String,
    code: String,
    message: String,
    extra: [String: Any] = [:]
  ) -> [String: Any] {
    var value: [String: Any] = [
      "ok": false,
      "status": status,
      "code": code,
      "message": message,
      "bundlePath": Bundle.main.bundlePath,
      "bundledPlistExists": bundledTunHelperPlistExists(),
      "bundledHelperExists": bundledTunHelperBinaryExists(),
      "installedHelperExists": FileManager.default.fileExists(atPath: installedTunHelperPath),
      "installedPlistExists": FileManager.default.fileExists(atPath: installedTunHelperPlistPath)
    ]
    if status == "enabled" {
      value["ok"] = true
    }
    for (key, item) in extra {
      value[key] = item
    }
    return value
  }

  private func appNotInstalledHelperStatus() -> [String: Any] {
    helperState(
      status: "notRegistered",
      code: "APP_NOT_IN_APPLICATIONS",
      message: "请先将应用拖入“应用程序”目录并重新打开"
    )
  }

  private func isInstalledInApplications() -> Bool {
    let bundlePath = URL(fileURLWithPath: Bundle.main.bundlePath)
      .standardizedFileURL.path
    return bundlePath == "/Applications/大象网络.app"
  }

  private func bundledTunHelperURL() -> URL {
    Bundle.main.bundleURL.appendingPathComponent("Contents/MacOS/ElephantTunHelper")
  }

  private func bundledAdminInstallerURL() -> URL {
    Bundle.main.bundleURL.appendingPathComponent(
      "Contents/Resources/ElephantTunHelper/install-helper.sh"
    )
  }

  private func bundledAdminInstallerExists() -> Bool {
    FileManager.default.isExecutableFile(atPath: bundledAdminInstallerURL().path)
  }

  private func sha256(of url: URL) -> String? {
    guard let data = try? Data(contentsOf: url) else { return nil }
    return SHA256.hash(data: data).map { String(format: "%02x", $0) }.joined()
  }

  private func launchdTunHelperStatus() -> String {
    runCommand("/bin/launchctl", args: ["print", "system/\(tunHelperLabel)"])
  }

  private func launchAtLoginStatus() -> [String: Any] {
    let status = SMAppService.mainApp.status
    return [
      "ok": status == .enabled,
      "enabled": status == .enabled,
      "status": helperStatusName(status),
      "code": helperStatusCode(status),
      "message": helperStatusMessage(status)
    ]
  }

  private func setLaunchAtLoginEnabled(_ enabled: Bool) -> [String: Any] {
    let service = SMAppService.mainApp
    do {
      if enabled {
        if service.status != .enabled {
          try service.register()
        }
      } else if service.status != .notRegistered && service.status != .notFound {
        let semaphore = DispatchSemaphore(value: 0)
        var unregisterError: NSError?
        service.unregister { error in
          unregisterError = error as NSError?
          semaphore.signal()
        }
        if semaphore.wait(timeout: .now() + 5) == .timedOut {
          return ["ok": false, "enabled": true, "status": "connectionFailed", "message": "关闭登录启动超时"]
        }
        if let unregisterError {
          return ["ok": false, "enabled": true, "status": "connectionFailed", "message": unregisterError.localizedDescription]
        }
      }
      return launchAtLoginStatus()
    } catch {
      return [
        "ok": false,
        "enabled": service.status == .enabled,
        "status": helperStatusName(service.status),
        "code": helperStatusCode(service.status),
        "message": error.localizedDescription
      ]
    }
  }

  private func bundledTunHelperPlistExists() -> Bool {
    let path = Bundle.main.bundlePath + "/Contents/Library/LaunchDaemons/" + tunHelperPlistName
    return FileManager.default.fileExists(atPath: path)
  }

  private func bundledTunHelperBinaryExists() -> Bool {
    let path = Bundle.main.bundlePath + "/Contents/MacOS/ElephantTunHelper"
    return FileManager.default.fileExists(atPath: path)
  }

  private func helperStatusName(_ status: SMAppService.Status) -> String {
    switch status {
    case .notRegistered:
      return "notRegistered"
    case .enabled:
      return "enabled"
    case .requiresApproval:
      return "requiresApproval"
    case .notFound:
      return "notFound"
    @unknown default:
      return "unknown"
    }
  }

  private func helperStatusCode(_ status: SMAppService.Status) -> String {
    switch status {
    case .notRegistered:
      return "HELPER_NOT_REGISTERED"
    case .enabled:
      return "OK"
    case .requiresApproval:
      return "HELPER_REQUIRES_APPROVAL"
    case .notFound:
      return "HELPER_NOT_FOUND"
    @unknown default:
      return "HELPER_UNKNOWN"
    }
  }

  private func helperStatusMessage(_ status: SMAppService.Status) -> String {
    switch status {
    case .notRegistered:
      return "后台网络组件尚未注册"
    case .enabled:
      return "后台网络组件已启用"
    case .requiresApproval:
      return "后台网络组件需要在系统设置中允许后才能接管全局流量"
    case .notFound:
      return "未在应用包中找到后台网络组件，请重新安装客户端"
    @unknown default:
      return "后台网络组件状态未知"
    }
  }

  private func callTunHelper(_ body: @escaping (ElephantTunHelperProtocol, @escaping (NSDictionary) -> Void) -> Void) -> [String: Any] {
    callTunHelperIfAvailable(timeout: 5, body) ?? [
      "ok": false,
      "code": "HELPER_CONNECTION_FAILED",
      "error": "无法连接后台网络组件"
    ]
  }

  private func callTunHelperIfAvailable(
    timeout: TimeInterval = 5,
    _ body: @escaping (ElephantTunHelperProtocol, @escaping (NSDictionary) -> Void) -> Void
  ) -> [String: Any]? {
    let connection = NSXPCConnection(machServiceName: tunHelperLabel, options: .privileged)
    connection.remoteObjectInterface = NSXPCInterface(with: ElephantTunHelperProtocol.self)
    connection.resume()
    defer { connection.invalidate() }

    let semaphore = DispatchSemaphore(value: 0)
    var response: [String: Any]?
    let proxy = connection.remoteObjectProxyWithErrorHandler { error in
      self.log("TUN helper XPC failed: \(error)")
      response = [
        "ok": false,
        "code": "HELPER_CONNECTION_FAILED",
        "error": error.localizedDescription
      ]
      semaphore.signal()
    } as? ElephantTunHelperProtocol

    guard let proxy else {
      return nil
    }

    body(proxy) { result in
      response = self.dictionary(from: result)
      semaphore.signal()
    }

    if semaphore.wait(timeout: .now() + timeout) == .timedOut {
      self.log("TUN helper XPC timed out")
      return [
        "ok": false,
        "code": "HELPER_TIMEOUT",
        "error": "后台网络组件响应超时"
      ]
    }
    return response
  }

  private func dictionary(from value: NSDictionary) -> [String: Any] {
    var result = [String: Any]()
    for (key, item) in value {
      if let key = key as? String {
        result[key] = item
      }
    }
    return result
  }

  private func callTunHelperIfAvailableString(_ body: @escaping (ElephantTunHelperProtocol, @escaping (NSString?) -> Void) -> Void) -> String? {
    let connection = NSXPCConnection(machServiceName: tunHelperLabel, options: .privileged)
    connection.remoteObjectInterface = NSXPCInterface(with: ElephantTunHelperProtocol.self)
    connection.resume()
    defer { connection.invalidate() }

    let semaphore = DispatchSemaphore(value: 0)
    var response: String?
    let proxy = connection.remoteObjectProxyWithErrorHandler { error in
      self.log("TUN helper XPC string call failed: \(error)")
      semaphore.signal()
    } as? ElephantTunHelperProtocol

    guard let proxy else {
      return nil
    }

    body(proxy) { result in
      response = result as String?
      semaphore.signal()
    }

    if semaphore.wait(timeout: .now() + 5) == .timedOut {
      self.log("TUN helper XPC string call timed out")
      return nil
    }
    return response
  }

  private func updateRuntimeState(
    status: String,
    mode: String? = nil,
    lastError: String? = nil,
    proxyPort: Int? = nil,
    configPath: String? = nil,
    binaryPath: String? = nil
  ) {
    runtimeState["status"] = status
    if let mode { runtimeState["mode"] = mode }
    if let lastError { runtimeState["lastError"] = lastError }
    if let proxyPort { runtimeState["proxyPort"] = proxyPort }
    if let configPath { runtimeState["configPath"] = configPath }
    if let binaryPath { runtimeState["binaryPath"] = binaryPath }
    runtimeState["updatedAt"] = ISO8601DateFormatter().string(from: Date())
    persistRuntimeState()
  }

  private func persistRuntimeState() {
    writeJSON(runtimeState, to: runtimeStateURL)
  }

  private func writeJSON(_ value: [String: Any], to url: URL) {
    guard JSONSerialization.isValidJSONObject(value),
          let data = try? JSONSerialization.data(withJSONObject: value, options: [.prettyPrinted])
    else {
      return
    }
    try? data.write(to: url)
  }

  private func readJSON(from url: URL) -> [String: Any]? {
    guard
      let data = try? Data(contentsOf: url),
      let object = try? JSONSerialization.jsonObject(with: data),
      let map = object as? [String: Any]
    else {
      return nil
    }
    return map
  }

  private func log(_ message: String) {
    let line = "[\(ISO8601DateFormatter().string(from: Date()))] \(message)\n"
    if let data = line.data(using: .utf8) {
      if let handle = try? FileHandle(forWritingTo: nativeLogURL) {
        handle.seekToEndOfFile()
        handle.write(data)
        handle.closeFile()
      }
    }
    NSLog("%@", message)
  }

  // MARK: - Keychain

  private func writeSecureValue(key: String, value: String) -> Bool {
    let encoded = Data(value.utf8)
    let query: [String: Any] = [
      kSecClass as String: kSecClassGenericPassword,
      kSecAttrService as String: secureServiceName,
      kSecAttrAccount as String: key
    ]
    SecItemDelete(query as CFDictionary)

    var item = query
    item[kSecValueData as String] = encoded
    let status = SecItemAdd(item as CFDictionary, nil)
    return status == errSecSuccess
  }

  private func readSecureValue(key: String) -> String? {
    let query: [String: Any] = [
      kSecClass as String: kSecClassGenericPassword,
      kSecAttrService as String: secureServiceName,
      kSecAttrAccount as String: key,
      kSecReturnData as String: true,
      kSecMatchLimit as String: kSecMatchLimitOne
    ]

    var item: CFTypeRef?
    let status = SecItemCopyMatching(query as CFDictionary, &item)
    guard status == errSecSuccess, let data = item as? Data else {
      return nil
    }
    return String(data: data, encoding: .utf8)
  }

  private func deleteSecureValue(key: String) -> Bool {
    let query: [String: Any] = [
      kSecClass as String: kSecClassGenericPassword,
      kSecAttrService as String: secureServiceName,
      kSecAttrAccount as String: key
    ]
    let status = SecItemDelete(query as CFDictionary)
    return status == errSecSuccess || status == errSecItemNotFound
  }

  private func deleteAllSecureValues() -> Bool {
    let query: [String: Any] = [
      kSecClass as String: kSecClassGenericPassword,
      kSecAttrService as String: secureServiceName
    ]
    let status = SecItemDelete(query as CFDictionary)
    return status == errSecSuccess || status == errSecItemNotFound
  }

  // MARK: - Shell helpers

  private func runCommand(_ executable: String, args: [String]) -> String {
    let process = Process()
    process.executableURL = URL(fileURLWithPath: executable)
    process.arguments = args

    let pipe = Pipe()
    process.standardOutput = pipe
    process.standardError = pipe
    do {
      try process.run()
      let data = pipe.fileHandleForReading.readDataToEndOfFile()
      process.waitUntilExit()
      return String(data: data, encoding: .utf8) ?? ""
    } catch {
      log(
        "Command failed executable=\(executable) args=\(args) error=\(error.localizedDescription)"
      )
      return ""
    }
  }

}
