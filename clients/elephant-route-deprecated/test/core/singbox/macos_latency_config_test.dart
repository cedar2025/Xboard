import 'dart:convert';

import 'package:elephant_network/core/singbox/macos_latency_config.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const source = {
    'use_tun_mode': true,
    'log': {'level': 'info'},
    'dns': {
      'servers': [
        {
          'tag': 'dns-direct',
          'address': '1.1.1.1',
          'detour': '节点选择',
        }
      ]
    },
    'inbounds': [
      {'type': 'tun', 'tag': 'tun-in', 'auto_route': true},
      {
        'type': 'mixed',
        'tag': 'mixed-in',
        'listen': '127.0.0.1',
        'listen_port': 2334,
      },
    ],
    'outbounds': [
      {
        'type': 'vless',
        'tag': 'node-a',
        'server': 'node-a.invalid',
        'server_port': 443,
        'uuid': 'redacted-a',
      },
      {
        'type': 'anytls',
        'tag': 'node-b',
        'server': 'node-b.invalid',
        'server_port': 443,
        'password': 'redacted-b',
      },
      {'type': 'direct', 'tag': 'direct'},
      {
        'type': 'selector',
        'tag': '节点选择',
        'outbounds': ['node-a', 'node-b'],
        'default': 'node-a',
      },
    ],
    'route': {
      'rules': [
        {'protocol': 'dns', 'outbound': 'direct'}
      ],
      'final': '节点选择',
    },
    'experimental': {
      'clash_api': {'external_controller': '127.0.0.1:9090'},
      'cache_file': {'enabled': true, 'path': '/tmp/cache.db'},
    },
  };

  test('builds isolated loopback workers without TUN or shared cache', () {
    final sourceText = jsonEncode(source);
    final output = MacosLatencyConfigBuilder.build(
      sourceConfig: sourceText,
      nodeTags: const ['node-a', 'node-b'],
      workerPorts: const [31001, 31002],
      clashApiPort: 31003,
    );

    expect(output.containsKey('use_tun_mode'), isFalse);
    expect(output['inbounds'], [
      {
        'type': 'mixed',
        'tag': '__elephant_latency_in_0',
        'listen': '127.0.0.1',
        'listen_port': 31001,
      },
      {
        'type': 'mixed',
        'tag': '__elephant_latency_in_1',
        'listen': '127.0.0.1',
        'listen_port': 31002,
      },
    ]);
    expect(
      output['experimental']['clash_api']['external_controller'],
      '127.0.0.1:31003',
    );
    expect(output['experimental'].containsKey('cache_file'), isFalse);
    expect(output['route']['rules'], [
      {
        'inbound': ['__elephant_latency_in_0'],
        'outbound': '__elephant_latency_worker_0',
      },
      {
        'inbound': ['__elephant_latency_in_1'],
        'outbound': '__elephant_latency_worker_1',
      },
    ]);
    expect(output['route']['final'], 'direct');
    expect(output['dns']['servers'][0]['detour'], 'direct');
    expect(
      (output['outbounds'] as List<dynamic>).any(
        (item) => (item as Map<String, dynamic>)['tag'] == '节点选择',
      ),
      isFalse,
    );
    expect(jsonDecode(sourceText), source);
  });

  test('appends one private selector per worker with concrete nodes only', () {
    final output = MacosLatencyConfigBuilder.build(
      sourceConfig: jsonEncode(source),
      nodeTags: const ['node-a', 'node-b'],
      workerPorts: const [31001, 31002, 31003, 31004],
      clashApiPort: 31005,
    );

    final selectors = (output['outbounds'] as List<dynamic>)
        .where((item) =>
            (item as Map<String, dynamic>)['tag']
                ?.toString()
                .startsWith('__elephant_latency_worker_') ==
            true)
        .cast<Map<String, dynamic>>()
        .toList();
    expect(selectors, hasLength(4));
    for (final selector in selectors) {
      expect(selector['type'], 'selector');
      expect(selector['outbounds'], ['node-a', 'node-b']);
      expect(selector['default'], 'node-a');
    }
  });

  test('rejects unknown and non-concrete node tags', () {
    expect(
      () => MacosLatencyConfigBuilder.build(
        sourceConfig: jsonEncode(source),
        nodeTags: const ['missing'],
        workerPorts: const [31001],
        clashApiPort: 31002,
      ),
      throwsArgumentError,
    );
    expect(
      () => MacosLatencyConfigBuilder.build(
        sourceConfig: jsonEncode(source),
        nodeTags: const ['节点选择'],
        workerPorts: const [31001],
        clashApiPort: 31002,
      ),
      throwsArgumentError,
    );
  });
}
