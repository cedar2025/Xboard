import 'package:shared_preferences/shared_preferences.dart';

/// 统一的本地存储代理
/// - 绕过 macOS 对 FlutterSecureStorage 强求的沙盒及 Keychain 签名需求
/// - 全局统一使用 SharedPreferences
class LocalStorage {
  const LocalStorage();

  Future<void> write({required String key, required String? value}) async {
    final prefs = await SharedPreferences.getInstance();
    if (value == null) {
      await prefs.remove(key);
    } else {
      await prefs.setString(key, value);
    }
  }

  Future<String?> read({required String key}) async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(key);
  }

  Future<void> delete({required String key}) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(key);
  }

  Future<void> deleteAll() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
  }
}
