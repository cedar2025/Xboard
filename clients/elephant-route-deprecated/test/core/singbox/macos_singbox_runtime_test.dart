import 'dart:io';
import 'dart:typed_data';

import 'package:elephant_network/core/singbox/macos_singbox_runtime.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late Directory tempDirectory;

  setUp(() {
    tempDirectory = Directory.systemTemp.createTempSync('mac_core_runtime_');
  });

  tearDown(() {
    tempDirectory.deleteSync(recursive: true);
  });

  test('replaces a stale runtime core and records the target version',
      () async {
    final binary = File('${tempDirectory.path}/sing-box-darwin-arm64')
      ..writeAsStringSync('old-core');
    final stopped = <bool>[];
    final installer = MacosSingBoxRuntimeInstaller(
      assetLoader: (_) async => Uint8List.fromList('new-core'.codeUnits),
      stopCore: () async => stopped.add(true),
      makeExecutable: (_) async {},
    );

    final updated = await installer.ensureInstalled(
      runtimeDirectory: tempDirectory,
      binaryName: 'sing-box-darwin-arm64',
    );

    expect(updated, isTrue);
    expect(stopped, [true]);
    expect(binary.readAsStringSync(), 'new-core');
    expect(
      File('${binary.path}.version').readAsStringSync().trim(),
      MacosSingBoxRuntime.targetVersion,
    );
  });

  test('does not rewrite a runtime core already at the target version',
      () async {
    final binary = File('${tempDirectory.path}/sing-box-darwin-arm64')
      ..writeAsStringSync('current-core');
    File('${binary.path}.version')
        .writeAsStringSync(MacosSingBoxRuntime.targetVersion);
    var assetLoads = 0;
    var stops = 0;
    final installer = MacosSingBoxRuntimeInstaller(
      assetLoader: (_) async {
        assetLoads++;
        return Uint8List(0);
      },
      stopCore: () async => stops++,
      makeExecutable: (_) async {},
    );

    final updated = await installer.ensureInstalled(
      runtimeDirectory: tempDirectory,
      binaryName: 'sing-box-darwin-arm64',
    );

    expect(updated, isFalse);
    expect(assetLoads, 0);
    expect(stops, 0);
    expect(binary.readAsStringSync(), 'current-core');
  });

  test('asset failure preserves the existing runtime core', () async {
    final binary = File('${tempDirectory.path}/sing-box-darwin-arm64')
      ..writeAsStringSync('old-core');
    final installer = MacosSingBoxRuntimeInstaller(
      assetLoader: (_) async => throw StateError('asset unavailable'),
      stopCore: () async {},
      makeExecutable: (_) async {},
    );

    await expectLater(
      installer.ensureInstalled(
        runtimeDirectory: tempDirectory,
        binaryName: 'sing-box-darwin-arm64',
      ),
      throwsStateError,
    );

    expect(binary.readAsStringSync(), 'old-core');
    expect(File('${binary.path}.tmp').existsSync(), isFalse);
  });

  test('defines the compatibility environment required by current configs', () {
    expect(MacosSingBoxRuntime.compatibilityEnvironment, {
      'ENABLE_DEPRECATED_SPECIAL_OUTBOUNDS': 'true',
      'ENABLE_DEPRECATED_LEGACY_DNS_SERVERS': 'true',
      'ENABLE_DEPRECATED_TUN_ADDRESS_X': 'true',
    });
  });
}
