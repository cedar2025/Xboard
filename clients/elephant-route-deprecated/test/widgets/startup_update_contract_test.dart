import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('startup resolves dependencies and checks updates before navigation',
      () {
    final mainSource = File('lib/main.dart').readAsStringSync();
    final scaffoldSource =
        File('lib/widgets/main_scaffold.dart').readAsStringSync();

    final bootstrapIndex =
        mainSource.indexOf('await AppBootstrap.initialize()');
    final runAppIndex =
        mainSource.indexOf('runApp(MyApp(bootstrap: bootstrap))');

    expect(bootstrapIndex, greaterThanOrEqualTo(0));
    expect(runAppIndex, greaterThan(bootstrapIndex));
    expect(mainSource, contains('updateProvider.sendHeartbeat()'));
    expect(mainSource, contains('checkForUpdate(silent: true)'));
    expect(mainSource, contains('showAppUpdateDialog(context, update)'));
    expect(scaffoldSource, isNot(contains('_checkedUpdate')));
    expect(scaffoldSource, isNot(contains('checkForUpdate')));
  });
}
