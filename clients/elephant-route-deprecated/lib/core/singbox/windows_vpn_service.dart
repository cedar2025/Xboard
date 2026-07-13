import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

import 'connection_latency_manager.dart';
import 'vpn_manager.dart';
import 'vpn_state.dart';
import 'windows_latency_session.dart';
import 'windows_service_protocol.dart';

class WindowsVpnService implements VpnManager, ConnectionLatencyManager {
  WindowsVpnService({
    MethodChannel? methodChannel,
    EventChannel? eventChannel,
  })  : _methodChannel = methodChannel ??
            const MethodChannel(WindowsServiceProtocol.methodChannel),
        _eventChannel = eventChannel ??
            const EventChannel(WindowsServiceProtocol.eventChannel) {
    _eventSubscription = _eventChannel.receiveBroadcastStream().listen(
      _handleState,
      onError: (Object error, StackTrace stackTrace) {
        debugPrint('Windows service event stream failed: $error');
      },
    );
  }

  final MethodChannel _methodChannel;
  final EventChannel _eventChannel;
  final StreamController<VpnState> _stateController =
      StreamController<VpnState>.broadcast();

  StreamSubscription<Object?>? _eventSubscription;
  VpnState _state = const VpnState(
    status: VpnStatus.disconnected,
    connectionMode: VpnConnectionMode.tun,
  );
  bool _disposed = false;
  String? _lastRuntimeConfig;
  WindowsLatencySession? _latencySession;

  @override
  Future<bool> requestPermission() async {
    try {
      final result = await _invokeMap('getStatus');
      _handleState(result);
      return result['error_code'] != 'service_unavailable';
    } on PlatformException catch (error) {
      _setUnavailable(error.message);
      return false;
    } on MissingPluginException {
      _setUnavailable('Windows 后台服务桥接未安装');
      return false;
    }
  }

  @override
  Future<void> start(String config) async {
    WindowsServiceProtocol.validateConfig(config);
    _updateState(_state.copyWith(
      status: VpnStatus.connecting,
      connectionMode: VpnConnectionMode.tun,
      resetErrorMessage: true,
      resetFailureReason: true,
    ));

    final runtimeDir = _runtimeDirectory();
    final sanitized = _sanitizeConfig(config, runtimeDir);
    try {
      final result = await _invokeMap('start', {
        'protocol_version': WindowsServiceProtocol.protocolVersion,
        'config': sanitized,
      });
      _handleState(result);
      if (_state.status == VpnStatus.connected) {
        _lastRuntimeConfig = sanitized;
      }
    } on PlatformException catch (error) {
      _updateState(VpnState(
        status: VpnStatus.error,
        connectionMode: VpnConnectionMode.tun,
        errorMessage: error.message ?? 'Windows TUN 启动失败',
        failureReason: VpnFailureReason.coreStartFailed,
      ));
      rethrow;
    }
  }

  @override
  Future<void> prepareSpeedTest(String config) async {
    WindowsServiceProtocol.validateConfig(config);
    await _invokeMap('prepareSpeedTest', {
      'protocol_version': WindowsServiceProtocol.protocolVersion,
      'config': _sanitizeConfig(config, _runtimeDirectory()),
    });
  }

  @override
  Future<void> stopSpeedTest() async {
    await _invokeMap('stopSpeedTest');
  }

  @override
  Future<void> stop({
    VpnStopReason reason = VpnStopReason.unspecified,
  }) async {
    if (_disposed) return;
    await stopConnectionLatencyTest();
    _updateState(_state.copyWith(status: VpnStatus.disconnecting));
    try {
      final result = await _invokeMap('stop');
      _handleState(result);
    } on PlatformException catch (error) {
      _updateState(VpnState(
        status: VpnStatus.restoreFailed,
        connectionMode: VpnConnectionMode.tun,
        errorMessage: error.message ?? 'Windows TUN 清理失败',
        failureReason: VpnFailureReason.restoreFailed,
      ));
      rethrow;
    }
  }

  @override
  Future<int> urlTest(String groupTag) async {
    final result = await _invokeMap('urlTest', {'group_tag': groupTag});
    final delay = result['delay'];
    if (delay is int) return delay;
    return int.tryParse(delay?.toString() ?? '') ?? -1;
  }

  @override
  Future<void> selectOutbound(String groupTag, String outboundTag) async {
    final result = await _invokeMap('selectOutbound', {
      'group_tag': groupTag,
      'outbound_tag': outboundTag,
    });
    _handleState(result);
  }

  @override
  Future<Map<String, ConnectionLatencyResult>> testConnectionLatencies({
    required List<String> nodeTags,
    required String testUrl,
    required int timeoutMs,
    required int concurrency,
    ConnectionLatencyResultCallback? onResult,
  }) async {
    final sourceConfig = _lastRuntimeConfig;
    if (_state.status != VpnStatus.connected || sourceConfig == null) {
      throw const WindowsLatencyException('请先开启加速后再测速');
    }
    await stopConnectionLatencyTest();
    final executableDirectory = File(Platform.resolvedExecutable).parent.path;
    final session = WindowsLatencySession(
      binaryPath: '$executableDirectory\\sing-box-windows-amd64.exe',
      sourceConfig: sourceConfig,
      nodeTags: nodeTags,
      testUrl: testUrl,
      timeoutMs: timeoutMs,
      workerCount: concurrency,
    );
    _latencySession = session;
    try {
      return await session.run(onResult: onResult);
    } finally {
      if (identical(_latencySession, session)) {
        _latencySession = null;
      }
    }
  }

  @override
  Future<void> stopConnectionLatencyTest() async {
    final session = _latencySession;
    _latencySession = null;
    await session?.close();
  }

  @override
  VpnState get currentState => _state;

  @override
  Stream<VpnState> get stateStream => _stateController.stream;

  void _handleState(Object? value) {
    _updateState(WindowsServiceProtocol.parseState(value));
  }

  void _setUnavailable(String? message) {
    _updateState(VpnState(
      status: VpnStatus.error,
      connectionMode: VpnConnectionMode.tun,
      errorMessage: message ?? 'Windows 后台服务不可用，请重新安装客户端',
      failureReason: VpnFailureReason.coreStartFailed,
      runtimeDetails: const {'error_code': 'service_unavailable'},
    ));
  }

  void _updateState(VpnState next) {
    _state = next;
    if (!_disposed && !_stateController.isClosed) {
      _stateController.add(next);
    }
  }

  Future<Map<String, dynamic>> _invokeMap(
    String method, [
    Map<String, dynamic>? arguments,
  ]) async {
    assert(WindowsServiceProtocol.supportedMethods.contains(method));
    final result =
        await _methodChannel.invokeMethod<Object?>(method, arguments);
    if (result is Map) return Map<String, dynamic>.from(result);
    return const <String, dynamic>{};
  }

  String _runtimeDirectory() {
    final programData =
        Platform.environment['ProgramData'] ?? r'C:\ProgramData';
    return '$programData\\ElephantNetwork\\runtime';
  }

  String _sanitizeConfig(String jsonConfig, String runtimeDir) {
    final config = Map<String, dynamic>.from(jsonDecode(jsonConfig) as Map);
    config.remove('use_tun_mode');
    config['log'] = {'level': 'warn', 'timestamp': true};

    final inbounds = (config['inbounds'] as List?) ?? <dynamic>[];
    inbounds.removeWhere((dynamic inbound) =>
        inbound is Map &&
        (inbound['type'] == 'tun' || inbound['type'] == 'mixed'));
    inbounds.add({
      'type': 'tun',
      'tag': 'tun-in',
      'interface_name': 'ElephantNetwork',
      'address': ['172.19.0.1/30', 'fdfe:dcba:9876::1/126'],
      'auto_route': true,
      'strict_route': true,
      'stack': 'system',
      'sniff': true,
    });
    config['inbounds'] = inbounds;

    final route = config['route'];
    if (route is Map) {
      final ruleSets = route['rule_set'];
      if (ruleSets is List) {
        for (var index = 0; index < ruleSets.length; index += 1) {
          final ruleSet = ruleSets[index];
          if (ruleSet is Map && ruleSet['type'] == 'remote') {
            final tag = ruleSet['tag']?.toString() ?? '';
            if (tag == 'geoip-cn' || tag == 'geosite-cn') {
              ruleSets[index] = {
                'tag': tag,
                'type': 'local',
                'format': ruleSet['format'] ?? 'binary',
                'path': '$runtimeDir\\$tag.srs',
              };
            }
          }
        }
      }
      route['final'] = '节点选择';
    }

    final experimental = config.putIfAbsent(
      'experimental',
      () => <String, dynamic>{},
    );
    if (experimental is Map) {
      final clashApi = experimental.putIfAbsent(
        'clash_api',
        () => <String, dynamic>{},
      );
      if (clashApi is Map) {
        clashApi['external_controller'] = '127.0.0.1:9090';
        clashApi['default_mode'] ??= 'rule';
      }
      final cacheFile = experimental['cache_file'];
      if (cacheFile is Map) cacheFile['path'] = '$runtimeDir\\cache.db';
    }

    return jsonEncode(config);
  }

  @override
  void dispose() {
    if (_disposed) return;
    unawaited(stop().catchError((Object error) {
      debugPrint('Windows service cleanup during dispose failed: $error');
    }));
    _disposed = true;
    _eventSubscription?.cancel();
    _stateController.close();
  }
}
