/// VPN 连接状态枚举
enum VpnStatus {
  disconnected,  // 未连接
  connecting,    // 连接中
  connected,     // 已连接
  disconnecting, // 断开中
}

/// VPN 状态数据模型
class VpnState {
  final VpnStatus status;
  final String? errorMessage;
  final int upSpeed;    // 上传速度 bytes/s
  final int downSpeed;  // 下载速度 bytes/s
  final int totalUp;    // 总上传 bytes
  final int totalDown;  // 总下载 bytes
  final Map<String, int>? latencyMap; // [NEW] 节点延迟数据

  const VpnState({
    required this.status,
    this.errorMessage,
    this.upSpeed = 0,
    this.downSpeed = 0,
    this.totalUp = 0,
    this.totalDown = 0,
    this.latencyMap,
  });

  /// 是否已连接
  bool get isConnected => status == VpnStatus.connected;

  /// 是否正在处理中(连接中或断开中)
  bool get isProcessing => 
      status == VpnStatus.connecting || status == VpnStatus.disconnecting;

  VpnState copyWith({
    VpnStatus? status,
    String? errorMessage,
    int? upSpeed,
    int? downSpeed,
    int? totalUp,
    int? totalDown,
    Map<String, int>? latencyMap,
  }) {
    return VpnState(
      status: status ?? this.status,
      errorMessage: errorMessage ?? this.errorMessage,
      upSpeed: upSpeed ?? this.upSpeed,
      downSpeed: downSpeed ?? this.downSpeed,
      totalUp: totalUp ?? this.totalUp,
      totalDown: totalDown ?? this.totalDown,
      latencyMap: latencyMap ?? this.latencyMap,
    );
  }
}
