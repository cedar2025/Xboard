import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class MacRuntimeService {
  MacRuntimeService._();

  static final MacRuntimeService instance = MacRuntimeService._();

  static const MethodChannel _channel =
      MethodChannel('com.elephant.network/runtime');

  bool get isSupported => defaultTargetPlatform == TargetPlatform.macOS;

  Future<List<Map<String, dynamic>>> listNetworkServices() async {
    if (!isSupported) return const [];
    final result =
        await _channel.invokeMethod<List<dynamic>>('listNetworkServices');
    return (result ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList(growable: false);
  }

  Future<Map<String, dynamic>> startProxyMode({
    required String configPath,
    required String binaryPath,
    required int proxyPort,
  }) async {
    return _invokeMap('startProxyMode', {
      'configPath': configPath,
      'binaryPath': binaryPath,
      'proxyPort': proxyPort,
    });
  }

  Future<Map<String, dynamic>> startTunMode({
    required String configPath,
    required String binaryPath,
  }) async {
    return _invokeMap('startTunMode', {
      'configPath': configPath,
      'binaryPath': binaryPath,
    });
  }

  Future<Map<String, dynamic>> stopCore() => _invokeMap('stopCore');

  Future<Map<String, dynamic>> applySystemProxy(int proxyPort) {
    return _invokeMap('applySystemProxy', {'proxyPort': proxyPort});
  }

  Future<Map<String, dynamic>> restoreSystemProxy() =>
      _invokeMap('restoreSystemProxy');

  Future<Map<String, dynamic>> getRuntimeStatus() =>
      _invokeMap('getRuntimeStatus');

  Future<String?> exportDiagnostics() async {
    if (!isSupported) return null;
    return await _channel.invokeMethod<String>('exportDiagnostics');
  }

  Future<Map<String, dynamic>> _invokeMap(String method,
      [Map<String, dynamic>? arguments]) async {
    if (!isSupported) return const {};
    final result = await _channel.invokeMethod(method, arguments);
    if (result is Map) {
      return Map<String, dynamic>.from(result);
    }
    return const {};
  }
}
