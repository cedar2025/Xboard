import 'dart:io';
import 'dart:typed_data';

typedef SingBoxAssetLoader = Future<Uint8List> Function(String assetPath);
typedef StopSingBoxCore = Future<void> Function();
typedef MakeFileExecutable = Future<void> Function(File file);
typedef MacosConfigValidator = Future<MacosConfigValidationResult> Function({
  required String binaryPath,
  required String configPath,
});

class MacosSingBoxRuntime {
  static const String targetVersion = '1.12.25';
  static const Map<String, String> compatibilityEnvironment = {
    'ENABLE_DEPRECATED_SPECIAL_OUTBOUNDS': 'true',
    'ENABLE_DEPRECATED_LEGACY_DNS_SERVERS': 'true',
    'ENABLE_DEPRECATED_TUN_ADDRESS_X': 'true',
  };

  static Future<MacosConfigValidationResult> validateConfig({
    required String binaryPath,
    required String configPath,
  }) async {
    final result = await Process.run(
      binaryPath,
      ['check', '-c', configPath],
      environment: {
        ...Platform.environment,
        ...compatibilityEnvironment,
      },
    );
    if (result.exitCode == 0) {
      return const MacosConfigValidationResult.valid();
    }

    final combined = '${result.stderr}\n${result.stdout}';
    return MacosConfigValidationResult.invalid(
      _lastErrorLine(combined) ?? 'sing-box 配置检查失败',
    );
  }

  static String? _lastErrorLine(String output) {
    final sanitized = output.replaceAll(
      RegExp(r'\x1B\[[0-9;]*[mK]'),
      '',
    );
    final lines = sanitized
        .split('\n')
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .toList(growable: false);
    if (lines.isEmpty) return null;
    return lines.last;
  }
}

class MacosConfigValidationResult {
  const MacosConfigValidationResult.valid()
      : isValid = true,
        error = null;

  const MacosConfigValidationResult.invalid(this.error) : isValid = false;

  final bool isValid;
  final String? error;
}

class MacosSingBoxRuntimeInstaller {
  const MacosSingBoxRuntimeInstaller({
    required this.assetLoader,
    required this.stopCore,
    required this.makeExecutable,
  });

  final SingBoxAssetLoader assetLoader;
  final StopSingBoxCore stopCore;
  final MakeFileExecutable makeExecutable;

  Future<bool> ensureInstalled({
    required Directory runtimeDirectory,
    required String binaryName,
  }) async {
    final binary = File('${runtimeDirectory.path}/$binaryName');
    final versionFile = File('${binary.path}.version');
    final installedVersion = await _readVersion(versionFile);
    if (await binary.exists() &&
        installedVersion == MacosSingBoxRuntime.targetVersion) {
      return false;
    }

    final bytes = await assetLoader('assets/bin/$binaryName');
    await stopCore();
    await runtimeDirectory.create(recursive: true);

    final temporaryBinary = File('${binary.path}.tmp');
    final temporaryVersion = File('${versionFile.path}.tmp');
    try {
      await temporaryBinary.writeAsBytes(bytes, flush: true);
      await makeExecutable(temporaryBinary);
      await temporaryBinary.rename(binary.path);

      await temporaryVersion.writeAsString(
        '${MacosSingBoxRuntime.targetVersion}\n',
        flush: true,
      );
      await temporaryVersion.rename(versionFile.path);
      return true;
    } catch (_) {
      if (await temporaryBinary.exists()) {
        await temporaryBinary.delete();
      }
      if (await temporaryVersion.exists()) {
        await temporaryVersion.delete();
      }
      rethrow;
    }
  }

  Future<String?> _readVersion(File versionFile) async {
    if (!await versionFile.exists()) return null;
    return (await versionFile.readAsString()).trim();
  }
}
