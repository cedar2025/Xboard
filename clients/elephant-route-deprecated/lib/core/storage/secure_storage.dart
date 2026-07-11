import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Secure storage facade.
///
/// On macOS this delegates token persistence to native Keychain APIs. On
/// Windows values are protected with per-user DPAPI before the ciphertext is
/// persisted in SharedPreferences. Other platforms retain the existing
/// SharedPreferences behavior.
class SecureStorage {
  const SecureStorage();

  static const MethodChannel _channel =
      MethodChannel('com.elephant.network/runtime');
  static const MethodChannel _windowsChannel =
      MethodChannel('com.elephant.network/windows_service');
  static const String _windowsPrefix = 'secure_dpapi_';

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

    if (defaultTargetPlatform == TargetPlatform.windows) {
      if (value == null) {
        await delete(key: key);
        return;
      }
      final protected = await _windowsChannel.invokeMethod<String>(
        'protectSecret',
        {'key': key, 'value': value},
      );
      if (protected == null || protected.isEmpty) {
        throw PlatformException(
          code: 'DPAPI_ERROR',
          message: 'Windows DPAPI did not return encrypted data',
        );
      }
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('$_windowsPrefix$key', protected);
      await prefs.remove(key);
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

    if (defaultTargetPlatform == TargetPlatform.windows) {
      final prefs = await SharedPreferences.getInstance();
      final encrypted = prefs.getString('$_windowsPrefix$key');
      if (encrypted != null && encrypted.isNotEmpty) {
        return _windowsChannel.invokeMethod<String>('unprotectSecret', {
          'key': key,
          'value': encrypted,
        });
      }

      // One-time migration from the legacy plaintext Windows storage.
      final plaintext = prefs.getString(key);
      if (plaintext == null || plaintext.isEmpty) return null;
      await write(key: key, value: plaintext);
      return plaintext;
    }

    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(key);
  }

  Future<void> delete({required String key}) async {
    if (defaultTargetPlatform == TargetPlatform.macOS) {
      await _channel.invokeMethod('deleteSecureValue', {'key': key});
      return;
    }

    if (defaultTargetPlatform == TargetPlatform.windows) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('$_windowsPrefix$key');
      await prefs.remove(key);
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

    if (defaultTargetPlatform == TargetPlatform.windows) {
      final prefs = await SharedPreferences.getInstance();
      final encryptedKeys = prefs
          .getKeys()
          .where((key) => key.startsWith(_windowsPrefix))
          .toList(growable: false);
      for (final key in encryptedKeys) {
        await prefs.remove(key);
      }
      // auth_token is the only legacy sensitive key written by this client.
      await prefs.remove('auth_token');
      return;
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
  }
}
