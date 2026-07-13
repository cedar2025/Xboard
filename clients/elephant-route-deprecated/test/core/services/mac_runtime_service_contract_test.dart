import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('mac runtime exposes unsigned release setup and startup operations', () {
    final dartSource =
        File('lib/core/services/mac_runtime_service.dart').readAsStringSync();
    final swiftSource =
        File('macos/Runner/AppDelegate.swift').readAsStringSync();

    for (final method in <String>[
      'getSetupStatus',
      'openSystemSettingsLoginItems',
      'refreshTunHelper',
      'getLaunchAtLoginStatus',
      'setLaunchAtLoginEnabled',
      'uninstallTunHelper',
    ]) {
      expect(dartSource, contains("'$method'"));
      expect(swiftSource, contains('case "$method"'));
    }
  });

  test('native helper contract uses the normalized public status values', () {
    final source = File('macos/Runner/AppDelegate.swift').readAsStringSync();

    for (final status in <String>[
      'enabled',
      'requiresApproval',
      'notRegistered',
      'notFound',
      'refreshRequired',
      'connectionFailed',
    ]) {
      expect(source, contains('"$status"'));
    }
  });

  test('native helper registration is blocked outside Applications', () {
    final source = File('macos/Runner/AppDelegate.swift').readAsStringSync();

    expect(source, contains('isInstalledInApplications'));
    expect(source, contains('APP_NOT_IN_APPLICATIONS'));
    expect(source, contains('请先将应用拖入“应用程序”目录并重新打开'));
  });

  test('native helper uses fixed administrator installer and hash upgrades',
      () {
    final source = File('macos/Runner/AppDelegate.swift').readAsStringSync();

    expect(source, contains('import CryptoKit'));
    expect(
      source,
      contains('/Library/PrivilegedHelperTools/ElephantTunHelper'),
    );
    expect(source, contains('/Library/LaunchDaemons/'));
    expect(source, contains('install-helper.sh'));
    expect(source, contains('SHA256.hash'));
    expect(source, contains('/usr/bin/osascript'));
    expect(source, contains('with administrator privileges'));
    expect(source, contains('unregisterManagedTunHelperIfNeeded'));

    for (final code in <String>[
      'HELPER_INSTALL_REQUIRED',
      'HELPER_REFRESH_REQUIRED',
      'AUTHORIZATION_CANCELLED',
      'HELPER_INSTALL_FAILED',
      'HELPER_HEALTH_CHECK_FAILED',
    ]) {
      expect(source, contains(code));
    }
  });

  test('administrator invocation accepts only a fixed action', () {
    final source = File('macos/Runner/AppDelegate.swift').readAsStringSync();

    expect(source, contains('private enum HelperInstallerAction'));
    expect(source, contains('case install'));
    expect(source, contains('case uninstall'));
    expect(source, isNot(contains('arguments["rootCommand"]')));
    expect(source, isNot(contains('arguments["installerPath"]')));
  });

  test('privileged helper restricts XPC clients to the console user', () {
    final source =
        File('macos/ElephantTunHelper/main.swift').readAsStringSync();

    expect(source, contains('connection.effectiveUserIdentifier'));
    expect(source, contains('/dev/console'));
    expect(source, contains('getpwuid'));
    expect(source, contains('clientUID != 0'));
    expect(source, contains('consoleStat.st_uid == clientUID'));
  });

  test('privileged helper validates exact per-user runtime files', () {
    final source =
        File('macos/ElephantTunHelper/main.swift').readAsStringSync();

    expect(source, contains('clientHomeDirectory'));
    expect(source, contains('config.json'));
    expect(source, contains('sing-box-darwin-arm64'));
    expect(source, isNot(contains('supportSuffix')));
  });

  test('native runtime serializes stop cleanup and safely launches commands',
      () {
    final source = File('macos/Runner/AppDelegate.swift').readAsStringSync();

    expect(source, contains('runtimeStopLock'));
    expect(source, contains('reason: "app_terminate"'));
    expect(source, contains('reason: "startup_recovery"'));
    expect(source, contains(r'reason=\(reason)'));
    expect(source, contains('process.executableURL'));
    expect(source, contains('try process.run()'));
    expect(source, contains('Command failed executable='));
    expect(source, isNot(contains('process.launchPath')));
    expect(source, isNot(contains('process.launch()')));
  });

  test('native runtimes allow current config compatibility and surface exits',
      () {
    final appSource = File('macos/Runner/AppDelegate.swift').readAsStringSync();
    final helperSource =
        File('macos/ElephantTunHelper/main.swift').readAsStringSync();

    for (final source in [appSource, helperSource]) {
      expect(source, contains('ENABLE_DEPRECATED_SPECIAL_OUTBOUNDS'));
      expect(source, contains('ENABLE_DEPRECATED_LEGACY_DNS_SERVERS'));
      expect(source, contains('ENABLE_DEPRECATED_TUN_ADDRESS_X'));
    }

    expect(appSource, contains('callTunHelperIfAvailable(timeout: 12, body)'));
    expect(helperSource, contains('CORE_EXITED'));
    expect(helperSource, contains('latestCoreOutput'));
    expect(helperSource, contains('process.isRunning'));
  });
}
