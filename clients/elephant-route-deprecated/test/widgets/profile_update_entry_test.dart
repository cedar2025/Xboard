import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('profile update entry checks directly without an About menu entry', () {
    final source =
        File('lib/screens/profile/profile_screen.dart').readAsStringSync();

    expect(source, contains("'检查更新'"));
    expect(source, contains("'v\${snapshot.data!.version}'"));
    expect(source, contains('provider.checkForUpdate()'));
    expect(source, contains('showAppUpdateDialog(context, update)'));
    expect(source, isNot(contains("'关于大象网络'")));
    expect(source, isNot(contains("'关于与诊断'")));
    expect(source, isNot(contains('const AboutScreen()')));
  });
}
