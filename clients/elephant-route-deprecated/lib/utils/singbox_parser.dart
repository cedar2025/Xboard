import 'package:flutter/foundation.dart';
import '../../models/proxy_node.dart';

class SingboxConfigParser {
  /// 将 Xboard/Sing-box 订阅 JSON 解析为原生 ProxyNode 列表
  static List<ProxyNode> parseNodes(Map<String, dynamic> config) {
    try {
      final List<ProxyNode> nodes = [];

      // Sing-box 配置通常在 outbounds 字段中
      final outbounds = config['outbounds'] as List<dynamic>?;
      if (outbounds == null) return [];

      for (var outbound in outbounds) {
        try {
          final type = outbound['type'] as String?;
          final tag = outbound['tag'] as String?;

          debugPrint('DEBUG Parser: Processing outbound tag=$tag, type=$type');

          // 过滤掉非代理类型的 outbound (比如 direct, block, dns 等)
          if (tag == null || _isInternalOutbound(type)) {
            debugPrint('DEBUG Parser: Skipping internal outbound');
            continue;
          }

          // 提取服务器信息
          final server = outbound['server'] as String? ?? '';
          final port = outbound['server_port'] as int? ?? 0;

          debugPrint('DEBUG Parser: tls type = ${outbound['tls'].runtimeType}');
          debugPrint(
              'DEBUG Parser: transport type = ${outbound['transport'].runtimeType}');

          nodes.add(ProxyNode(
            name: tag,
            type: type ?? 'unknown',
            server: server,
            port: port,
            uuid: outbound['uuid'],
            security: outbound['security'],
            network: outbound['network'],
            tlsOptions: outbound['tls'],
            wsOptions: outbound['transport'],
          ));

          debugPrint('DEBUG Parser: Successfully added node $tag');
        } catch (e) {
          debugPrint('DEBUG Parser: Error processing outbound: $e');
          debugPrint('DEBUG Parser: Outbound data: $outbound');
        }
      }

      return nodes;
    } catch (e) {
      debugPrint('解析 Sing-box 配置失败: $e');
      return [];
    }
  }

  static bool _isInternalOutbound(String? type) {
    const internalTypes = ['direct', 'block', 'dns', 'selector', 'urltest'];
    return internalTypes.contains(type);
  }
}
