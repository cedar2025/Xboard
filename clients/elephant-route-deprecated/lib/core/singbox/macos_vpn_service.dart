import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';

import '../services/app_logger.dart';
import '../services/mac_runtime_service.dart';
import 'connection_latency_manager.dart';
import 'macos_latency_session.dart';
import 'macos_tun_permission.dart';
import 'macos_singbox_runtime.dart';
import 'vpn_manager.dart';
import 'vpn_state.dart';
import 'vpn_stop_coordinator.dart';

class MacosVpnService implements VpnManager, ConnectionLatencyManager {
  MacosVpnService()
      : _clashDio = Dio(
          BaseOptions(
            baseUrl: _clashApiBase,
            connectTimeout: const Duration(seconds: 3),
            receiveTimeout: const Duration(seconds: 5),
          ),
        ) {
    _clashDio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        client.findProxy = (_) => 'DIRECT';
        return client;
      },
    );
  }

  static const String _clashApiBase = 'http://127.0.0.1:9090';

  final Dio _clashDio;
  final MacRuntimeService _runtime = MacRuntimeService.instance;
  final _stateController = StreamController<VpnState>.broadcast();

  VpnState _state = const VpnState(status: VpnStatus.disconnected);
  String? _lastSanitizedConfig;
  String? _singboxDirPath;
  String? _singboxBinPath;
  int _proxyPort = 2334;
  bool _isTunMode = false;
  bool _disposed = false;
  MacosLatencySession? _latencySession;
  final VpnStopCoordinator<void> _stopCoordinator = VpnStopCoordinator<void>();

  @override
  Stream<VpnState> get stateStream => _stateController.stream;

  @override
  VpnState get currentState => _state;

  @override
  Future<bool> requestPermission() async {
    await AppLogger.instance.info('macOS requestPermission invoked');
    try {
      final permission = MacosTunPermission.fromNative(
        await _runtime.ensureTunHelper(),
      );
      if (permission.granted) return true;

      _updateState(
        VpnStatus.error,
        errorMessage: permission.message,
        failureReason: VpnFailureReason.permissionDenied,
        runtimeDetails: permission.details,
      );
      await AppLogger.instance.warn(
        'macOS TUN permission denied status=${permission.status} code=${permission.code}',
      );
      return false;
    } on PlatformException catch (error) {
      _updateState(
        VpnStatus.error,
        errorMessage: error.message ?? '后台网络组件检查失败',
        failureReason: VpnFailureReason.permissionDenied,
        runtimeDetails: {
          'status': 'connectionFailed',
          'code': error.code,
        },
      );
      return false;
    }
  }

  @override
  Future<void> start(String config) async {
    try {
      _updateState(VpnStatus.connecting, resetFailure: true, resetError: true);
      final supportDir = await getApplicationSupportDirectory();
      final singBoxDir = Directory('${supportDir.path}/sing-box');
      if (!await singBoxDir.exists()) {
        await singBoxDir.create(recursive: true);
      }

      final configMap = jsonDecode(config) as Map<String, dynamic>;
      _isTunMode = configMap['use_tun_mode'] == true;
      final sanitizedConfig =
          _sanitizeConfig(config, singBoxDir.path, _isTunMode);
      final configFile = File('${singBoxDir.path}/config.json');
      await configFile.writeAsString(sanitizedConfig);

      _lastSanitizedConfig = sanitizedConfig;
      _singboxDirPath = singBoxDir.path;

      final arch = await _getArchitecture();
      final binName =
          arch == 'arm64' ? 'sing-box-darwin-arm64' : 'sing-box-darwin-amd64';
      final binFile = File('${singBoxDir.path}/$binName');

      final coreReady = await _ensureCoreInstalled(
        singBoxDir: singBoxDir,
        binaryName: binName,
      );
      if (!coreReady) return;

      final configValid = await _validateConfig(
        binaryPath: binFile.path,
        configPath: configFile.path,
      );
      if (!configValid) return;

      _singboxBinPath = binFile.path;
      _proxyPort = _extractProxyPort(sanitizedConfig);

      await _cleanupResidualCacheFiles(singBoxDir.path);
      await AppLogger.instance.info(
        'Starting macOS runtime mode=${_isTunMode ? 'tun' : 'proxy'} port=$_proxyPort',
      );

      _updateState(VpnStatus.coreStarting);
      Map<String, dynamic> startResult;
      if (_isTunMode) {
        startResult = await _runtime.startTunMode(
          configPath: configFile.path,
          binaryPath: binFile.path,
        );
      } else {
        startResult = await _runtime.startProxyMode(
          configPath: configFile.path,
          binaryPath: binFile.path,
          proxyPort: _proxyPort,
        );
      }

      final started = startResult['ok'] == true;
      if (!started) {
        final errorMessage = (startResult['error'] as String?) ?? '启动内核失败';
        _updateState(
          VpnStatus.error,
          errorMessage: errorMessage,
          failureReason: _mapStartFailureReason(
            startResult,
            isTunMode: _isTunMode,
            fallbackError: errorMessage,
          ),
        );
        return;
      }

      if (!_isTunMode) {
        _updateState(VpnStatus.applyingProxy);
      }

      final healthy = await _waitForHealthCheck();
      if (!healthy) {
        await AppLogger.instance.warn('Health check failed after start');
        _updateState(
          VpnStatus.error,
          errorMessage: '连接健康检查失败，请检查本地内核和网络权限',
          failureReason: VpnFailureReason.healthCheckFailed,
        );
        return;
      }

      final runtimeStatus = await _runtime.getRuntimeStatus();
      _updateState(
        VpnStatus.connected,
        connectionMode:
            _isTunMode ? VpnConnectionMode.tun : VpnConnectionMode.proxy,
        runtimeDetails: runtimeStatus,
        resetFailure: true,
        resetError: true,
      );
      await AppLogger.instance.info('macOS runtime connected successfully');
    } catch (e, stackTrace) {
      await AppLogger.instance.error('macOS VpnService start failed',
          error: e, stackTrace: stackTrace);
      _updateState(
        VpnStatus.error,
        errorMessage: e.toString(),
        failureReason: VpnFailureReason.unknown,
      );
    }
  }

  @override
  Future<void> prepareSpeedTest(String config) async {}

  @override
  Future<void> stopSpeedTest() => stopConnectionLatencyTest();

  @override
  Future<Map<String, ConnectionLatencyResult>> testConnectionLatencies({
    required List<String> nodeTags,
    required String testUrl,
    required int timeoutMs,
    required int concurrency,
    ConnectionLatencyResultCallback? onResult,
  }) async {
    final config = _lastSanitizedConfig;
    final binaryPath = _singboxBinPath;
    if (config == null || binaryPath == null) {
      throw const MacosLatencyException('测速服务未就绪，请先连接大象网络');
    }

    await stopConnectionLatencyTest();
    final session = MacosLatencySession(
      binaryPath: binaryPath,
      sourceConfig: config,
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
      await session.close();
    }
  }

  @override
  Future<void> stopConnectionLatencyTest() async {
    final session = _latencySession;
    _latencySession = null;
    await session?.close();
  }

  @override
  Future<void> stop({
    VpnStopReason reason = VpnStopReason.unspecified,
  }) {
    final reused = _stopCoordinator.hasInFlight;
    AppLogger.instance.info(
      'macOS runtime stop requested reason=${reason.wireValue} reused=$reused',
    );
    return _stopCoordinator.run(() async {
      try {
        await stopConnectionLatencyTest();
        _updateState(VpnStatus.disconnecting, resetError: true);
        final result = await _runtime.stopCore(reason: reason.wireValue);
        final restored = result['proxyRestored'] != false;
        final nextStatus =
            restored ? VpnStatus.disconnected : VpnStatus.restoreFailed;
        _updateState(
          nextStatus,
          errorMessage: restored ? null : '系统代理恢复失败，请在设置页手动恢复',
          failureReason: restored ? null : VpnFailureReason.restoreFailed,
          resetError: restored,
          resetFailure: restored,
          connectionMode: VpnConnectionMode.unknown,
          runtimeDetails: result,
        );
        await AppLogger.instance.info(
            'macOS runtime stopped: reason=${reason.wireValue} restored=$restored');
      } catch (e, stackTrace) {
        await AppLogger.instance.error('macOS VpnService stop failed',
            error: e, stackTrace: stackTrace);
        _updateState(
          VpnStatus.restoreFailed,
          errorMessage: e.toString(),
          failureReason: VpnFailureReason.restoreFailed,
        );
      }
    });
  }

  @override
  Future<int> urlTest(String groupTag) async {
    try {
      final encodedGroup = Uri.encodeComponent(groupTag);
      final response = await _clashDio.get(
        '/proxies/$encodedGroup/delay',
        queryParameters: {
          'url': 'https://www.gstatic.com/generate_204',
          'timeout': 3000,
        },
      );
      if (response.statusCode == 200 && response.data != null) {
        final delay = response.data['delay'];
        if (delay is int && delay > 0) return delay;
      }
      return -1;
    } catch (e) {
      await AppLogger.instance.warn('macOS urlTest failed for $groupTag: $e');
      return -1;
    }
  }

  @override
  Future<void> selectOutbound(String groupTag, String outboundTag) async {
    if (_lastSanitizedConfig == null ||
        _singboxDirPath == null ||
        _singboxBinPath == null) {
      return;
    }

    try {
      _updateState(VpnStatus.coreStarting);

      final config = jsonDecode(_lastSanitizedConfig!) as Map<String, dynamic>;
      final outbounds = config['outbounds'];
      if (outbounds is List) {
        for (final outbound in outbounds) {
          if (outbound is Map<String, dynamic> &&
              outbound['type'] == 'selector' &&
              outbound['tag'] == groupTag) {
            outbound['default'] = outboundTag;
            break;
          }
        }
      }

      final updatedConfig = jsonEncode(config);
      _lastSanitizedConfig = updatedConfig;
      final configFile = File('$_singboxDirPath/config.json');
      await configFile.writeAsString(updatedConfig);
      await _cleanupResidualCacheFiles(_singboxDirPath!);

      final configValid = await _validateConfig(
        binaryPath: _singboxBinPath!,
        configPath: configFile.path,
      );
      if (!configValid) return;

      await _runtime.stopCore(reason: VpnStopReason.nodeSwitch.wireValue);
      Map<String, dynamic> startResult;
      if (_isTunMode) {
        startResult = await _runtime.startTunMode(
          configPath: configFile.path,
          binaryPath: _singboxBinPath!,
        );
      } else {
        _updateState(VpnStatus.applyingProxy);
        startResult = await _runtime.startProxyMode(
          configPath: configFile.path,
          binaryPath: _singboxBinPath!,
          proxyPort: _proxyPort,
        );
      }

      if (startResult['ok'] != true || !await _waitForHealthCheck()) {
        final errorMessage = (startResult['error'] as String?) ?? '节点切换失败，请重试';
        _updateState(
          VpnStatus.error,
          errorMessage: errorMessage,
          failureReason: _mapStartFailureReason(
            startResult,
            isTunMode: _isTunMode,
            fallbackError: errorMessage,
          ),
        );
        return;
      }

      _updateState(
        VpnStatus.connected,
        connectionMode:
            _isTunMode ? VpnConnectionMode.tun : VpnConnectionMode.proxy,
        runtimeDetails: await _runtime.getRuntimeStatus(),
        resetError: true,
        resetFailure: true,
      );
      await AppLogger.instance.info('Node switch completed: $outboundTag');
    } catch (e, stackTrace) {
      await AppLogger.instance
          .error('Node switch failed', error: e, stackTrace: stackTrace);
      _updateState(
        VpnStatus.error,
        errorMessage: '节点切换失败: $e',
        failureReason: VpnFailureReason.unknown,
      );
    }
  }

  @override
  void dispose() {
    if (_disposed) return;
    _disposed = true;
    unawaited(stopConnectionLatencyTest());
    _stateController.close();
  }

  void _updateState(
    VpnStatus status, {
    String? errorMessage,
    VpnFailureReason? failureReason,
    VpnConnectionMode? connectionMode,
    Map<String, dynamic>? runtimeDetails,
    bool resetError = false,
    bool resetFailure = false,
  }) {
    _state = _state.copyWith(
      status: status,
      errorMessage: errorMessage,
      resetErrorMessage: resetError,
      failureReason: failureReason,
      resetFailureReason: resetFailure,
      connectionMode: connectionMode,
      runtimeDetails: runtimeDetails,
    );
    if (!_disposed && !_stateController.isClosed) {
      _stateController.add(_state);
    }
  }

  Future<bool> _waitForHealthCheck() async {
    for (var i = 0; i < 12; i++) {
      try {
        final response = await _clashDio.get('/proxies');
        if (response.statusCode == 200) {
          return true;
        }
      } catch (_) {
        // Retry until timeout.
      }
      await Future.delayed(const Duration(milliseconds: 500));
    }
    return false;
  }

  VpnFailureReason _mapStartFailureReason(
    Map<String, dynamic> startResult, {
    required bool isTunMode,
    required String fallbackError,
  }) {
    final code = (startResult['code'] as String?)?.toUpperCase() ?? '';
    final errorText = fallbackError.toLowerCase();

    if (code == 'TUN_CONFLICT' ||
        code == 'TUN_ROUTE_CONFLICT' ||
        errorText.contains('route') ||
        errorText.contains('其他 tun/vpn') ||
        errorText.contains('冲突路由') ||
        errorText.contains('file exists')) {
      return VpnFailureReason.routeConflict;
    }
    if (code == 'PERMISSION_DENIED' ||
        code == 'HELPER_NOT_ENABLED' ||
        code == 'HELPER_NOT_REGISTERED' ||
        code == 'HELPER_REQUIRES_APPROVAL' ||
        code == 'HELPER_NOT_FOUND' ||
        errorText.contains('permission') ||
        errorText.contains('administrator') ||
        errorText.contains('授权') ||
        errorText.contains('后台网络组件')) {
      return VpnFailureReason.permissionDenied;
    }
    if (errorText.contains('proxy')) {
      return VpnFailureReason.proxySetupFailed;
    }
    if (errorText.contains('health check')) {
      return VpnFailureReason.healthCheckFailed;
    }
    return isTunMode
        ? VpnFailureReason.coreStartFailed
        : VpnFailureReason.coreStartFailed;
  }

  Future<void> _cleanupResidualCacheFiles(String baseDir) async {
    final filesToDelete = [
      '$baseDir/cache.db',
      '$baseDir/cache.db-wal',
      '$baseDir/cache.db-shm',
    ];
    for (final filePath in filesToDelete) {
      final file = File(filePath);
      if (await file.exists()) {
        try {
          await file.delete();
        } catch (_) {
          // Best-effort cleanup.
        }
      }
    }
  }

  int _extractProxyPort(String jsonConfig) {
    final updatedConfigMap = jsonDecode(jsonConfig) as Map<String, dynamic>;
    final inbounds = updatedConfigMap['inbounds'];
    if (inbounds is List) {
      for (final inbound in inbounds) {
        if (inbound is Map<String, dynamic>) {
          final type = inbound['type'];
          if (type == 'mixed' || type == 'socks' || type == 'http') {
            return (inbound['listen_port'] as num?)?.toInt() ?? 2334;
          }
        }
      }
    }
    return 2334;
  }

  Future<String> _getArchitecture() async {
    final result = await Process.run('uname', ['-m']);
    final out = result.stdout.toString().trim();
    if (out == 'arm64' || out == 'aarch64') {
      return 'arm64';
    }
    return 'amd64';
  }

  Future<bool> _ensureCoreInstalled({
    required Directory singBoxDir,
    required String binaryName,
  }) async {
    final installer = MacosSingBoxRuntimeInstaller(
      assetLoader: (assetPath) async {
        final byteData = await rootBundle.load(assetPath);
        return byteData.buffer.asUint8List(
          byteData.offsetInBytes,
          byteData.lengthInBytes,
        );
      },
      stopCore: () async {
        await _runtime.stopCore(reason: 'core_upgrade');
      },
      makeExecutable: (file) async {
        final result = await Process.run('chmod', ['+x', file.path]);
        if (result.exitCode != 0) {
          throw FileSystemException(
            'Failed to make sing-box executable',
            file.path,
          );
        }
      },
    );

    try {
      final updated = await installer.ensureInstalled(
        runtimeDirectory: singBoxDir,
        binaryName: binaryName,
      );
      if (updated) {
        await AppLogger.instance.info(
          'Updated macOS sing-box runtime to ${MacosSingBoxRuntime.targetVersion}',
        );
      }
      return true;
    } catch (error, stackTrace) {
      await AppLogger.instance.error(
        'Failed to update macOS sing-box runtime',
        error: error,
        stackTrace: stackTrace,
      );
      _updateState(
        VpnStatus.error,
        errorMessage: '后台内核更新失败，请重新安装客户端',
        failureReason: VpnFailureReason.coreStartFailed,
        runtimeDetails: const {'code': 'CORE_VERSION_MISMATCH'},
      );
      return false;
    }
  }

  Future<bool> _validateConfig({
    required String binaryPath,
    required String configPath,
  }) async {
    final validation = await MacosSingBoxRuntime.validateConfig(
      binaryPath: binaryPath,
      configPath: configPath,
    );
    if (validation.isValid) return true;

    final error = validation.error ?? 'sing-box 配置检查失败';
    await AppLogger.instance.warn('macOS config validation failed: $error');
    _updateState(
      VpnStatus.error,
      errorMessage: error,
      failureReason: VpnFailureReason.coreStartFailed,
      runtimeDetails: const {'code': 'CONFIG_INVALID'},
    );
    return false;
  }

  String _sanitizeConfig(String jsonConfig, String baseDir, bool useTunMode) {
    try {
      final config = jsonDecode(jsonConfig) as Map<String, dynamic>;
      config.remove('use_tun_mode');
      config['log'] = {'level': 'warn', 'timestamp': true};

      if (config['inbounds'] is List) {
        final inbounds = config['inbounds'] as List<dynamic>;
        if (!useTunMode) {
          inbounds.removeWhere((inbound) => inbound['type'] == 'tun');
        } else {
          var hasTunInbound = false;
          for (final inbound in inbounds) {
            if (inbound['type'] == 'tun') {
              hasTunInbound = true;
              inbound.remove('address');
              inbound.remove('interface_name');
              inbound['inet4_address'] = '172.19.0.1/30';
              inbound['inet6_address'] = 'fdfe:dcba:9876::1/126';
              inbound['mtu'] = 1500;
              inbound['auto_route'] = true;
              inbound['strict_route'] = false;
              inbound['stack'] = 'system';
            }
          }
          if (!hasTunInbound) {
            inbounds.add({
              'type': 'tun',
              'tag': 'tun-in',
              'inet4_address': '172.19.0.1/30',
              'inet6_address': 'fdfe:dcba:9876::1/126',
              'mtu': 1500,
              'auto_route': true,
              'strict_route': false,
              'stack': 'system',
              'sniff': true,
            });
          }
        }

        final hasProxyInbound = inbounds.any((inbound) {
          final type = inbound['type'];
          return type == 'mixed' || type == 'socks' || type == 'http';
        });
        if (!hasProxyInbound) {
          inbounds.add({
            'type': 'mixed',
            'tag': 'mixed-in',
            'listen': '127.0.0.1',
            'listen_port': 2334,
            'sniff': true,
          });
        }
      }

      if (config['route'] is Map<String, dynamic>) {
        final route = config['route'] as Map<String, dynamic>;
        if (route['rule_set'] is List) {
          final ruleSets = route['rule_set'] as List<dynamic>;
          for (var i = 0; i < ruleSets.length; i++) {
            final ruleSet = ruleSets[i];
            if (ruleSet is Map<String, dynamic> &&
                ruleSet['type'] == 'remote') {
              final tag = ruleSet['tag'];
              ruleSets[i] = {
                'tag': tag,
                'type': 'local',
                'format': ruleSet['format'] ?? 'binary',
                'path': '$baseDir/$tag.srs',
              };
            }
          }
        }
        route['final'] = '节点选择';
      }

      final experimental = (config['experimental'] as Map<String, dynamic>?) ??
          <String, dynamic>{};
      config['experimental'] = experimental;
      final clashApi = (experimental['clash_api'] as Map<String, dynamic>?) ??
          <String, dynamic>{};
      experimental['clash_api'] = clashApi;
      clashApi['external_controller'] = '127.0.0.1:9090';
      clashApi['default_mode'] = clashApi['default_mode'] ?? 'rule';

      if (experimental['cache_file'] is Map<String, dynamic>) {
        final cacheFile = experimental['cache_file'] as Map<String, dynamic>;
        cacheFile['path'] = '$baseDir/cache.db';
      }

      return jsonEncode(config);
    } catch (e) {
      debugPrint('Config sanitization failed: $e');
      return jsonConfig;
    }
  }
}
