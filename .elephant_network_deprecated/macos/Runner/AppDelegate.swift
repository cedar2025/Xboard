import Cocoa
import FlutterMacOS
import Security

@main
class AppDelegate: FlutterAppDelegate {
  private let runtimeChannelName = "com.elephant.network/runtime"
  private let proxyChannelName = "com.elephant.network/proxy"
  private let secureServiceName = "com.elphantroute.elephantNetwork.secure"

  private var coreProcess: Process?
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
    _ = stopCoreInternal(restoreProxy: true, updateRuntime: false)
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
    case "stopCore":
      performAsync(result) {
        self.stopCoreInternal(restoreProxy: true, updateRuntime: true)
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
    _ = stopCoreInternal(restoreProxy: true, updateRuntime: false)

    guard launchCore(binaryPath: binaryPath, configPath: configPath) else {
      let error = "Failed to launch sing-box core"
      updateRuntimeState(status: "error", mode: "proxy", lastError: error, proxyPort: proxyPort, configPath: configPath, binaryPath: binaryPath)
      return ["ok": false, "error": error]
    }

    guard applySystemProxyInternal(proxyPort: proxyPort) else {
      _ = stopCoreInternal(restoreProxy: true, updateRuntime: false)
      let error = "Failed to apply system proxy"
      updateRuntimeState(status: "error", mode: "proxy", lastError: error, proxyPort: proxyPort, configPath: configPath, binaryPath: binaryPath)
      return ["ok": false, "error": error]
    }

    updateRuntimeState(status: "connected", mode: "proxy", lastError: "", proxyPort: proxyPort, configPath: configPath, binaryPath: binaryPath)
    return ["ok": true, "mode": "proxy", "proxyPort": proxyPort]
  }

  private func startTunMode(configPath: String, binaryPath: String) -> [String: Any] {
    log("Starting TUN mode with binary=\(binaryPath)")
    _ = stopCoreInternal(restoreProxy: true, updateRuntime: false, allowPrivilegedCleanup: false)

    if let conflict = activeTunnelConflictDescription() {
      updateRuntimeState(status: "error", mode: "tun", lastError: conflict, configPath: configPath, binaryPath: binaryPath)
      return ["ok": false, "error": conflict, "code": "TUN_CONFLICT"]
    }

    guard launchTunCore(binaryPath: binaryPath, configPath: configPath) else {
      let error = "Failed to launch TUN core with admin privileges"
      updateRuntimeState(status: "error", mode: "tun", lastError: error, configPath: configPath, binaryPath: binaryPath)
      return ["ok": false, "error": error, "code": "PERMISSION_DENIED"]
    }

    guard waitForHealthCheck() else {
      _ = stopCoreInternal(restoreProxy: true, updateRuntime: false, allowPrivilegedCleanup: false)
      let error = recentTunFailureMessage() ?? "Health check failed after TUN launch"
      updateRuntimeState(status: "error", mode: "tun", lastError: error, configPath: configPath, binaryPath: binaryPath)
      return [
        "ok": false,
        "error": error,
        "code": error.contains("其他 TUN/VPN 会话") || error.contains("冲突路由") ? "TUN_ROUTE_CONFLICT" : "HEALTH_CHECK_FAILED"
      ]
    }

    updateRuntimeState(status: "connected", mode: "tun", lastError: "", configPath: configPath, binaryPath: binaryPath)
    return ["ok": true, "mode": "tun"]
  }

  private func stopCoreInternal(restoreProxy: Bool, updateRuntime: Bool, allowPrivilegedCleanup: Bool = true) -> [String: Any] {
    log("Stopping macOS runtime")
    if let process = coreProcess, process.isRunning {
      process.terminate()
      waitForCoreExit(timeout: 1.5)
    }
    coreProcess = nil

    _ = runCommand("/usr/bin/pkill", args: ["-f", coreProcessPattern])

    var privilegedCleanupAttempted = false
    var privilegedCleanupSucceeded = true
    if allowPrivilegedCleanup && hasPrivilegedCoreProcess() {
      privilegedCleanupAttempted = true
      privilegedCleanupSucceeded = runAppleScriptCommand("/usr/bin/pkill -f \(shellQuote(coreProcessPattern)) || true")
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
      "proxyRestored": restored,
      "privilegedCleanupAttempted": privilegedCleanupAttempted,
      "privilegedCleanupSucceeded": privilegedCleanupSucceeded
    ]
  }

  private func getRuntimeStatus() -> [String: Any] {
    var status = runtimeState
    status["coreRunning"] = isCoreRunning()
    status["activeNetworkServices"] = activeNetworkServices().map { $0["name"] as? String ?? "" }
    status["hasProxyBackup"] = FileManager.default.fileExists(atPath: proxyBackupURL.path)
    return status
  }

  private func recoverRuntimeOnLaunch() {
    if let persisted = readJSON(from: runtimeStateURL) {
      runtimeState.merge(persisted) { _, new in new }
    }

    let hadBackup = FileManager.default.fileExists(atPath: proxyBackupURL.path)
    if hadBackup || isCoreRunning() {
      log("Recovering previous runtime state")
      _ = stopCoreInternal(restoreProxy: true, updateRuntime: false, allowPrivilegedCleanup: false)
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

  private func launchTunCore(binaryPath: String, configPath: String) -> Bool {
    let command = [
      "/usr/bin/pkill -f \(shellQuote(coreProcessPattern)) || true",
      "while /usr/bin/pgrep -f \(shellQuote(coreProcessPattern)) >/dev/null; do /bin/sleep 0.2; done",
      "/sbin/route -n delete -net 0.0.0.0/1 >/dev/null 2>&1 || true",
      "/sbin/route -n delete -net 128.0.0.0/1 >/dev/null 2>&1 || true",
      "/sbin/route -n delete -net 1.0.0.0/8 >/dev/null 2>&1 || true",
      "/sbin/route -n delete -net 198.18.0.0/15 >/dev/null 2>&1 || true",
      "\(shellQuote(binaryPath)) run -c \(shellQuote(configPath)) >> \(shellQuote(nativeLogURL.path)) 2>&1 &"
    ].joined(separator: "; ")
    return runAppleScriptCommand(command)
  }

  private func waitForHealthCheck() -> Bool {
    guard let url = URL(string: "http://127.0.0.1:9090/proxies") else { return false }
    for _ in 0..<12 {
      if let data = try? Data(contentsOf: url), !data.isEmpty {
        return true
      }
      Thread.sleep(forTimeInterval: 0.5)
    }
    return false
  }

  private func isCoreRunning() -> Bool {
    let output = runCommand("/usr/bin/pgrep", args: ["-f", coreProcessPattern])
    return !output.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
  }

  private func hasPrivilegedCoreProcess() -> Bool {
    let output = runCommand("/bin/ps", args: ["-ax", "-o", "user=,command="])
    let currentUser = NSUserName()
    for rawLine in output.split(separator: "\n") {
      let line = rawLine.trimmingCharacters(in: .whitespacesAndNewlines)
      guard line.contains(coreProcessPattern) else { continue }
      let parts = line.split(maxSplits: 1, whereSeparator: { $0 == " " || $0 == "\t" })
      guard let user = parts.first else { continue }
      if String(user) != currentUser {
        return true
      }
    }
    return false
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

  private func recentTunFailureMessage() -> String? {
    guard let logContents = try? String(contentsOf: nativeLogURL, encoding: .utf8) else {
      return nil
    }
    let recentLines = logContents.split(separator: "\n").suffix(80).map(String.init)
    if recentLines.contains(where: { $0.contains("configure tun interface: add route:") && $0.contains("file exists") }) {
      return "检测到系统中存在冲突路由。请先断开其他 TUN/VPN 会话后再启动 TUN 模式。"
    }
    return nil
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
      return exportRoot.path
    } catch {
      log("Failed to export diagnostics: \(error)")
      return nil
    }
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
    process.launchPath = executable
    process.arguments = args

    let pipe = Pipe()
    process.standardOutput = pipe
    process.standardError = pipe
    process.launch()
    process.waitUntilExit()
    let data = pipe.fileHandleForReading.readDataToEndOfFile()
    return String(data: data, encoding: .utf8) ?? ""
  }

  private func runAppleScriptCommand(_ command: String) -> Bool {
    let source = "do shell script \(appleScriptQuote(command)) with administrator privileges"
    var error: NSDictionary?
    let script = NSAppleScript(source: source)
    _ = script?.executeAndReturnError(&error)
    if let error {
      log("AppleScript command failed: \(error)")
      return false
    }
    return true
  }

  private func shellQuote(_ value: String) -> String {
    "'\(value.replacingOccurrences(of: "'", with: "'\\''"))'"
  }

  private func appleScriptQuote(_ value: String) -> String {
    let escaped = value
      .replacingOccurrences(of: "\\", with: "\\\\")
      .replacingOccurrences(of: "\"", with: "\\\"")
    return "\"\(escaped)\""
  }
}
