import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// 配置管理 Provider
/// 管理 DNS、服务模式、测试 URL 等配置的持久化
class ConfigProvider with ChangeNotifier {
  // 默认值
  static const String defaultForeignDns = '8.8.8.8';
  static const String defaultDomesticDns = '223.5.5.5';
  static const String defaultServiceMode = 'VPN (TUN模式)';
  static const String defaultTestUrl = 'http://cp.cloudflare.com/generate_204';

  // 可选的服务模式列表
  static const List<String> serviceModes = [
    'VPN (TUN模式)',
    'HTTP 代理',
    'SOCKS5 代理',
  ];

  String _foreignDns = defaultForeignDns;
  String _domesticDns = defaultDomesticDns;
  String _serviceMode = defaultServiceMode;
  String _testUrl = defaultTestUrl;
  bool _useTunMode = false;
  bool _isLoaded = false;

  // Getters
  String get foreignDns => _foreignDns;
  String get domesticDns => _domesticDns;
  String get serviceMode => _serviceMode;
  String get testUrl => _testUrl;
  bool get useTunMode => _useTunMode;
  bool get isLoaded => _isLoaded;

  ConfigProvider() {
    _loadSettings();
  }

  /// 从本地存储加载配置
  Future<void> _loadSettings() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      _foreignDns = prefs.getString('config_foreign_dns') ?? defaultForeignDns;
      _domesticDns =
          prefs.getString('config_domestic_dns') ?? defaultDomesticDns;
      _serviceMode =
          prefs.getString('config_service_mode') ?? defaultServiceMode;
      _testUrl = prefs.getString('config_test_url') ?? defaultTestUrl;
      _useTunMode = prefs.getBool('config_use_tun_mode') ?? false;
      _isLoaded = true;
      notifyListeners();
    } catch (e) {
      debugPrint('加载配置失败: $e');
      _isLoaded = true;
      notifyListeners();
    }
  }

  /// 更新国外 DNS
  Future<void> setForeignDns(String dns) async {
    if (dns.isEmpty) return;
    _foreignDns = dns;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('config_foreign_dns', dns);
  }

  /// 更新国内 DNS
  Future<void> setDomesticDns(String dns) async {
    if (dns.isEmpty) return;
    _domesticDns = dns;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('config_domestic_dns', dns);
  }

  /// 更新服务模式
  Future<void> setServiceMode(String mode) async {
    _serviceMode = mode;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('config_service_mode', mode);
  }

  /// 更新测试 URL
  Future<void> setTestUrl(String url) async {
    if (url.isEmpty) return;
    _testUrl = url;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('config_test_url', url);
  }

  /// 更新 TUN 模式状态
  Future<void> setUseTunMode(bool use) async {
    _useTunMode = use;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('config_use_tun_mode', use);
  }

  /// 恢复默认设置
  Future<void> resetToDefaults() async {
    _foreignDns = defaultForeignDns;
    _domesticDns = defaultDomesticDns;
    _serviceMode = defaultServiceMode;
    _testUrl = defaultTestUrl;
    _useTunMode = false;
    notifyListeners();

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('config_foreign_dns');
    await prefs.remove('config_domestic_dns');
    await prefs.remove('config_service_mode');
    await prefs.remove('config_test_url');
    await prefs.remove('config_use_tun_mode');
  }
}
