import 'dart:async';

import 'package:elephant_network/core/singbox/latency_test_policy.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('LatencyTestPolicy', () {
    test('tests up to four nodes concurrently', () {
      expect(LatencyTestPolicy.concurrency, 4);
    });

    test('uses built-in 204 probe URLs before user configured URL', () {
      final urls = LatencyTestPolicy.probeUrls(
        configuredTestUrl: 'https://example.com/custom_204',
      );

      expect(urls, [
        'https://www.gstatic.com/generate_204',
        'http://cp.cloudflare.com/generate_204',
        'https://example.com/custom_204',
      ]);
    });

    test('deduplicates configured URL when it matches a built-in URL', () {
      final urls = LatencyTestPolicy.probeUrls(
        configuredTestUrl: ' http://cp.cloudflare.com/generate_204 ',
      );

      expect(urls, [
        'https://www.gstatic.com/generate_204',
        'http://cp.cloudflare.com/generate_204',
      ]);
    });

    test('selects minimum valid latency from multiple probes', () async {
      final tester = LatencyTester(
        probeUrls: const ['first', 'second', 'third'],
        timeoutMs: 3500,
        probe: (url, timeoutMs) async {
          return switch (url) {
            'first' => -1,
            'second' => 180,
            'third' => 92,
            _ => -1,
          };
        },
      );

      expect(await tester.test(), 92);
    });

    test('starts all probes for a node concurrently', () async {
      var started = 0;
      final allStarted = Completer<void>();

      final tester = LatencyTester(
        probeUrls: const ['first', 'second', 'third'],
        timeoutMs: 3500,
        probe: (url, timeoutMs) async {
          started++;
          if (started == 3) {
            allStarted.complete();
          }

          await allStarted.future.timeout(const Duration(milliseconds: 100));
          return switch (url) {
            'first' => 140,
            'second' => 95,
            'third' => 180,
            _ => -1,
          };
        },
      );

      expect(await tester.test(), 95);
    });

    test('returns timeout when all probes fail', () async {
      final tester = LatencyTester(
        probeUrls: const ['first', 'second'],
        timeoutMs: 3500,
        probe: (url, timeoutMs) async => -1,
      );

      expect(await tester.test(), -1);
    });

    test('requires connected VPN for real Android Windows and macOS tests', () {
      expect(
        LatencyTestPolicy.requiresConnectedVpn(
          isWeb: false,
          isAndroid: true,
          isWindows: false,
          isMacOS: false,
          isMockVpn: false,
        ),
        isTrue,
      );
      expect(
        LatencyTestPolicy.requiresConnectedVpn(
          isWeb: false,
          isAndroid: true,
          isWindows: false,
          isMacOS: false,
          isMockVpn: true,
        ),
        isFalse,
      );
      expect(
        LatencyTestPolicy.requiresConnectedVpn(
          isWeb: false,
          isAndroid: false,
          isWindows: true,
          isMacOS: false,
          isMockVpn: false,
        ),
        isTrue,
      );
      expect(
        LatencyTestPolicy.requiresConnectedVpn(
          isWeb: false,
          isAndroid: false,
          isWindows: false,
          isMacOS: true,
          isMockVpn: false,
        ),
        isTrue,
      );
      expect(
        LatencyTestPolicy.requiresConnectedVpn(
          isWeb: false,
          isAndroid: false,
          isWindows: false,
          isMacOS: false,
          isMockVpn: false,
        ),
        isFalse,
      );
    });
  });
}
