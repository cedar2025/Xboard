class ProxyNode {
  final String name;
  final String type;
  final String server;
  final int port;
  final String? uuid;
  final String? alterId;
  final String? security;
  final String? network;
  final dynamic tlsOptions;  // 可能是 Map 或其他类型
  final dynamic wsOptions;   // 可能是 Map、List 或其他类型
  final int? latency; // 毫秒

  ProxyNode({
    required this.name,
    required this.type,
    required this.server,
    required this.port,
    this.uuid,
    this.alterId,
    this.security,
    this.network,
    this.tlsOptions,
    this.wsOptions,
    this.latency,
  });

  factory ProxyNode.fromJson(Map<String, dynamic> json) {
    return ProxyNode(
      name: json['name'] ?? '',
      type: json['type'] ?? '',
      server: json['server'] ?? '',
      port: json['port'] ?? 0,
      uuid: json['uuid'],
      alterId: json['alter_id']?.toString(),
      security: json['security'],
      network: json['network'],
      tlsOptions: json['tls_options'],
      wsOptions: json['ws_options'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'name': name,
      'type': type,
      'server': server,
      'port': port,
      'uuid': uuid,
      'alter_id': alterId,
      'security': security,
      'network': network,
      'tls_options': tlsOptions,
      'ws_options': wsOptions,
    };
  }

  // 复制并更新延迟
  ProxyNode copyWithLatency(int? newLatency) {
    return ProxyNode(
      name: name,
      type: type,
      server: server,
      port: port,
      uuid: uuid,
      alterId: alterId,
      security: security,
      network: network,
      tlsOptions: tlsOptions,
      wsOptions: wsOptions,
      latency: newLatency,
    );
  }
}
