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
}
