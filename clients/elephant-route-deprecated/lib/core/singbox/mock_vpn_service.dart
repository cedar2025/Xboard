import 'dart:async';
import 'dart:math';
import 'package:flutter/foundation.dart';
import 'vpn_manager.dart';
import 'vpn_state.dart';

/// Mock VPN 服务实现(用于测试和演示)
class MockVpnService implements VpnManager {
  final _stateController = StreamController<VpnState>.broadcast();
  VpnState _currentState = const VpnState(status: VpnStatus.disconnected);
  Timer? _speedTimer;

  @override
  Stream<VpnState> get stateStream => _stateController.stream;

  @override
  VpnState get currentState => _currentState;

  @override
  Future<bool> requestPermission() async {
    // Mock: 模拟权限请求延迟
    await Future.delayed(const Duration(milliseconds: 500));
    // Mock: 总是返回已授权
    return true;
  }

  @override
  Future<void> start(String config) async {
    // 更新为连接中
    _updateState(VpnStatus.connecting);

    // Mock: 模拟连接延迟
    await Future.delayed(const Duration(seconds: 2));

    // 更新为已连接
    _updateState(VpnStatus.connected);

    // 开始模拟流量统计
    _startSpeedSimulation();
  }

  @override
  Future<void> prepareSpeedTest(String config) async {}

  @override
  Future<void> stopSpeedTest() async {}

  @override
  Future<void> stop({
    VpnStopReason reason = VpnStopReason.unspecified,
  }) async {
    // 停止流量模拟
    _speedTimer?.cancel();
    _speedTimer = null;

    // 更新为断开中
    _updateState(VpnStatus.disconnecting);

    // Mock: 模拟断开延迟
    await Future.delayed(const Duration(seconds: 1));

    // 更新为未连接,重置流量
    _currentState = const VpnState(status: VpnStatus.disconnected);
    _stateController.add(_currentState);
  }

  @override
  Future<void> selectOutbound(String groupTag, String outboundTag) async {
    // Mock: 模拟切换延迟
    await Future.delayed(const Duration(milliseconds: 100));
    debugPrint('Mock: Selected $outboundTag in $groupTag');
  }

  @override
  void dispose() {
    _speedTimer?.cancel();
    _stateController.close();
  }

  @override
  Future<int> urlTest(String groupTag) async {
    // Mock: 模拟测试延迟
    await Future.delayed(const Duration(milliseconds: 300));
    // 随机返回 50-300ms 之间的延迟
    return Random().nextInt(250) + 50;
  }

  /// 更新 VPN 状态
  void _updateState(VpnStatus status, {String? errorMessage}) {
    _currentState = _currentState.copyWith(
      status: status,
      errorMessage: errorMessage,
    );
    _stateController.add(_currentState);
  }

  /// 模拟流量速度(仅用于演示)
  void _startSpeedSimulation() {
    final random = Random();
    _speedTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_currentState.status != VpnStatus.connected) {
        timer.cancel();
        return;
      }

      // 生成随机速度(10KB/s - 5MB/s)
      final upSpeed = random.nextInt(5 * 1024 * 1024) + 10 * 1024;
      final downSpeed = random.nextInt(5 * 1024 * 1024) + 10 * 1024;

      _currentState = _currentState.copyWith(
        upSpeed: upSpeed,
        downSpeed: downSpeed,
        totalUp: _currentState.totalUp + upSpeed,
        totalDown: _currentState.totalDown + downSpeed,
      );
      _stateController.add(_currentState);
    });
  }
}
