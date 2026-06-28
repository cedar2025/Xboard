import 'dart:io';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/services.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../models/app_update.dart';
import '../../../utils/constants.dart';

class AppUpdateService {
  AppUpdateService()
      : _dio = Dio(BaseOptions(
          baseUrl: ApiConstants.appDistributionBaseUrl,
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 10),
        )) {
    _dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        if (ApiConstants.allowInsecureCertificates) {
          client.badCertificateCallback = (_, __, ___) => true;
        }
        return client;
      },
    );
  }

  static const _installationIdKey = 'app_distribution_installation_id';
  static const _updateChannel = MethodChannel('com.elephant.network/update');

  final Dio _dio;

  Future<AppUpdateCheckResult> checkForUpdate({
    String channel = 'stable',
  }) async {
    if (!_isSupportedPlatform) {
      return const AppUpdateCheckResult(hasUpdate: false, force: false);
    }

    final packageInfo = await PackageInfo.fromPlatform();
    final installationId = await getInstallationId();
    final arch = await _arch;

    final response = await _dio.get(
      ApiConstants.appUpdate,
      queryParameters: {
        'app_key': _appKey,
        'platform': _platform,
        'channel': channel,
        'version': packageInfo.version,
        'build': int.tryParse(packageInfo.buildNumber) ?? 0,
        'arch': arch,
        'installation_id': installationId,
        'os_version': Platform.operatingSystemVersion,
      },
    );

    final body = response.data;
    final data = body is Map && body['data'] is Map
        ? Map<String, dynamic>.from(body['data'] as Map)
        : body is Map
            ? Map<String, dynamic>.from(body)
            : <String, dynamic>{};

    return AppUpdateCheckResult.fromJson(data);
  }

  Future<void> sendHeartbeat({String channel = 'stable'}) async {
    if (!_isSupportedPlatform) return;
    if (Platform.isAndroid) return;

    final packageInfo = await PackageInfo.fromPlatform();
    final arch = await _arch;
    await _dio.post(
      ApiConstants.appHeartbeat,
      data: {
        'app_key': _appKey,
        'installation_id': await getInstallationId(),
        'platform': _platform,
        'channel': channel,
        'arch': arch,
        'version': packageInfo.version,
        'build': int.tryParse(packageInfo.buildNumber) ?? 0,
        'os_version': Platform.operatingSystemVersion,
      },
    );
  }

  Future<void> sendUpdateResult(String event,
      {String channel = 'stable'}) async {
    if (!_isSupportedPlatform) return;
    if (Platform.isAndroid) return;

    final packageInfo = await PackageInfo.fromPlatform();
    await _dio.post(
      ApiConstants.appUpdateResult,
      data: {
        'app_key': _appKey,
        'installation_id': await getInstallationId(),
        'event': event,
        'platform': _platform,
        'channel': channel,
        'from_version': packageInfo.version,
        'from_build': int.tryParse(packageInfo.buildNumber) ?? 0,
      },
    );
  }

  Future<File> downloadUpdate(
    AppUpdateInfo update, {
    void Function(int received, int total)? onProgress,
  }) async {
    if (update.downloadUrl.isEmpty) {
      throw StateError('Download URL is empty');
    }

    final directory = await getTemporaryDirectory();
    final file = File(
      '${directory.path}/elephant-route-update-${update.buildNumber}.apk',
    );

    if (await file.exists()) {
      await file.delete();
    }

    await _dio.download(
      _resolveDownloadUrl(update.downloadUrl),
      file.path,
      onReceiveProgress: onProgress,
      options: Options(responseType: ResponseType.bytes),
    );

    final expectedSha256 = update.sha256?.trim().toLowerCase();
    if (expectedSha256 != null && expectedSha256.isNotEmpty) {
      final digest = sha256.convert(await file.readAsBytes());
      if (digest.toString().toLowerCase() != expectedSha256) {
        await file.delete();
        throw StateError('Downloaded APK SHA256 mismatch');
      }
    }

    return file;
  }

  Future<void> installDownloadedApk(File apk) async {
    if (!Platform.isAndroid) {
      await _openDownloadedFile(apk);
      return;
    }

    await _updateChannel.invokeMethod<void>('installApk', {
      'path': apk.path,
    });
  }

  Future<String> getInstallationId() async {
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString(_installationIdKey);
    if (existing != null && existing.isNotEmpty) return existing;

    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    final id =
        bytes.map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();
    await prefs.setString(_installationIdKey, id);
    return id;
  }

  String get _platform {
    if (Platform.isAndroid) return 'android';
    if (Platform.isMacOS) return 'macos';
    if (Platform.isWindows) return 'windows';
    return 'unknown';
  }

  bool get _isSupportedPlatform =>
      Platform.isAndroid || Platform.isMacOS || Platform.isWindows;

  String get _appKey {
    if (Platform.isAndroid) return ApiConstants.androidAppDistributionAppKey;
    return ApiConstants.appDistributionAppKey;
  }

  Future<String> get _arch async {
    if (Platform.isAndroid) {
      final abi = await _primaryAndroidAbi();
      if (abi.contains('arm64') || abi.contains('aarch64')) return 'arm64';
      if (abi.contains('armeabi-v7a') || abi.contains('armv7')) return 'armv7';
      if (abi.contains('x86_64')) return 'x64';
      if (abi.contains('x86')) return 'x86';
      return abi.isEmpty ? 'arm64' : abi;
    }

    final executable = Platform.resolvedExecutable.toLowerCase();
    if (executable.contains('arm64') || executable.contains('aarch64')) {
      return 'arm64';
    }
    return Platform.version.toLowerCase().contains('arm64') ? 'arm64' : 'x64';
  }

  Future<String> _primaryAndroidAbi() async {
    try {
      final abi = await _updateChannel.invokeMethod<String>('primaryAbi');
      return (abi ?? '').toLowerCase();
    } catch (_) {
      return '';
    }
  }

  String _resolveDownloadUrl(String downloadUrl) {
    final uri = Uri.tryParse(downloadUrl);
    if (uri != null && uri.hasScheme) return downloadUrl;

    final base = Uri.parse(ApiConstants.appDistributionBaseUrl);
    return base.resolve(downloadUrl).toString();
  }

  Future<void> _openDownloadedFile(File file) async {
    if (Platform.isMacOS) {
      await Process.run('open', [file.path]);
      return;
    }
    if (Platform.isWindows) {
      await Process.run('explorer', [file.path]);
      return;
    }
    throw UnsupportedError('Install is not supported on this platform');
  }
}
