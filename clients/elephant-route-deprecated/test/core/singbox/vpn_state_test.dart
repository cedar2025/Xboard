import 'package:elephant_network/core/singbox/vpn_state.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('VpnState', () {
    test('processing states are reported correctly', () {
      expect(const VpnState(status: VpnStatus.connecting).isProcessing, isTrue);
      expect(
          const VpnState(status: VpnStatus.coreStarting).isProcessing, isTrue);
      expect(
          const VpnState(status: VpnStatus.applyingProxy).isProcessing, isTrue);
      expect(
          const VpnState(status: VpnStatus.disconnecting).isProcessing, isTrue);
      expect(const VpnState(status: VpnStatus.connected).isProcessing, isFalse);
    });

    test('copyWith can clear error and failure state explicitly', () {
      final failed = const VpnState(
        status: VpnStatus.error,
        errorMessage: 'boom',
        failureReason: VpnFailureReason.healthCheckFailed,
      );

      final recovered = failed.copyWith(
        status: VpnStatus.connected,
        resetErrorMessage: true,
        resetFailureReason: true,
        connectionMode: VpnConnectionMode.proxy,
      );

      expect(recovered.status, VpnStatus.connected);
      expect(recovered.errorMessage, isNull);
      expect(recovered.failureReason, isNull);
      expect(recovered.connectionMode, VpnConnectionMode.proxy);
    });
  });
}
