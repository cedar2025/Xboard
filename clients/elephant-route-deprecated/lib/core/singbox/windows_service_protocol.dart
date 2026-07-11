import 'dart:convert';

import 'vpn_state.dart';

final class WindowsServiceProtocol {
  WindowsServiceProtocol._();

  static const methodChannel = 'com.elephant.network/windows_service';
  static const eventChannel = 'com.elephant.network/windows_service/events';
  static const protocolVersion = 1;
  static const maxConfigBytes = 4 * 1024 * 1024;

  static const supportedMethods = <String>{
    'getStatus',
    'start',
    'stop',
    'urlTest',
    'selectOutbound',
    'prepareSpeedTest',
    'stopSpeedTest',
    'protectSecret',
    'unprotectSecret',
    'deleteSecret',
    'deleteAllSecrets',
  };

  static VpnState parseState(Object? value) {
    if (value is! Map) {
      return const VpnState(
        status: VpnStatus.error,
        failureReason: VpnFailureReason.unknown,
        connectionMode: VpnConnectionMode.tun,
        errorMessage: 'Windows 后台服务返回了无效状态',
      );
    }

    final map = Map<String, dynamic>.from(value);
    final errorCode = map['error_code']?.toString();
    final latencyMap = <String, int>{};
    final rawLatency = map['latency_map'];
    if (rawLatency is Map) {
      for (final entry in rawLatency.entries) {
        final latency = _toInt(entry.value);
        if (latency != null) latencyMap[entry.key.toString()] = latency;
      }
    }

    return VpnState(
      status: _status(map['status']?.toString()),
      errorMessage: _optionalString(map['error_message']),
      failureReason: _failureReason(errorCode),
      connectionMode: VpnConnectionMode.tun,
      upSpeed: _toInt(map['up_speed']) ?? 0,
      downSpeed: _toInt(map['down_speed']) ?? 0,
      totalUp: _toInt(map['total_up']) ?? 0,
      totalDown: _toInt(map['total_down']) ?? 0,
      latencyMap: latencyMap.isEmpty ? null : latencyMap,
      runtimeDetails: map,
    );
  }

  static void validateConfig(String config) {
    if (config.trim().isEmpty) {
      throw const FormatException('Windows TUN 配置不能为空');
    }
    if (utf8.encode(config).length > maxConfigBytes) {
      throw const FormatException('Windows TUN 配置超过 4 MiB 限制');
    }
  }

  static VpnStatus _status(String? value) {
    switch (value) {
      case 'connecting':
        return VpnStatus.connecting;
      case 'core_starting':
        return VpnStatus.coreStarting;
      case 'connected':
        return VpnStatus.connected;
      case 'disconnecting':
        return VpnStatus.disconnecting;
      case 'restore_failed':
        return VpnStatus.restoreFailed;
      case 'error':
        return VpnStatus.error;
      case 'idle':
      case 'disconnected':
      default:
        return VpnStatus.disconnected;
    }
  }

  static VpnFailureReason? _failureReason(String? code) {
    switch (code) {
      case null:
      case '':
        return null;
      case 'permission_denied':
        return VpnFailureReason.permissionDenied;
      case 'config_invalid':
        return VpnFailureReason.invalidConfig;
      case 'binary_missing':
        return VpnFailureReason.missingBinary;
      case 'tun_conflict':
        return VpnFailureReason.routeConflict;
      case 'core_start_failed':
      case 'service_unavailable':
        return VpnFailureReason.coreStartFailed;
      case 'restore_failed':
        return VpnFailureReason.restoreFailed;
      default:
        return VpnFailureReason.unknown;
    }
  }

  static int? _toInt(Object? value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  static String? _optionalString(Object? value) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? null : text;
  }
}
