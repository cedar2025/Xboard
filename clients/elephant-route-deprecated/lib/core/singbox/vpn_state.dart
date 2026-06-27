/// VPN 连接状态枚举
enum VpnStatus {
  disconnected, // 未连接
  connecting, // 连接中
  coreStarting, // sing-box 启动中
  applyingProxy, // 系统代理应用中
  connected, // 已连接
  disconnecting, // 断开中
  restoreFailed, // 退出后恢复失败
  error, // 明确错误态
}

enum VpnFailureReason {
  authenticationFailed,
  emptySubscription,
  invalidConfig,
  missingBinary,
  permissionDenied,
  routeConflict,
  proxySetupFailed,
  healthCheckFailed,
  coreStartFailed,
  restoreFailed,
  unknown,
}

enum VpnConnectionMode {
  unknown,
  proxy,
  tun,
}

/// VPN 状态数据模型
class VpnState {
  final VpnStatus status;
  final String? errorMessage;
  final VpnFailureReason? failureReason;
  final VpnConnectionMode connectionMode;
  final int upSpeed; // 上传速度 bytes/s
  final int downSpeed; // 下载速度 bytes/s
  final int totalUp; // 总上传 bytes
  final int totalDown; // 总下载 bytes
  final Map<String, int>? latencyMap; // [NEW] 节点延迟数据
  final Map<String, dynamic>? runtimeDetails;

  const VpnState({
    required this.status,
    this.errorMessage,
    this.failureReason,
    this.connectionMode = VpnConnectionMode.unknown,
    this.upSpeed = 0,
    this.downSpeed = 0,
    this.totalUp = 0,
    this.totalDown = 0,
    this.latencyMap,
    this.runtimeDetails,
  });

  /// 是否已连接
  bool get isConnected => status == VpnStatus.connected;

  /// 是否正在处理中(连接中或断开中)
  bool get isProcessing =>
      status == VpnStatus.connecting ||
      status == VpnStatus.coreStarting ||
      status == VpnStatus.applyingProxy ||
      status == VpnStatus.disconnecting;

  VpnState copyWith({
    VpnStatus? status,
    String? errorMessage,
    bool resetErrorMessage = false,
    VpnFailureReason? failureReason,
    bool resetFailureReason = false,
    VpnConnectionMode? connectionMode,
    int? upSpeed,
    int? downSpeed,
    int? totalUp,
    int? totalDown,
    Map<String, int>? latencyMap,
    Map<String, dynamic>? runtimeDetails,
  }) {
    return VpnState(
      status: status ?? this.status,
      errorMessage:
          resetErrorMessage ? null : (errorMessage ?? this.errorMessage),
      failureReason:
          resetFailureReason ? null : (failureReason ?? this.failureReason),
      connectionMode: connectionMode ?? this.connectionMode,
      upSpeed: upSpeed ?? this.upSpeed,
      downSpeed: downSpeed ?? this.downSpeed,
      totalUp: totalUp ?? this.totalUp,
      totalDown: totalDown ?? this.totalDown,
      latencyMap: latencyMap ?? this.latencyMap,
      runtimeDetails: runtimeDetails ?? this.runtimeDetails,
    );
  }
}
