import 'package:elephant_network/core/singbox/connection_latency_manager.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('connection latency result exposes latency and elapsed time', () {
    const result = ConnectionLatencyResult(
      latencyMs: 253,
      elapsedMs: 1164,
      attempts: [1163, 253],
    );

    expect(result.latencyMs, 253);
    expect(result.elapsedMs, 1164);
    expect(result.attempts, [1163, 253]);
  });
}
