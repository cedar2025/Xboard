import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('macOS release is arm64-only and ad-hoc signed after pruning', () {
    final script = File('build_macos_beta.sh').readAsStringSync();

    expect(script, contains('MACOS_ARCH="arm64"'));
    expect(script, isNot(contains('MACOS_ARCH must be arm64 or x64')));
    expect(script, contains('--sign -'));
    expect(script, isNot(contains('--options runtime')));
    expect(
      script,
      contains('com.elphantroute.elephantNetwork.tunhelper'),
    );
    expect(script, contains('codesign --verify --deep --strict'));
    expect(script, contains('sing-box-darwin-amd64'));
    expect(script, contains('Applications'));
    expect(script, contains(r'rm -rf "${EXPORT_DIR}"'));
    expect(script, contains(r'rm -rf "${APP_BUNDLE}"'));
    expect(script, isNot(contains('preserve_existing_outputs')));
    expect(
      script,
      contains(r'APP_DISTRIBUTION_URL="${APP_DISTRIBUTION_URL:-}"'),
    );
    expect(
      script,
      isNot(contains(
        r'APP_DISTRIBUTION_URL="${APP_DISTRIBUTION_URL:-${BASE_URL}}"',
      )),
    );
    expect(script, contains('shasum -a 256'));
  });

  test(
    'bundled arm64 core supports AnyTLS',
    () {
      final result = Process.runSync(
        'assets/bin/sing-box-darwin-arm64',
        const ['version'],
      );

      expect(result.exitCode, 0);
      expect(result.stdout.toString(), contains('sing-box version 1.12.25'));
    },
    skip: !Platform.isMacOS,
  );

  test('ad-hoc helper signing does not enable hardened runtime', () {
    final script = File('macos/build_tun_helper.sh').readAsStringSync();
    final adHocBranch = script.split('else').last;

    expect(adHocBranch, contains('--sign -'));
    expect(adHocBranch, isNot(contains('--options runtime')));
  });

  test('bundles a fixed administrator installer and legacy daemon plist', () {
    final helperBuild = File('macos/build_tun_helper.sh').readAsStringSync();
    final installer =
        File('macos/ElephantTunHelper/install-helper.sh').readAsStringSync();
    final plist = File(
      'macos/ElephantTunHelper/'
      'com.elphantroute.elephantNetwork.tunhelper.legacy.plist',
    ).readAsStringSync();

    expect(
      helperBuild,
      contains(r'${APP_CONTENTS}/Resources/ElephantTunHelper'),
    );
    expect(helperBuild, contains('install-helper.sh'));
    expect(helperBuild, contains('tunhelper.legacy.plist'));

    expect(installer, contains(r'case "${1:-}" in'));
    expect(installer, contains('install)'));
    expect(installer, contains('uninstall)'));
    expect(installer, contains('/Applications/大象网络.app'));
    expect(
      installer,
      contains('/Library/PrivilegedHelperTools/ElephantTunHelper'),
    );
    expect(
      installer,
      contains(r'PLIST_DEST="/Library/LaunchDaemons/${LABEL}.plist"'),
    );
    expect(installer, contains('launchctl bootstrap system'));
    expect(installer, contains('launchctl bootout'));
    expect(installer, contains('root:wheel'));
    expect(installer, isNot(contains(r'$2')));

    expect(
      plist,
      contains('/Library/PrivilegedHelperTools/ElephantTunHelper'),
    );
    expect(
      plist,
      contains('com.elphantroute.elephantNetwork.tunhelper'),
    );
  });

  test('release documentation makes the unsigned acceptance gate explicit', () {
    final docs = File('docs/macos-beta-release.md').readAsStringSync();

    expect(docs, contains('Developer ID'));
    expect(docs, contains('仍要打开'));
    expect(docs, contains('platform=macos'));
    expect(docs, contains('arch=arm64'));
    expect(docs, contains('SHA-256'));
  });
}
