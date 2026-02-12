import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'dart:convert';
import 'dart:async'; // [NEW]
import '../core/api/dio_client.dart';
import '../core/api/services/user_service.dart';
import '../models/proxy_node.dart';
import '../utils/singbox_parser.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../core/singbox/vpn_manager.dart';
import '../core/singbox/vpn_state.dart';

class NodeProvider with ChangeNotifier {
  final UserService _userService;
  final VpnManager _vpnManager;
  StreamSubscription? _vpnStateSubscription;

  // Restore deleted fields
  final _storage = const FlutterSecureStorage();
  List<ProxyNode> _nodes = [];
  ProxyNode? _selectedNode;
  bool _isLoading = false;
  String? _errorMessage;

  NodeProvider(DioClient dioClient, this._vpnManager) 
      : _userService = UserService(dioClient) {
    // Listen to VPN state for latency updates
    _vpnStateSubscription = _vpnManager.stateStream.listen((state) {
      if (state.latencyMap != null && state.latencyMap!.isNotEmpty) {
        _handleLatencyUpdate(state.latencyMap!);
      }
    });
  }

  // ... (existing getters/fetchNodes/selectNode)

  /// Handle latency updates from VPN service
  void _handleLatencyUpdate(Map<String, int> latencyMap) {
    bool changed = false;
    _nodes = _nodes.map((node) {
      if (latencyMap.containsKey(node.name)) {
        final newLatency = latencyMap[node.name];
        if (node.latency != newLatency) {
          changed = true;
          return node.copyWithLatency(newLatency);
        }
      }
      return node;
    }).toList();

    if (changed) {
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _vpnStateSubscription?.cancel();
    super.dispose();
  }

  /// 测试所有节点延迟
  Future<void> testAllLatencies(BuildContext context) async {
    if (_nodes.isEmpty) return;
    
    // Check if VPN is connected
    if (_vpnManager.currentState.status != VpnStatus.connected) {
       ScaffoldMessenger.of(context).showSnackBar(
         const SnackBar(content: Text('请先连接 VPN 再进行测速')),
       );
       return;
    }
    
    ScaffoldMessenger.of(context).showSnackBar(
       const SnackBar(
         content: Text('正在开始测速...'), 
         duration: Duration(seconds: 1)
       ),
    );

    // Trigger "proxy" group test in Sing-box
    // This will test all nodes in the proxy group (which contains all nodes)
    // Results will be broadcasted back via stateStream -> _handleLatencyUpdate
    await _vpnManager.urlTest("proxy");
    
    // Note: We don't await the results here as they are async.
    // The UI will update automatically when the stream emits.
  }


  List<ProxyNode> get nodes => _nodes;
  ProxyNode? get selectedNode => _selectedNode;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// 获取节点列表
  Future<void> fetchNodes() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      // 加载本地保存的选中节点 (如果有)
      if (_selectedNode == null) {
          final savedNodeJson = await _storage.read(key: 'selected_node');
          if (savedNodeJson != null) {
              _selectedNode = ProxyNode.fromJson(jsonDecode(savedNodeJson));
          }
      }

      // 1. 获取订阅信息(包含 subscribe_url)
      dynamic subscribeInfo;
      try {
        subscribeInfo = await _userService.getSubscribe();
      } catch (e) {
        print('DEBUG: getSubscribe failed: $e');
        // 如果获取订阅报错（可能是404或500），视作无订阅
        _errorMessage = '未找到订阅链接，请先购买套餐';
        _isLoading = false;
        notifyListeners();
        return;
      }

      print('DEBUG: subscribeInfo = $subscribeInfo');
      
      if (subscribeInfo == null) {
        _errorMessage = '未找到订阅链接，请先购买套餐';
        _isLoading = false;
        notifyListeners();
        return;
      }

      final subscribeUrl = subscribeInfo['subscribe_url'] as String?;
      print('DEBUG: subscribeUrl = $subscribeUrl');
      
      if (subscribeUrl == null || subscribeUrl.isEmpty) {
        _errorMessage = '未找到订阅链接，请先购买套餐';
        _isLoading = false;
        notifyListeners();
        return;
      }

      // 2. 从 subscribe_url 中提取 token 参数
      // URL 格式: http://domain/s/{token}
      final uri = Uri.parse(subscribeUrl);
      final pathSegments = uri.pathSegments;
      
      print('DEBUG: parsed uri = $uri');
      print('DEBUG: pathSegments = $pathSegments');
      
      // 提取最后一个路径段作为 token
      final token = pathSegments.isNotEmpty ? pathSegments.last : null;
      
      print('DEBUG: token = $token');
      
      if (token == null || token.isEmpty) {
        _errorMessage = '订阅链接格式错误: $subscribeUrl';
        _isLoading = false;
        notifyListeners();
        return;
      }

      // 3. 调用 Xboard 订阅接口获取 Sing-box 配置
      // 注意: 这里指定 User-Agent 以获取正确的格式
      dynamic config;
      try {
        config = await _userService.getSubscriptionConfig(token);
      } catch (e) {
        print('DEBUG: getSubscriptionConfig failed: $e');
        _errorMessage = '订阅配置获取失败，请检查您的订阅状态';
        _isLoading = false;
        notifyListeners();
        return;
      }
      
      print('DEBUG: config type = ${config.runtimeType}');
      print('DEBUG: config = $config');
      
      // 4. 解析配置中的节点
      final List<ProxyNode> fetchedNodes = SingboxConfigParser.parseNodes(config);
      
      // 5. 合并延迟数据：如果新获取的节点在旧列表中已存在测速结果，则保留旧值
      final Map<String, int?> oldLatencies = {
        for (var node in _nodes) 
          if (node.latency != null) node.name: node.latency
      };

      _nodes = fetchedNodes.map((node) {
        if (oldLatencies.containsKey(node.name)) {
          return node.copyWithLatency(oldLatencies[node.name]);
        }
        return node;
      }).toList();

      print('DEBUG: parsed ${_nodes.length} nodes');

      if (_nodes.isNotEmpty && _selectedNode == null) {
        _selectedNode = _nodes.first;
      }
      
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      print('DEBUG: Error in fetchNodes: $e');
      _errorMessage = '获取节点列表失败: ${e.toString()}';
      _isLoading = false;
      notifyListeners();
    }
  }

  /// 选择节点
  void selectNode(ProxyNode node) {
    _selectedNode = node;
    notifyListeners();
    
    // 保存到存储以便持久化和跨 Provider 访问
    _storage.write(key: 'selected_node', value: jsonEncode(node.toJson()));
    
    // 通知 VPN 切换节点 (热切换)
    // 注意: 组名目前写死为 "proxy", 实际应根据 Xboard 的配置动态获取
    _vpnManager.selectOutbound("proxy", node.name);
  }


}
