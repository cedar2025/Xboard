import 'package:elephant_network/core/storage/secure_storage.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('com.elephant.network/windows_service');
  final messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;

  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.windows;
    SharedPreferences.setMockInitialValues({});
    messenger.setMockMethodCallHandler(channel, (call) async {
      final arguments = Map<String, dynamic>.from(call.arguments as Map);
      switch (call.method) {
        case 'protectSecret':
          return 'encrypted:${arguments['value']}';
        case 'unprotectSecret':
          return (arguments['value'] as String).replaceFirst('encrypted:', '');
      }
      return null;
    });
  });

  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
    messenger.setMockMethodCallHandler(channel, null);
  });

  test('stores only DPAPI ciphertext on Windows', () async {
    const storage = SecureStorage();
    await storage.write(key: 'auth_token', value: 'secret-token');

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('auth_token'), isNull);
    expect(
      prefs.getString('secure_dpapi_auth_token'),
      'encrypted:secret-token',
    );
    expect(await storage.read(key: 'auth_token'), 'secret-token');
  });

  test('migrates the legacy plaintext token on first read', () async {
    SharedPreferences.setMockInitialValues({'auth_token': 'legacy-token'});
    const storage = SecureStorage();

    expect(await storage.read(key: 'auth_token'), 'legacy-token');
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('auth_token'), isNull);
    expect(
      prefs.getString('secure_dpapi_auth_token'),
      'encrypted:legacy-token',
    );
  });
}
