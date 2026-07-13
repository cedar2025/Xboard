import 'dart:convert';

import 'package:elephant_network/core/singbox/windows_latency_config.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('builds isolated worker config without changing the active selector',
      () {
    final source = <String, dynamic>{
      'inbounds': [
        {'type': 'tun', 'tag': 'tun-in'},
      ],
      'outbounds': [
        {'type': 'anytls', 'tag': 'Tokyo', 'server': 'example.com'},
        {'type': 'direct', 'tag': 'direct'},
        {
          'type': 'selector',
          'tag': '节点选择',
          'outbounds': ['Tokyo'],
          'default': 'Tokyo',
        },
      ],
      'dns': {
        'servers': [
          {'tag': 'remote', 'address': '8.8.8.8', 'detour': '节点选择'},
        ],
      },
      'route': {
        'final': '节点选择',
        'rules': [
          {'outbound': '节点选择'},
        ],
      },
      'experimental': {
        'cache_file': {'enabled': true, 'path': r'C:\cache.db'},
        'clash_api': {'external_controller': '127.0.0.1:9090'},
      },
    };

    final result = WindowsLatencyConfigBuilder.build(
      sourceConfig: jsonEncode(source),
      nodeTags: const ['Tokyo'],
      workerPorts: const [31001, 31002],
      clashApiPort: 31999,
    );

    final inbounds = result['inbounds'] as List;
    expect(inbounds, hasLength(2));
    expect(inbounds.every((item) => item['type'] == 'mixed'), isTrue);
    expect(inbounds.any((item) => item['type'] == 'tun'), isFalse);

    final outbounds = result['outbounds'] as List;
    expect(outbounds.where((item) => item['type'] == 'selector'), hasLength(2));
    expect(outbounds.any((item) => item['tag'] == '节点选择'), isFalse);
    expect(outbounds.any((item) => item['type'] == 'anytls'), isTrue);

    final route = result['route'] as Map;
    expect(route['final'], 'direct');
    expect((route['rules'] as List), hasLength(2));
    expect(source['route'], {
      'final': '节点选择',
      'rules': [
        {'outbound': '节点选择'},
      ],
    });

    final experimental = result['experimental'] as Map;
    expect(experimental.containsKey('cache_file'), isFalse);
    expect(
      (experimental['clash_api'] as Map)['external_controller'],
      '127.0.0.1:31999',
    );
    expect(
      ((result['dns'] as Map)['servers'] as List).single['detour'],
      'direct',
    );
  });
}
