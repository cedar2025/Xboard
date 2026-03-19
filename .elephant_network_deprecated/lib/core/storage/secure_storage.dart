import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Secure storage facade.
///
/// On macOS this delegates token persistence to native Keychain APIs.
/// Other platforms fall back to SharedPreferences to preserve existing flows.
class SecureStorage {
  const SecureStorage();

  static const MethodChannel _channel =
      MethodChannel('com.elephant.network/runtime');

  Future<void> write({required String key, required String? value}) async {
    if (defaultTargetPlatform == TargetPlatform.macOS) {
      if (value == null) {
        await delete(key: key);
        return;
      }
      await _channel.invokeMethod('writeSecureValue', {
        'key': key,
        'value': value,
      });
      return;
    }

    final prefs = await SharedPreferences.getInstance();
    if (value == null) {
      await prefs.remove(key);
    } else {
      await prefs.setString(key, value);
    }
  }

  Future<String?> read({required String key}) async {
    if (defaultTargetPlatform == TargetPlatform.macOS) {
      final result = await _channel.invokeMethod<String?>('readSecureValue', {
        'key': key,
      });
      return result;
    }

    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(key);
  }

  Future<void> delete({required String key}) async {
    if (defaultTargetPlatform == TargetPlatform.macOS) {
      await _channel.invokeMethod('deleteSecureValue', {'key': key});
      return;
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(key);
  }

  Future<void> deleteAll() async {
    if (defaultTargetPlatform == TargetPlatform.macOS) {
      await _channel.invokeMethod('deleteAllSecureValues');
      return;
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
  }
}
