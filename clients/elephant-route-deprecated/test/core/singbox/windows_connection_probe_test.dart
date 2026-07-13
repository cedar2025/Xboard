import 'package:elephant_network/core/singbox/windows_connection_probe.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('uses one curl process with the same URL twice for connection reuse',
      () {
    final arguments = WindowsConnectionProbe.buildArguments(
      proxyPort: 31001,
      testUrl: 'https://www.gstatic.com/generate_204',
      timeout: const Duration(seconds: 5),
    );

    expect(
      arguments
          .where((value) => value == 'https://www.gstatic.com/generate_204'),
      hasLength(2),
    );
    expect(arguments, containsAllInOrder(['--output', 'NUL']));
    expect(
      arguments,
      containsAllInOrder(['--proxy', 'http://127.0.0.1:31001']),
    );
    expect(arguments, containsAllInOrder(['--max-time', '5.000']));
  });

  test('parses two reused-connection timings and selects the lower value', () {
    final result = WindowsConnectionProbe.parseOutput(
      '204 1.141234\n204 0.261432\n',
      elapsedMs: 1427,
    );

    expect(result.attempts, [1141, 261]);
    expect(result.latencyMs, 261);
    expect(result.elapsedMs, 1427);
  });

  test('ignores a failed response when the other request is valid', () {
    final result = WindowsConnectionProbe.parseOutput(
      '503 0.800000\n204 0.250000\n',
      elapsedMs: 1100,
    );

    expect(result.attempts, [-1, 250]);
    expect(result.latencyMs, 250);
  });

  test('keeps a completed first response when the second request times out',
      () {
    final result = WindowsConnectionProbe.parseOutput(
      '204 1.100000\n',
      elapsedMs: 5000,
    );

    expect(result.attempts, [1100]);
    expect(result.latencyMs, 1100);
  });

  test('returns timeout for empty or malformed output', () {
    final result = WindowsConnectionProbe.parseOutput(
      'curl failed',
      elapsedMs: 5000,
    );

    expect(result.attempts, isEmpty);
    expect(result.latencyMs, -1);
  });
}
