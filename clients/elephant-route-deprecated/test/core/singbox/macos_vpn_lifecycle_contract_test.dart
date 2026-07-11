import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('macOS disposal releases Dart resources without stopping the tunnel',
      () {
    final source =
        File('lib/core/singbox/macos_vpn_service.dart').readAsStringSync();
    final disposeBody = RegExp(
      r'void dispose\(\) \{([\s\S]*?)\n  \}',
    ).firstMatch(source)!.group(1)!;

    expect(disposeBody, isNot(contains('stop()')));
    expect(disposeBody, contains('_stateController.close()'));
  });

  test('all macOS stop paths carry an explicit source', () {
    final runtime =
        File('lib/core/services/mac_runtime_service.dart').readAsStringSync();
    final macos =
        File('lib/core/singbox/macos_vpn_service.dart').readAsStringSync();
    final tray = File('lib/widgets/tray_controller.dart').readAsStringSync();
    final update = File('lib/core/api/services/app_update_service.dart')
        .readAsStringSync();

    expect(runtime, contains("{'reason': reason}"));
    expect(macos, contains('VpnStopReason.nodeSwitch'));
    expect(tray, contains('VpnStopReason.trayExit'));
    expect(update, contains("'reason': 'update_install'"));
  });
}
