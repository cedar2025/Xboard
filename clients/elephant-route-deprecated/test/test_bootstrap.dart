import 'dart:io';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void configureTestEnvironment() {
  TestWidgetsFlutterBinding.ensureInitialized();
  SharedPreferences.setMockInitialValues({});

  final messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  final supportDir =
      Directory.systemTemp.createTempSync('elephant_route_test_');
  final pathProviderChannel =
      const MethodChannel('plugins.flutter.io/path_provider');
  final secureStorageChannel =
      const MethodChannel('com.elephant.network/runtime');
  final secureValues = <String, String>{};

  messenger.setMockMethodCallHandler(pathProviderChannel, (call) async {
    switch (call.method) {
      case 'getApplicationSupportDirectory':
      case 'getApplicationDocumentsDirectory':
      case 'getTemporaryDirectory':
        return supportDir.path;
      default:
        return null;
    }
  });

  messenger.setMockMethodCallHandler(secureStorageChannel, (call) async {
    final args = Map<String, Object?>.from(call.arguments as Map? ?? {});
    final key = args['key'] as String?;
    switch (call.method) {
      case 'writeSecureValue':
        if (key != null) {
          secureValues[key] = args['value'] as String? ?? '';
        }
        return null;
      case 'readSecureValue':
        return key == null ? null : secureValues[key];
      case 'deleteSecureValue':
        if (key != null) {
          secureValues.remove(key);
        }
        return null;
      case 'deleteAllSecureValues':
        secureValues.clear();
        return null;
      default:
        return null;
    }
  });
}
