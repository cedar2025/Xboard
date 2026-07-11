import 'package:elephant_network/core/singbox/macos_tun_permission.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('MacosTunPermission', () {
    test('accepts only an enabled helper', () {
      expect(
        MacosTunPermission.fromNative(const {'status': 'enabled'}).granted,
        isTrue,
      );
      expect(
        MacosTunPermission.fromNative(
          const {'status': 'enabled', 'ok': false},
        ).granted,
        isFalse,
      );
    });

    test('preserves actionable native failure information', () {
      final permission = MacosTunPermission.fromNative(const {
        'status': 'requiresApproval',
        'code': 'HELPER_REQUIRES_APPROVAL',
        'message': '请在系统设置中允许后台网络组件',
      });

      expect(permission.granted, isFalse);
      expect(permission.status, 'requiresApproval');
      expect(permission.code, 'HELPER_REQUIRES_APPROVAL');
      expect(permission.message, contains('系统设置'));
      expect(permission.requiresSettings, isTrue);
    });

    test('treats refresh and connection failures as denied', () {
      for (final status in ['refreshRequired', 'connectionFailed']) {
        expect(
          MacosTunPermission.fromNative({'status': status}).granted,
          isFalse,
        );
      }
    });

    test('preserves administrator installer failures and cancellation', () {
      for (final entry in const [
        {
          'status': 'notRegistered',
          'code': 'HELPER_INSTALL_REQUIRED',
          'message': '需要管理员授权安装后台网络组件',
        },
        {
          'status': 'notRegistered',
          'code': 'AUTHORIZATION_CANCELLED',
          'message': '已取消管理员授权',
        },
        {
          'status': 'connectionFailed',
          'code': 'HELPER_INSTALL_FAILED',
          'message': '后台网络组件安装失败',
        },
      ]) {
        final permission = MacosTunPermission.fromNative(entry);
        expect(permission.granted, isFalse);
        expect(permission.code, entry['code']);
        expect(permission.message, entry['message']);
      }
    });
  });
}
