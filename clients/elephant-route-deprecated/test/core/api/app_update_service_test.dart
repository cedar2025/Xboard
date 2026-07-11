import 'package:elephant_network/core/api/services/app_update_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AppUpdateService version comparison', () {
    test('treats 1.2 and 1.2.0 as the same version', () {
      expect(AppUpdateService.isVersionNewer('1.2', '1.2.0'), isFalse);
      expect(AppUpdateService.isVersionNewer('v1.2.0', '1.2'), isFalse);
    });

    test('detects a patch version as newer', () {
      expect(AppUpdateService.isVersionNewer('1.2.1', '1.2.0'), isTrue);
    });

    test('does not treat the current installed version as an update', () {
      expect(AppUpdateService.isVersionNewer('1.2.0', '1.2.0'), isFalse);
    });
  });

  group('AppUpdateService artifact naming', () {
    test('uses an EXE installer for Windows updates', () {
      expect(
        AppUpdateService.updateFileName(
          platform: 'windows',
          buildNumber: 10500,
        ),
        'ElephantNetwork-Setup-x64-10500.exe',
      );
    });

    test('keeps Android and macOS package extensions platform-specific', () {
      expect(
        AppUpdateService.updateFileName(platform: 'android', buildNumber: 9),
        endsWith('.apk'),
      );
      expect(
        AppUpdateService.updateFileName(platform: 'macos', buildNumber: 9),
        endsWith('.dmg'),
      );
    });
  });
}
