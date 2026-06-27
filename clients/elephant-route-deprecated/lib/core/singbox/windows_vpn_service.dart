import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';
import 'vpn_manager.dart';
import 'vpn_state.dart';

class WindowsVpnService implements VpnManager {
  static const MethodChannel _proxyChannel =
      MethodChannel('com.elephant.network/proxy');
  static const String _clashApiBase = 'http://127.0.0.1:9090';

  // Use a Dio instance that BYPASSES system proxy for Clash API calls
  late final Dio _clashDio;

  Process? _singboxProcess;
  bool _isConnected = false;
  bool _isRestarting =
      false; // Prevent false disconnect during node switch restart
  VpnState _state = const VpnState(status: VpnStatus.disconnected);
  final _stateController = StreamController<VpnState>.broadcast();

  // Store last config info for restart-based node switching
  String? _lastSanitizedConfig;
  String? _singboxDirPath;
  String? _singboxBinPath;
  int _proxyPort = 2334;

  WindowsVpnService() {
    _clashDio = Dio(BaseOptions(
      baseUrl: _clashApiBase,
      connectTimeout: const Duration(seconds: 3),
      receiveTimeout: const Duration(seconds: 5),
    ));
    // Force Dio to bypass system proxy for local Clash API calls
    _clashDio.httpClientAdapter = IOHttpClientAdapter(
      onHttpClientCreate: (client) {
        client.findProxy = (uri) => 'DIRECT';
        return client;
      },
    );
  }

  void _updateState(VpnStatus status, {String? errorMessage}) {
    _state = _state.copyWith(status: status, errorMessage: errorMessage);
    _stateController.add(_state);
  }

  @override
  Future<bool> requestPermission() async {
    // Windows 在此阶段不需要特殊的 VPN 权限请求
    return true;
  }

  @override
  Future<void> start(String config) async {
    try {
      debugPrint('Windows VpnService start...');
      final singboxDir = await getApplicationSupportDirectory();
      final configMap = jsonDecode(config);
      final useTunMode = configMap['use_tun_mode'] ?? false;
      final updatedConfig =
          _sanitizeConfig(config, singboxDir.path, useTunMode);
      final configFile = File('${singboxDir.path}/config.json');
      await configFile.writeAsString(updatedConfig);

      // Store for restart-based switching
      _lastSanitizedConfig = updatedConfig;
      _singboxDirPath = singboxDir.path;

      final binName = 'sing-box-windows-amd64.exe';
      final binFile = File('${singboxDir.path}/$binName');

      if (!await binFile.exists()) {
        final byteData = await rootBundle.load('assets/bin/windows/$binName');
        await binFile.writeAsBytes(byteData.buffer
            .asUint8List(byteData.offsetInBytes, byteData.lengthInBytes));
      }

      _singboxBinPath = binFile.path;

      final updatedConfigMap = jsonDecode(updatedConfig);
      int proxyPort = 2334; // Default to mixed port if possible
      if (updatedConfigMap['inbounds'] != null) {
        for (var inbound in updatedConfigMap['inbounds']) {
          if (inbound['type'] == 'mixed' || inbound['type'] == 'http') {
            proxyPort = inbound['listen_port'] ?? proxyPort;
            break;
          }
        }
      }
      _proxyPort = proxyPort;

      // Clear cache files to prevent SQLite corruption errors on start
      final filesToDelete = [
        '${singboxDir.path}/cache.db',
        '${singboxDir.path}/cache.db-wal',
        '${singboxDir.path}/cache.db-shm'
      ];
      for (final filePath in filesToDelete) {
        final f = File(filePath);
        if (await f.exists()) {
          try {
            await f.delete();
            debugPrint('WindowsVpnService: deleted $filePath on start');
          } catch (e) {
            debugPrint(
                'WindowsVpnService: failed to delete $filePath on start: $e');
          }
        }
      }

      if (useTunMode) {
        // TUN 模式：需要提权启动
        // 使用 PowerShell Start-Process 触发 UAC 提权
        final script =
            'Start-Process "${binFile.path}" -ArgumentList "run", "-c", "${configFile.path}" -Verb RunAs -WindowStyle Hidden';
        _singboxProcess =
            await Process.start('powershell', ['-Command', script]);
      } else {
        // 普通模式：直接启动
        _singboxProcess = await Process.start(
          binFile.path,
          ['run', '-c', configFile.path],
          workingDirectory: singboxDir.path,
        );
      }

      _singboxProcess?.stdout.transform(utf8.decoder).listen((data) {
        debugPrint('[sing-box]: $data');
      });
      _singboxProcess?.stderr.transform(utf8.decoder).listen((data) {
        debugPrint('[sing-box ERR]: $data');
      });

      _singboxProcess?.exitCode.then((code) {
        debugPrint('sing-box process exited with code $code');
        if (_isRestarting) {
          debugPrint('sing-box exit during restart, ignoring...');
          return;
        }
        _isConnected = false;
        _updateState(VpnStatus.disconnected);
        _disableSystemProxy();
      });

      if (!useTunMode) {
        final proxySuccess = await _enableSystemProxy(proxyPort);
        // Even if proxy enabling fails, sing-box is running, so we connect.
        if (!proxySuccess) {
          debugPrint('Failed to setup system proxy but sing-box is running.');
        }
      } else {
        debugPrint('TUN mode active, bypassing system proxy settings.');
      }

      _isConnected = true;
      _updateState(VpnStatus.connected);
    } catch (e) {
      debugPrint('macOS VpnService start failed: $e');
      _updateState(VpnStatus.disconnected, errorMessage: e.toString());
      await stop();
    }
  }

  @override
  Future<void> stop() async {
    try {
      debugPrint('Windows VpnService stop...');
      await _disableSystemProxy();

      if (_singboxProcess != null) {
        _singboxProcess?.kill();
        _singboxProcess = null;
      }

      _isConnected = false;
      _updateState(VpnStatus.disconnected);

      // 对于通过 UAC 运行的进程，直接 kill 句柄可能无效，
      // 使用 taskkill 按照文件名杀掉作为兜底
      await Process.run('taskkill', ['/F', '/IM', 'sing-box.exe']);
    } catch (e) {
      debugPrint('macOS VpnService stop error: $e');
      _updateState(VpnStatus.disconnected, errorMessage: e.toString());
    }
  }

  @override
  VpnState get currentState => _state;

  @override
  Stream<VpnState> get stateStream => _stateController.stream;

  @override
  Future<int> urlTest(String groupTag) async {
    // 通过 Clash API 触发 URL 测试
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
      debugPrint('WindowsVpnService urlTest failed: $e');
      return -1;
    }
  }

  @override
  Future<void> selectOutbound(String groupTag, String outboundTag) async {
    if (!_isConnected ||
        _lastSanitizedConfig == null ||
        _singboxDirPath == null ||
        _singboxBinPath == null) {
      debugPrint(
          'WindowsVpnService selectOutbound: not connected or no stored config, skipping');
      return;
    }

    debugPrint(
        'WindowsVpnService selectOutbound: switching $groupTag -> $outboundTag via restart');

    try {
      // 1. Modify the stored config to set the new outbound as the selector's default
      final Map<String, dynamic> config = jsonDecode(_lastSanitizedConfig!);

      if (config.containsKey('outbounds') && config['outbounds'] is List) {
        final List<dynamic> outbounds = config['outbounds'];
        for (var outbound in outbounds) {
          if (outbound is Map &&
              outbound['type'] == 'selector' &&
              outbound['tag'] == groupTag) {
            outbound['default'] = outboundTag;
            debugPrint(
                'WindowsVpnService: set selector "$groupTag" default to "$outboundTag"');
            break;
          }
        }
      }

      final updatedConfig = jsonEncode(config);
      _lastSanitizedConfig = updatedConfig;

      // 2. Write updated config
      final configFile = File('$_singboxDirPath/config.json');
      await configFile.writeAsString(updatedConfig);

      // 3. Clear cache to prevent sing-box from restoring old selection
      final filesToDelete = [
        '$_singboxDirPath/cache.db',
        '$_singboxDirPath/cache.db-wal',
        '$_singboxDirPath/cache.db-shm'
      ];
      for (final filePath in filesToDelete) {
        final f = File(filePath);
        if (await f.exists()) {
          try {
            await f.delete();
            debugPrint('WindowsVpnService: deleted $filePath');
          } catch (e) {
            debugPrint('WindowsVpnService: failed to delete $filePath: $e');
          }
        }
      }

      // 4. Kill current sing-box process (set restarting flag to prevent false disconnect)
      _isRestarting = true;
      if (_singboxProcess != null) {
        _singboxProcess?.kill();
        _singboxProcess = null;
        // Brief wait for process to exit
        await Future.delayed(const Duration(milliseconds: 500));
      }

      // 5. Restart sing-box with updated config (system proxy stays enabled)
      _singboxProcess = await Process.start(
        _singboxBinPath!,
        ['run', '-c', configFile.path],
        workingDirectory: _singboxDirPath!,
      );

      _singboxProcess?.stdout.transform(utf8.decoder).listen((data) {
        debugPrint('[sing-box]: $data');
      });
      _singboxProcess?.stderr.transform(utf8.decoder).listen((data) {
        debugPrint('[sing-box ERR]: $data');
      });

      _singboxProcess?.exitCode.then((code) {
        debugPrint('sing-box process exited with code $code');
        if (_isRestarting) {
          debugPrint('sing-box exit during restart, ignoring...');
          return;
        }
        _isConnected = false;
        _updateState(VpnStatus.disconnected);
        _disableSystemProxy();
      });

      // Wait for sing-box to start up then clear restarting flag
      await Future.delayed(const Duration(milliseconds: 800));
      _isRestarting = false;

      debugPrint(
          'WindowsVpnService selectOutbound: restart complete, now using "$outboundTag"');
    } catch (e) {
      debugPrint('WindowsVpnService selectOutbound FAILED: $e');
    }
  }

  @override
  void dispose() {
    stop();
    _stateController.close();
  }

  // ================= 辅助方法 =================

  Future<String> _getArchitecture() async {
    final result = await Process.run('uname', ['-m']);
    final out = result.stdout.toString().trim();
    if (out == 'arm64' || out == 'aarch64') {
      return 'arm64';
    }
    return 'amd64';
  }

  Future<bool> _enableSystemProxy(int port) async {
    try {
      final bool result = await _proxyChannel.invokeMethod('enableProxy', {
        'port': port,
      });
      return result;
    } catch (e) {
      debugPrint('Invoke enableProxy failed: $e');
      return false; // 可能由于没有权限或者还没实现
    }
  }

  Future<bool> _disableSystemProxy() async {
    try {
      final bool result = await _proxyChannel.invokeMethod('disableProxy');
      return result;
    } catch (e) {
      debugPrint('Invoke disableProxy failed: $e');
      return false;
    }
  }

  String _sanitizeConfig(String jsonConfig, String baseDir, bool useTunMode) {
    try {
      final Map<String, dynamic> config = jsonDecode(jsonConfig);

      // 1. Set log level to warn to reduce TRACE noise
      config['log'] = {'level': 'warn', 'timestamp': true};

      // 2. Remove TUN inbounds (unsupported without root perms on macOS desktop)
      if (config.containsKey('inbounds') && config['inbounds'] is List) {
        final List<dynamic> inbounds = config['inbounds'];

        if (!useTunMode) {
          // 非 TUN 模式：移除 tun 入站
          inbounds.removeWhere((inbound) => inbound['type'] == 'tun');
        } else {
          // TUN 模式：确保 tun 入站配置正确
          bool hasTunInbound =
              inbounds.any((inbound) => inbound['type'] == 'tun');
          if (!hasTunInbound) {
            inbounds.add({
              "type": "tun",
              "tag": "tun-in",
              "interface_name": "singbox-tun",
              "inet4_address": "172.19.0.1/30",
              "inet6_address": "fdfe:dcba:9876::1/126",
              "auto_route": true,
              "strict_route": true,
              "stack": "system",
              "sniff": true
            });
          }
        }

        // Ensure there is at least one mixed or http inbound if none left
        bool hasProxyInbound = inbounds.any((inbound) =>
            inbound['type'] == 'mixed' || inbound['type'] == 'http');

        if (!hasProxyInbound) {
          inbounds.add({
            "type": "mixed",
            "tag": "mixed-in",
            "listen": "127.0.0.1",
            "listen_port": 2334,
            "sniff": true
          });
        }
      }

      // 3. Convert remote rule_sets to local rule_sets
      if (config.containsKey('route') && config['route'] is Map) {
        final Map<String, dynamic> route = config['route'];
        if (route.containsKey('rule_set') && route['rule_set'] is List) {
          final List<dynamic> ruleSets = route['rule_set'];
          for (var i = 0; i < ruleSets.length; i++) {
            final ruleSet = ruleSets[i];
            if (ruleSet is Map && ruleSet['type'] == 'remote') {
              // Convert to local using the assets we copied
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
      }

      // 4. Ensure experimental.clash_api is configured for local REST API
      if (!config.containsKey('experimental')) {
        config['experimental'] = <String, dynamic>{};
      }
      final experimental = config['experimental'] as Map<String, dynamic>;

      // Inject clash_api if not present
      if (!experimental.containsKey('clash_api')) {
        experimental['clash_api'] = <String, dynamic>{};
      }
      final clashApi = experimental['clash_api'] as Map<String, dynamic>;
      clashApi['external_controller'] = '127.0.0.1:9090';
      // Optionally set default mode
      if (!clashApi.containsKey('default_mode')) {
        clashApi['default_mode'] = 'rule';
      }

      // Ensure cache_file path is absolute
      if (experimental.containsKey('cache_file') &&
          experimental['cache_file'] is Map) {
        final cacheFile = experimental['cache_file'] as Map<String, dynamic>;
        cacheFile['path'] = '$baseDir/cache.db';
      }

      // 5. Add explicit final outbound to route through 节点选择 selector
      if (config.containsKey('route') && config['route'] is Map) {
        final Map<String, dynamic> route = config['route'];
        // Ensure unmatched traffic goes through the selector for proper outbound switching
        route['final'] = '节点选择';
      }

      return jsonEncode(config);
    } catch (e) {
      debugPrint('Config sanitization failed: $e');
      return jsonConfig;
    }
  }
}
