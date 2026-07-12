import 'dart:async';

import 'package:elephant_network/core/singbox/vpn_stop_coordinator.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('coalesces concurrent runs and resets after completion', () async {
    final coordinator = VpnStopCoordinator<int>();
    final first = Completer<int>();
    var calls = 0;

    Future<int> task() {
      calls++;
      return first.future;
    }

    final a = coordinator.run(task);
    final b = coordinator.run(task);
    expect(coordinator.hasInFlight, isTrue);
    expect(calls, 1);

    first.complete(7);
    expect(await Future.wait([a, b]), [7, 7]);
    await Future<void>.delayed(Duration.zero);
    expect(coordinator.hasInFlight, isFalse);

    expect(
        await coordinator.run(() async {
          calls++;
          return 8;
        }),
        8);
    expect(calls, 2);
  });
}
