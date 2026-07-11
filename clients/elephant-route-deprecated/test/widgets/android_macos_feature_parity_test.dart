import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('shared dashboard and profile do not render generated avatars', () {
    final dashboard =
        File('lib/screens/home/dashboard_screen.dart').readAsStringSync();
    final profile =
        File('lib/screens/profile/profile_screen.dart').readAsStringSync();

    expect(dashboard, isNot(contains('user.emailInitial')));
    expect(profile, isNot(contains('user.emailInitial')));
  });

  test('profile keeps direct update but removes about and diagnostics', () {
    final profile =
        File('lib/screens/profile/profile_screen.dart').readAsStringSync();

    expect(profile, contains("'检查更新'"));
    expect(profile, isNot(contains('AboutScreen')));
    expect(profile, isNot(contains('关于与诊断')));
    expect(File('lib/screens/profile/about_screen.dart').existsSync(), isFalse);
  });

  test('macOS participates in invite sharing and connected latency policy', () {
    final profile =
        File('lib/screens/profile/profile_screen.dart').readAsStringSync();
    final sharing =
        File('lib/core/services/invite_share_service.dart').readAsStringSync();
    final nodeScreen =
        File('lib/screens/home/node_selection_screen.dart').readAsStringSync();

    expect(profile, contains('PlatformUtils.isMacOS'));
    expect(sharing, contains('Platform.isWindows || Platform.isMacOS'));
    expect(nodeScreen, contains('Platform.isMacOS'));
  });

  test('macOS setup uses administrator install instead of login item approval',
      () {
    final guide = File('lib/widgets/mac_setup_guide.dart').readAsStringSync();
    final runtime =
        File('lib/core/services/mac_runtime_service.dart').readAsStringSync();

    expect(guide, contains('管理员授权安装后台网络组件'));
    expect(guide, isNot(contains('登录项与扩展')));
    expect(guide, isNot(contains('openSystemSettingsLoginItems')));
    expect(runtime, contains("'uninstallTunHelper'"));
  });
}
