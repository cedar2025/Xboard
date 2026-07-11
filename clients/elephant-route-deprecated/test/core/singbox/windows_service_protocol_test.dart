import 'package:elephant_network/core/singbox/vpn_state.dart';
import 'package:elephant_network/core/singbox/windows_service_protocol.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('WindowsServiceProtocol', () {
    test('parses connected TUN state and traffic', () {
      final state = WindowsServiceProtocol.parseState({
        'status': 'connected',
        'up_speed': 120,
        'down_speed': 340,
        'total_up': 1000,
        'total_down': 2000,
        'latency_map': {'Tokyo': 38, 'Los Angeles': '142'},
      });

      expect(state.status, VpnStatus.connected);
      expect(state.connectionMode, VpnConnectionMode.tun);
      expect(state.upSpeed, 120);
      expect(state.downSpeed, 340);
      expect(state.totalUp, 1000);
      expect(state.totalDown, 2000);
      expect(state.latencyMap, {'Tokyo': 38, 'Los Angeles': 142});
    });

    test('maps native error codes to stable failure reasons', () {
      final state = WindowsServiceProtocol.parseState({
        'status': 'error',
        'error_code': 'tun_conflict',
        'error_message': 'another TUN adapter owns the route',
      });

      expect(state.status, VpnStatus.error);
      expect(state.failureReason, VpnFailureReason.routeConflict);
      expect(state.errorMessage, contains('TUN'));
    });

    test('rejects empty and oversized configs', () {
      expect(
        () => WindowsServiceProtocol.validateConfig('  '),
        throwsFormatException,
      );
      expect(
        () => WindowsServiceProtocol.validateConfig(
          'x' * (WindowsServiceProtocol.maxConfigBytes + 1),
        ),
        throwsFormatException,
      );
    });

    test('turns malformed service output into an error state', () {
      final state = WindowsServiceProtocol.parseState('not-a-map');

      expect(state.status, VpnStatus.error);
      expect(state.failureReason, VpnFailureReason.unknown);
    });
  });
}
