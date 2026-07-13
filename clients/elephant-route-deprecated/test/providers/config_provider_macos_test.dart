import 'package:elephant_network/providers/config_provider.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.macOS;
  });

  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
  });

  test('macOS migrates the legacy default probe URL to Google', () async {
    SharedPreferences.setMockInitialValues({
      'config_test_url': ConfigProvider.defaultTestUrl,
    });

    final provider = ConfigProvider();
    while (!provider.isLoaded) {
      await Future<void>.delayed(Duration.zero);
    }

    expect(provider.testUrl, ConfigProvider.macosDefaultTestUrl);
    final prefs = await SharedPreferences.getInstance();
    expect(
      prefs.getString('config_test_url'),
      ConfigProvider.macosDefaultTestUrl,
    );
  });

  test('macOS preserves a custom probe URL', () async {
    const customUrl = 'https://example.com/custom_204';
    SharedPreferences.setMockInitialValues({
      'config_test_url': customUrl,
    });

    final provider = ConfigProvider();
    while (!provider.isLoaded) {
      await Future<void>.delayed(Duration.zero);
    }

    expect(provider.testUrl, customUrl);
  });
}
