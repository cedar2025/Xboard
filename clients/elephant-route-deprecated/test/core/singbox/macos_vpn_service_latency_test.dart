import 'package:elephant_network/core/singbox/connection_latency_manager.dart';
import 'package:elephant_network/core/singbox/macos_vpn_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('macOS VPN service exposes isolated connection latency capability', () {
    final service = MacosVpnService();

    expect(service, isA<ConnectionLatencyManager>());

    service.dispose();
  });

  test('stopping an idle latency session is idempotent', () async {
    final service = MacosVpnService();

    await service.stopConnectionLatencyTest();
    await service.stopSpeedTest();

    service.dispose();
  });
}
