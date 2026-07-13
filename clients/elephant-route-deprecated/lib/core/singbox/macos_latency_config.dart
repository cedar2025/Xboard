import 'dart:convert';

class MacosLatencyConfigBuilder {
  const MacosLatencyConfigBuilder._();

  static const String inboundPrefix = '__elephant_latency_in_';
  static const String workerPrefix = '__elephant_latency_worker_';

  static Map<String, dynamic> build({
    required String sourceConfig,
    required List<String> nodeTags,
    required List<int> workerPorts,
    required int clashApiPort,
  }) {
    if (nodeTags.isEmpty) {
      throw ArgumentError.value(nodeTags, 'nodeTags', 'must not be empty');
    }
    if (workerPorts.isEmpty) {
      throw ArgumentError.value(
        workerPorts,
        'workerPorts',
        'must not be empty',
      );
    }

    final decoded = jsonDecode(sourceConfig);
    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('sing-box config must be a JSON object');
    }
    final config = decoded;
    config.remove('use_tun_mode');
    config['log'] = <String, dynamic>{
      'level': 'warn',
      'timestamp': true,
    };

    final rawOutbounds = config['outbounds'];
    if (rawOutbounds is! List<dynamic>) {
      throw const FormatException('sing-box config must contain outbounds');
    }
    final outbounds = rawOutbounds
        .whereType<Map<String, dynamic>>()
        .where((outbound) {
          final tag = outbound['tag']?.toString();
          final type = outbound['type']?.toString();
          return !(tag?.startsWith(workerPrefix) ?? false) &&
              type != 'selector' &&
              type != 'urltest';
        })
        .map((outbound) => Map<String, dynamic>.from(outbound))
        .toList();

    final byTag = <String, Map<String, dynamic>>{
      for (final outbound in outbounds)
        if (outbound['tag'] is String) outbound['tag'] as String: outbound,
    };
    for (final nodeTag in nodeTags) {
      final outbound = byTag[nodeTag];
      final type = outbound?['type']?.toString();
      if (outbound == null || type == 'selector' || type == 'urltest') {
        throw ArgumentError.value(
          nodeTag,
          'nodeTags',
          'must reference a concrete outbound',
        );
      }
    }

    final dns = config['dns'];
    if (dns is Map<String, dynamic> && dns['servers'] is List<dynamic>) {
      for (final server in (dns['servers'] as List<dynamic>)
          .whereType<Map<String, dynamic>>()) {
        final detour = server['detour'];
        if (detour is String && !byTag.containsKey(detour)) {
          if (byTag['direct']?['type'] == 'direct') {
            server['detour'] = 'direct';
          } else {
            server.remove('detour');
          }
        }
      }
    }

    for (var index = 0; index < workerPorts.length; index++) {
      outbounds.add(<String, dynamic>{
        'type': 'selector',
        'tag': '$workerPrefix$index',
        'outbounds': List<String>.from(nodeTags),
        'default': nodeTags.first,
      });
    }
    config['outbounds'] = outbounds;

    config['inbounds'] = <Map<String, dynamic>>[
      for (var index = 0; index < workerPorts.length; index++)
        <String, dynamic>{
          'type': 'mixed',
          'tag': '$inboundPrefix$index',
          'listen': '127.0.0.1',
          'listen_port': workerPorts[index],
        },
    ];

    final sourceRoute = config['route'];
    final route = sourceRoute is Map<String, dynamic>
        ? Map<String, dynamic>.from(sourceRoute)
        : <String, dynamic>{};
    route['rules'] = <Map<String, dynamic>>[
      for (var index = 0; index < workerPorts.length; index++)
        <String, dynamic>{
          'inbound': <String>['$inboundPrefix$index'],
          'outbound': '$workerPrefix$index',
        },
    ];
    if (byTag['direct']?['type'] == 'direct') {
      route['final'] = 'direct';
    } else {
      route.remove('final');
    }
    config['route'] = route;

    final sourceExperimental = config['experimental'];
    final experimental = sourceExperimental is Map<String, dynamic>
        ? Map<String, dynamic>.from(sourceExperimental)
        : <String, dynamic>{};
    experimental.remove('cache_file');
    experimental['clash_api'] = <String, dynamic>{
      'external_controller': '127.0.0.1:$clashApiPort',
      'default_mode': 'rule',
    };
    config['experimental'] = experimental;

    return config;
  }
}
