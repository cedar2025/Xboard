import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'dart:async';
import 'dart:io';
import 'dart:math';
import '../core/api/dio_client.dart';
import '../core/api/services/user_service.dart';
import '../models/proxy_node.dart';
import '../utils/singbox_parser.dart';
import '../core/storage/local_storage.dart';
import '../core/singbox/connection_latency_manager.dart';
import '../core/singbox/latency_test_policy.dart';
import '../core/singbox/vpn_manager.dart';
import '../core/singbox/mock_vpn_service.dart';
import '../core/singbox/vpn_state.dart';
import '../utils/toast_utils.dart';
import 'config_provider.dart';

/// 自动选择节点的固定名称
const String kAutoSelectNodeName = '自动选择节点';

class NodeProvider with ChangeNotifier {
  final UserService _userService;
  final VpnManager _vpnManager;
  final ConfigProvider _configProvider;
  StreamSubscription? _vpnStateSubscription;
  VpnStatus _lastVpnStatus = VpnStatus.disconnected;

  final _storage = const LocalStorage();
  List<ProxyNode> _nodes = [];
  ProxyNode? _selectedNode;
  bool _isLoading = false;
  bool _isTestingLatency = false;
  DateTime? _ignoreNativeLatencyUpdatesUntil;
  String? _errorMessage;

  // 自动选择模式相关
  bool _isAutoMode = true;
  ProxyNode? _autoSelectedRealNode; // 自动模式下实际选中的真实节点

  NodeProvider(DioClient dioClient, this._vpnManager, this._configProvider)
      : _userService = UserService(dioClient) {
    // 监听 VPN 状态变化获取延迟更新
    _vpnStateSubscription = _vpnManager.stateStream.listen((state) {
      if (state.latencyMap != null && state.latencyMap!.isNotEmpty) {
        _handleLatencyUpdate(state.latencyMap!);
      }

      debugPrint(
          'DEBUG NodeProvider state: new=${state.status}, old=$_lastVpnStatus, nodes=${_nodes.length}');
      // VPN 连接成功后自动触发一次完整的测速
      if (_lastVpnStatus != VpnStatus.connected &&
          state.status == VpnStatus.connected) {
        debugPrint(
            'DEBUG NodeProvider: VPN 连接成功，延迟 2 秒后触发测速，nodes count: ${_nodes.length}');
        Future.delayed(const Duration(seconds: 2), () {
          debugPrint(
              'DEBUG NodeProvider: 开始执行 testAllLatencies (当前状态=${_vpnManager.currentState.status})');
          if (_vpnManager.currentState.status == VpnStatus.connected) {
            testAllLatencies();
          }
        });
      }
      _lastVpnStatus = state.status;
    });

    // 加载持久化的自动模式设置
    _loadAutoMode();
  }

  /// 从存储中加载自动模式设置
  Future<void> _loadAutoMode() async {
    final savedMode = await _storage.read(key: 'auto_mode');
    if (savedMode != null) {
      _isAutoMode = savedMode == 'true';
    }
  }

  // ==================== Getters ====================

  List<ProxyNode> get nodes => _nodes;
  ProxyNode? get selectedNode => _selectedNode;
  bool get isLoading => _isLoading || _isTestingLatency;
  String? get errorMessage => _errorMessage;
  bool get isAutoMode => _isAutoMode;
  ProxyNode? get autoSelectedRealNode => _autoSelectedRealNode;

  /// 获取真实节点列表（不含自动选择虚拟节点）
  List<ProxyNode> get realNodes =>
      _nodes.where((n) => n.type != 'auto').toList();

  /// 获取当前实际生效的节点名称（用于 UI 显示）
  String get effectiveNodeName {
    if (_isAutoMode && _autoSelectedRealNode != null) {
      return _autoSelectedRealNode!.name;
    }
    return _selectedNode?.name ?? '';
  }

  // ==================== 延迟处理 ====================

  /// 处理来自 VPN 内核的延迟数据更新
  void _handleLatencyUpdate(Map<String, int> latencyMap) {
    final ignoreUntil = _ignoreNativeLatencyUpdatesUntil;
    if (ignoreUntil != null && DateTime.now().isBefore(ignoreUntil)) {
      debugPrint(
          '[SPEED_TEST_DART] Ignore native latency update while HTTP delay test is authoritative');
      return;
    }

    bool changed = false;
    _nodes = _nodes.map((node) {
      if (node.type == 'auto') return node; // 跳过虚拟节点
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
      // 延迟更新后重新评估自动选择
      _evaluateAutoSelect();
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _vpnStateSubscription?.cancel();
    super.dispose();
  }

  final Dio _dio = Dio(BaseOptions(
    connectTimeout: const Duration(seconds: 5),
    receiveTimeout: const Duration(seconds: 5),
  ))
    ..httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        client.findProxy = (uri) => 'DIRECT';
        return client;
      },
    );

  /// 测试所有节点延迟
  Future<void> testAllLatencies([BuildContext? context]) async {
    if (_isTestingLatency) return;

    final useMacosConnectionSession =
        !kIsWeb && Platform.isMacOS && _vpnManager is ConnectionLatencyManager;
    final requiresConnectedVpn = LatencyTestPolicy.requiresConnectedVpn(
      isWeb: kIsWeb,
      isAndroid: !kIsWeb && Platform.isAndroid,
      isWindows: !kIsWeb && Platform.isWindows,
      isMacOS: !kIsWeb && Platform.isMacOS,
      isMockVpn: _vpnManager is MockVpnService,
    );

    if (requiresConnectedVpn &&
        _vpnManager.currentState.status != VpnStatus.connected) {
      debugPrint('[SPEED_TEST_DART] Latency test blocked: VPN not connected');
      if (context != null && context.mounted) {
        ToastUtils.show(context, '请先开启加速后再测速');
      }
      return;
    }

    _isTestingLatency = true;
    _ignoreNativeLatencyUpdatesUntil =
        DateTime.now().add(const Duration(seconds: 30));
    notifyListeners();

    try {
      if (_nodes.isEmpty) {
        debugPrint('DEBUG: testAllLatencies 触发时 nodes 为空，尝试自动 fetchNodes...');
        await fetchNodes();
        if (_nodes.isEmpty) {
          debugPrint('DEBUG: 自动 fetchNodes 后仍然为空，放弃测速。');
          return;
        }
      }

      if (requiresConnectedVpn &&
          !useMacosConnectionSession &&
          !await _waitForLocalClashApi()) {
        debugPrint(
            '[SPEED_TEST_DART] Android latency test blocked: Clash API not ready');
        if (context != null && context.mounted) {
          ToastUtils.show(context, '测速服务未就绪，请稍后重试');
        }
        return;
      }

      if (context != null && context.mounted) {
        ToastUtils.show(context, '正在开始测速...');
      }

      // 将所有真实节点的延迟清空，标记为正在测速
      _nodes = _nodes.map((node) {
        if (node.type == 'auto') return node;
        return node.copyWithLatency(null);
      }).toList();
      notifyListeners();

      final latencyProfile = !kIsWeb && Platform.isMacOS
          ? LatencyTestProfile.v2boxConnection
          : LatencyTestProfile.standard;
      final probeUrls = LatencyTestPolicy.probeUrls(
        configuredTestUrl: _configProvider.testUrl,
        profile: latencyProfile,
      );

      if (useMacosConnectionSession) {
        await _testMacosConnectionLatencies(
          realNodes,
          probeUrls.single,
          LatencyTestPolicy.timeoutMsFor(latencyProfile),
          LatencyTestPolicy.concurrency,
        );
      } else {
        await _testNodesConcurrently(
          realNodes,
          probeUrls,
          LatencyTestPolicy.timeoutMsFor(latencyProfile),
          LatencyTestPolicy.concurrency,
        );
      }

      // 测速完成后评估自动选择
      _evaluateAutoSelect();
    } catch (e) {
      debugPrint('[SPEED_TEST_DART] 测速失败: $e');
      if (context != null && context.mounted) {
        ToastUtils.show(context, '测速失败，请稍后重试');
      }
    } finally {
      _isTestingLatency = false;
      _ignoreNativeLatencyUpdatesUntil =
          DateTime.now().add(const Duration(seconds: 5));
      notifyListeners();
    }
  }

  Future<void> _testMacosConnectionLatencies(
    List<ProxyNode> nodes,
    String testUrl,
    int timeout,
    int concurrency,
  ) async {
    final manager = _vpnManager as ConnectionLatencyManager;
    try {
      await manager.testConnectionLatencies(
        nodeTags: nodes.map((node) => node.name).toList(growable: false),
        testUrl: testUrl,
        timeoutMs: timeout,
        concurrency: concurrency,
        onResult: _applyConnectionLatencyResult,
      );
    } catch (_) {
      for (final node in nodes) {
        _applyConnectionLatencyResult(
          node.name,
          const ConnectionLatencyResult(latencyMs: -1, elapsedMs: 0),
        );
      }
      rethrow;
    }
  }

  void _applyConnectionLatencyResult(
    String nodeTag,
    ConnectionLatencyResult result,
  ) {
    final index = _nodes.indexWhere((node) => node.name == nodeTag);
    if (index == -1) return;
    if (_nodes[index].latency != result.latencyMs) {
      _nodes[index] = _nodes[index].copyWithLatency(result.latencyMs);
      notifyListeners();
    }
  }

  Future<void> _testNodesConcurrently(
    List<ProxyNode> nodes,
    List<String> probeUrls,
    int timeout,
    int concurrency,
  ) async {
    if (nodes.isEmpty) return;

    var nextIndex = 0;
    final workerCount = min(concurrency, nodes.length);

    Future<void> worker() async {
      while (true) {
        final currentIndex = nextIndex;
        if (currentIndex >= nodes.length) return;
        nextIndex++;

        final node = nodes[currentIndex];
        final tester = LatencyTester(
          probeUrls: probeUrls,
          timeoutMs: timeout,
          probe: (probeUrl, timeoutMs) =>
              _testSingleNode(node, probeUrl, timeoutMs),
        );
        final latency = await tester.test();
        final index = _nodes.indexWhere((n) => n.name == node.name);
        if (index != -1) {
          _nodes[index] = _nodes[index].copyWithLatency(latency);
          notifyListeners();
        }
      }
    }

    await Future.wait(List.generate(workerCount, (_) => worker()));
  }

  Future<bool> _waitForLocalClashApi() async {
    final deadline = DateTime.now().add(const Duration(seconds: 8));
    while (DateTime.now().isBefore(deadline)) {
      if (await _isLocalClashApiReady()) return true;
      await Future.delayed(const Duration(milliseconds: 250));
    }
    return false;
  }

  Future<bool> _isLocalClashApiReady() async {
    try {
      final response = await _dio.get('http://127.0.0.1:9090/proxies');
      return response.statusCode == 200;
    } catch (_) {
      // Clash API is not listening yet.
      return false;
    }
  }

  Future<int> _testSingleNode(
      ProxyNode node, String testUrl, int timeout) async {
    // 若处于 MockVpn 模式，直接伪造一个合理的随机延迟
    if (_vpnManager is MockVpnService) {
      await Future.delayed(Duration(milliseconds: 100 + Random().nextInt(300)));
      // 随机产生 10% 的超时率，以及 [30, 430] 毫秒区间的可用延迟用于测试不同颜色
      if (Random().nextInt(100) < 10) return -1;
      return 30 + Random().nextInt(400);
    }

    try {
      final encodedName = Uri.encodeComponent(node.name);
      final url = 'http://127.0.0.1:9090/proxies/$encodedName/delay';

      final response = await _dio.get(
        url,
        queryParameters: {
          'url': testUrl,
          'timeout': timeout,
        },
      );

      if (response.statusCode == 200 && response.data != null) {
        final delay = response.data['delay'];
        if (delay != null && delay is int && delay > 0) {
          debugPrint(
              '[SPEED_TEST_DART] node=${node.name} url=$testUrl delay=${delay}ms');
          return delay;
        } else {
          debugPrint('[SPEED_TEST_DART] 节点 ${node.name} 返回无效延迟: $delay');
        }
      } else {
        debugPrint(
            '[SPEED_TEST_DART] 节点 ${node.name} 返回状态: ${response.statusCode}');
      }
      return -1;
    } on DioException catch (e) {
      debugPrint('[SPEED_TEST_DART] DioException: ${node.name}: ${e.message}');
      return -1;
    } catch (e) {
      debugPrint('[SPEED_TEST_DART] 未知错误: ${node.name}: $e');
      return -1;
    }
  }

  // ==================== 节点获取 ====================

  /// 获取节点列表
  Future<void> fetchNodes() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      // 加载本地保存的选中节点（如果有）
      if (_selectedNode == null) {
        final savedNodeJson = await _storage.read(key: 'selected_node');
        if (savedNodeJson != null) {
          final saved = ProxyNode.fromJson(jsonDecode(savedNodeJson));
          // 如果保存的是自动选择节点，恢复自动模式
          if (saved.type == 'auto') {
            _isAutoMode = true;
          }
          _selectedNode = saved;
        }
      }

      // 1. 获取订阅信息
      dynamic subscribeInfo;
      try {
        subscribeInfo = await _userService.getSubscribe();
      } catch (e) {
        debugPrint('DEBUG: getSubscribe 失败: $e');
        _errorMessage = '未找到订阅链接，请先购买套餐 (Debug: $e)';
        _isLoading = false;
        notifyListeners();
        return;
      }

      debugPrint('DEBUG: subscribeInfo = $subscribeInfo');

      if (subscribeInfo == null) {
        _errorMessage = '未找到订阅链接，请先购买套餐';
        _isLoading = false;
        notifyListeners();
        return;
      }

      final subscribeUrl = subscribeInfo['subscribe_url'] as String?;

      if (subscribeUrl == null || subscribeUrl.isEmpty) {
        _errorMessage = '未找到订阅链接，请先购买套餐';
        _isLoading = false;
        notifyListeners();
        return;
      }

      // 2. 从 subscribe_url 中提取 token
      final uri = Uri.parse(subscribeUrl);
      final pathSegments = uri.pathSegments;
      final token = pathSegments.isNotEmpty ? pathSegments.last : null;

      if (token == null || token.isEmpty) {
        _errorMessage = '订阅链接格式错误: $subscribeUrl';
        _isLoading = false;
        notifyListeners();
        return;
      }

      // 3. 获取 Sing-box 配置
      dynamic config;
      try {
        config = await _userService.getSubscriptionConfig(token);
      } catch (e) {
        debugPrint('DEBUG: getSubscriptionConfig 失败: $e');
        _errorMessage = '订阅配置获取失败，请检查您的订阅状态';
        _isLoading = false;
        notifyListeners();
        return;
      }

      // 4. 解析节点
      final List<ProxyNode> fetchedNodes =
          SingboxConfigParser.parseNodes(config);

      // 5. 合并延迟数据
      final Map<String, int?> oldLatencies = {
        for (var node in _nodes)
          if (node.latency != null && node.type != 'auto')
            node.name: node.latency
      };

      List<ProxyNode> mergedNodes = fetchedNodes.map((node) {
        if (oldLatencies.containsKey(node.name)) {
          return node.copyWithLatency(oldLatencies[node.name]);
        }
        return node;
      }).toList();

      // 6. 在列表最前面插入"自动选择节点"虚拟节点
      final autoNode = ProxyNode(
        name: kAutoSelectNodeName,
        type: 'auto',
        server: '',
        port: 0,
      );
      _nodes = [autoNode, ...mergedNodes];

      debugPrint('DEBUG: 解析到 ${mergedNodes.length} 个节点 + 1 个自动选择节点');

      // 7. 默认选择逻辑
      if (_selectedNode == null) {
        // 首次启动，默认自动模式
        _isAutoMode = true;
        _selectedNode = autoNode;
      }

      // 8. 如果是自动模式，尝试选择最优节点
      if (_isAutoMode) {
        _evaluateAutoSelect();
      }

      _isLoading = false;
      notifyListeners();
    } catch (e) {
      debugPrint('DEBUG: fetchNodes 错误: $e');
      _errorMessage = '获取节点列表失败: ${e.toString()}';
      _isLoading = false;
      notifyListeners();
    }
  }

  // ==================== 节点选择 ====================

  /// 选择节点
  void selectNode(ProxyNode node) {
    debugPrint(
        'NODE_SWITCH: selectNode called with "${node.name}" (type=${node.type}), isConnected=${_vpnManager.currentState.status}');
    _selectedNode = node;

    if (node.type == 'auto') {
      // 用户选择了自动选择节点
      _isAutoMode = true;
      _storage.write(key: 'auto_mode', value: 'true');

      // 立即选择当前最优真实节点
      _evaluateAutoSelect(forceSwitch: true);
    } else {
      // 用户选择了具体节点
      _isAutoMode = false;
      _autoSelectedRealNode = null;
      _storage.write(key: 'auto_mode', value: 'false');

      // 通知 VPN 切换节点
      debugPrint(
          'NODE_SWITCH: calling selectOutbound("节点选择", "${node.name}"), VPN status=${_vpnManager.currentState.status}');
      _vpnManager.selectOutbound("节点选择", node.name);
    }

    notifyListeners();

    // 持久化选中节点
    _storage.write(key: 'selected_node', value: jsonEncode(node.toJson()));
  }

  // ==================== 自动选择逻辑 ====================

  /// 评估并执行自动选择
  /// [forceSwitch] 为 true 时强制切换到最优节点（用户主动选择自动模式时）
  void _evaluateAutoSelect({bool forceSwitch = false}) {
    if (!_isAutoMode) return;

    final available = realNodes;
    if (available.isEmpty) return;

    // 找到延迟最低的在线节点
    ProxyNode? bestNode;
    int bestLatency = 999999;

    for (final node in available) {
      if (node.latency != null &&
          node.latency! > 0 &&
          node.latency! < bestLatency) {
        bestLatency = node.latency!;
        bestNode = node;
      }
    }

    if (bestNode == null) {
      // 没有任何在线节点，保持当前选择或选第一个
      if (_autoSelectedRealNode == null && available.isNotEmpty) {
        _autoSelectedRealNode = available.first;
        _vpnManager.selectOutbound("节点选择", available.first.name);
        debugPrint('DEBUG 自动选择: 无测速数据，选择第一个节点: ${available.first.name}');
      }
      return;
    }

    if (forceSwitch) {
      // 强制切换（用户主动选择自动模式）
      _autoSelectedRealNode = bestNode;
      _vpnManager.selectOutbound("节点选择", bestNode.name);
      debugPrint('DEBUG 自动选择: 强制切换到最优节点: ${bestNode.name} (${bestLatency}ms)');
      return;
    }

    // 检查当前自动选中的节点是否仍在线
    if (_autoSelectedRealNode != null) {
      // 在最新的节点列表中找到它
      final currentNode = available.firstWhere(
        (n) => n.name == _autoSelectedRealNode!.name,
        orElse: () => available.first,
      );

      final currentLatency = currentNode.latency;

      // 仅在当前节点超时或不可用时才切换
      if (currentLatency != null && currentLatency > 0) {
        // 当前节点仍在线，不切换
        _autoSelectedRealNode = currentNode; // 更新延迟数据
        debugPrint(
            'DEBUG 自动选择: 当前节点 ${currentNode.name} (${currentLatency}ms) 仍在线，不切换');
        return;
      }

      // 当前节点超时了，切换到最优节点
      debugPrint(
          'DEBUG 自动选择: 当前节点 ${_autoSelectedRealNode!.name} 已超时，切换到: ${bestNode.name} (${bestLatency}ms)');
    } else {
      debugPrint('DEBUG 自动选择: 首次选择最优节点: ${bestNode.name} (${bestLatency}ms)');
    }

    _autoSelectedRealNode = bestNode;
    _vpnManager.selectOutbound("节点选择", bestNode.name);
  }
}
