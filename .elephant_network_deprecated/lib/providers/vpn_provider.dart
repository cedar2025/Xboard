import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../core/singbox/vpn_manager.dart';
import '../core/singbox/vpn_state.dart';
import '../core/singbox/mock_vpn_service.dart';
import '../core/singbox/real_vpn_service.dart';
import '../core/api/dio_client.dart';
import '../core/api/services/user_service.dart';
import '../../utils/constants.dart';
import '../providers/config_provider.dart';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'dart:convert';
import 'dart:async';
import 'dart:io';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';
import '../core/storage/local_storage.dart';

class VpnProvider with ChangeNotifier {
  final VpnManager _vpnManager;
  final UserService _userService;
  final ConfigProvider _configProvider;
  final LocalStorage _storage = const LocalStorage();
  final Dio _trafficDio = Dio()
    ..httpClientAdapter = IOHttpClientAdapter(
      onHttpClientCreate: (client) {
        client.findProxy = (uri) => 'DIRECT';
        return client;
      },
    );
  VpnState _state = const VpnState(status: VpnStatus.disconnected);
  Timer? _trafficTimer;

  VpnProvider(DioClient dioClient, this._vpnManager, this._configProvider) 
      : _userService = UserService(dioClient) {
    // 监听 VPN 状态变化
    _vpnManager.stateStream.listen((state) {
      // 检测从已连接变为断开状态
      if (_state.isConnected && state.status == VpnStatus.disconnected) {
        debugPrint('VPN 断开，保存未上报流量: up=${_state.totalUp}, down=${_state.totalDown}');
        // 保存本次会话的流量增量到本地
        _saveSessionTraffic(_state.totalUp, _state.totalDown);
        
        // 重置本地流量计数器
        _state = state.copyWith(
          totalUp: 0,
          totalDown: 0,
          upSpeed: 0,
          downSpeed: 0,
        );
      } else {
        _state = state;
      }
      notifyListeners();
    });
  }

  VpnState get state => _state;
  bool get isConnected => _state.isConnected;
  bool get isProcessing => _state.isProcessing;

  /// 切换 VPN 连接状态
  Future<void> toggle() async {
    if (_state.isProcessing) {
      return; // 正在处理中,忽略操作
    }

    if (_state.isConnected) {
      await disconnect();
    } else {
      await connect();
    }
  }

  /// 连接 VPN
  Future<bool> connect() async {
    try {
      // 0. 更新状态为连接中，防止点击无反应
      _state = _state.copyWith(status: VpnStatus.connecting);
      notifyListeners();

      // [FIX] Copy assets (srs files) to filesystem before starting
      await _copyAssetsToFilesystem();

      // 1. 请求 VPN 权限
      final hasPermission = await _vpnManager.requestPermission();
      if (!hasPermission) {
        _state = _state.copyWith(status: VpnStatus.disconnected);
        notifyListeners();
        return false;
      }

      // 2. 获取真实的订阅配置
      String? config;
      try {
        final subscribeInfo = await _userService.getSubscribe();
        final subscribeUrl = subscribeInfo['subscribe_url'] as String?;
        if (subscribeUrl != null && subscribeUrl.isNotEmpty) {
          final uri = Uri.parse(subscribeUrl);
          final token = uri.pathSegments.isNotEmpty ? uri.pathSegments.last : null;
          if (token != null && token.isNotEmpty) {
            debugPrint('开始获取订阅配置，Token: $token');
            final rawConfig = await _userService.getSubscriptionConfig(token);
            config = jsonEncode(rawConfig);
            debugPrint('成功获取并编码订阅配置, 长度: ${config.length}');
          }
        }
      } catch (e) {
        debugPrint('获取订阅配置失败: $e');
      }

      if (config == null || config.isEmpty) {
         _state = _state.copyWith(
           status: VpnStatus.disconnected,
           errorMessage: '获取订阅配置失败，请检查网络或套餐是否有效'
         );
         notifyListeners();
         return false;
      }

      // 注入用户自定义 DNS 配置
      config = _injectDnsConfig(config);

      // 4. 启动内核
      await _vpnManager.start(config);
      
      // 启动流量监控 (P0 需求)
      _startTrafficPolling();
      
      return true;
    } catch (e) {
      debugPrint('VPN 连接失败: $e');
      _state = _state.copyWith(status: VpnStatus.disconnected, errorMessage: e.toString());
      notifyListeners();
      return false;
    }
  }

  /// 断开 VPN
  Future<void> disconnect() async {
    try {
      _stopTrafficPolling();
      await _vpnManager.stop();
    } catch (e) {
      debugPrint('VPN 断开失败: $e');
    }
  }

  /// 启动实时流量轮询 (需求 3.4)
  void _startTrafficPolling() {
    _trafficTimer?.cancel();
    _trafficTimer = Timer.periodic(const Duration(seconds: 1), (timer) async {
      if (!_state.isConnected) {
        timer.cancel();
        return;
      }

      try {
        // 请求 Clash API (127.0.0.1:9090/traffic)
        final response = await _trafficDio.get(ApiConstants.clashTraffic);
        final Map<String, dynamic> data = response.data is Map ? Map<String, dynamic>.from(response.data) : {};
        final int up = (data['up'] ?? 0).toInt();
        final int down = (data['down'] ?? 0).toInt();
        
        // 更新状态中的网速
        // 注意：总流量优先依靠原生端广播更新，这里仅作为速度轮询补充
        _state = _state.copyWith(
          upSpeed: up,
          downSpeed: down,
          // 如果原生端没有回传累计流量(为0)，则可以使用速度进行本地估算累加
          // 但为了防止冲突，这里暂时只更新速度，除非确定需要本地累加
          totalUp: _state.totalUp > 0 ? _state.totalUp : (_state.totalUp + up),
          totalDown: _state.totalDown > 0 ? _state.totalDown : (_state.totalDown + down),
        );
        notifyListeners();
      } catch (e) {
        // Silently ignore traffic polling errors to reduce log noise
      }
    });
  }

  void _stopTrafficPolling() {
    _trafficTimer?.cancel();
    _trafficTimer = null;
  }

  /// 保存本次会话流量到本地存储（未上报到后端的流量）
  Future<void> _saveSessionTraffic(int up, int down) async {
    if (up == 0 && down == 0) return;
    
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedUp = prefs.getInt('unreported_traffic_up') ?? 0;
      final savedDown = prefs.getInt('unreported_traffic_down') ?? 0;
      
      await prefs.setInt('unreported_traffic_up', savedUp + up);
      await prefs.setInt('unreported_traffic_down', savedDown + down);
      
      debugPrint('保存未上报流量: +${up}B up, +${down}B down (总计: ${savedUp + up}B up, ${savedDown + down}B down)');
    } catch (e) {
      debugPrint('保存流量失败: $e');
    }
  }
  
  /// 获取未上报的流量总计（字节）
  Future<int> getUnreportedTraffic() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final up = prefs.getInt('unreported_traffic_up') ?? 0;
      final down = prefs.getInt('unreported_traffic_down') ?? 0;
      return up + down;
    } catch (e) {
      debugPrint('读取未上报流量失败: $e');
      return 0;
    }
  }
  
  /// 清空未上报流量（当后端已同步时调用）
  Future<void> clearUnreportedTraffic() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('unreported_traffic_up');
      await prefs.remove('unreported_traffic_down');
      debugPrint('清空未上报流量');
    } catch (e) {
      debugPrint('清空未上报流量失败: $e');
    }
  }

  /// Copy SRS assets to working directory
  Future<void> _copyAssetsToFilesystem() async {
    try {
      // Use getApplicationSupportDirectory to match Android's context.getFilesDir()
      final directory = await getApplicationSupportDirectory();
      final singBoxDir = Directory('${directory.path}/sing-box');
      if (!await singBoxDir.exists()) {
        await singBoxDir.create(recursive: true);
      }

      final files = ['geosite-cn.srs', 'geoip-cn.srs'];
      for (final fileName in files) {
        final file = File('${singBoxDir.path}/$fileName');
        // Always overwrite to ensure latest version
        final byteData = await rootBundle.load('assets/srs/$fileName');
        await file.writeAsBytes(byteData.buffer.asUint8List());
        debugPrint('Copied asset $fileName to ${file.path}');
      }
    } catch (e) {
      debugPrint('Error copying assets: $e');
    }
  }

  @override
  void dispose() {
    _stopTrafficPolling();
    _vpnManager.dispose();
    super.dispose();
  }

  /// 注入用户自定义 DNS 配置到 Sing-box 配置中
  String _injectDnsConfig(String jsonConfig) {
    try {
      final Map<String, dynamic> config = jsonDecode(jsonConfig);
      
      // 读取用户设置的 DNS
      final foreignDns = _configProvider.foreignDns;
      final domesticDns = _configProvider.domesticDns;

      if (config.containsKey('dns') && config['dns'] is Map) {
        final dnsConfig = config['dns'] as Map<String, dynamic>;
        if (dnsConfig.containsKey('servers') && dnsConfig['servers'] is List) {
          final servers = dnsConfig['servers'] as List<dynamic>;
          
          for (var server in servers) {
            if (server is Map) {
              final tag = server['tag'];
              // 替换 remote (国外) 的地址
              if (tag == 'remote' && server.containsKey('address')) {
                server['address'] = foreignDns;
                print('DEBUG: 注入国外 DNS: $foreignDns');
              }
              // 替换 local/国内 DNS 的地址
              else if ((tag == 'local' || tag == 'domestic') && server.containsKey('address')) {
                server['address'] = domesticDns;
                print('DEBUG: 注入国内 DNS: $domesticDns');
              }
            }
          }
        }
      }
      return jsonEncode(config);
    } catch (e) {
      print('DEBUG: DNS 注入失败: $e');
      return jsonConfig; // 如果解析失败，返回原配置
    }
  }
}
