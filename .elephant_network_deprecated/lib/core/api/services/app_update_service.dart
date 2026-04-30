import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:package_info_plus/package_info_plus.dart';
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

  final Dio _dio;

  Future<AppUpdateCheckResult> checkForUpdate({
    String channel = 'stable',
  }) async {
    if (!_isSupportedPlatform) {
      return const AppUpdateCheckResult(hasUpdate: false, force: false);
    }

    final packageInfo = await PackageInfo.fromPlatform();
    final installationId = await getInstallationId();

    final response = await _dio.get(
      ApiConstants.appUpdate,
      queryParameters: {
        'app_key': ApiConstants.appDistributionAppKey,
        'platform': _platform,
        'channel': channel,
        'version': packageInfo.version,
        'build': int.tryParse(packageInfo.buildNumber) ?? 0,
        'arch': _arch,
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

    final packageInfo = await PackageInfo.fromPlatform();
    await _dio.post(
      ApiConstants.appHeartbeat,
      data: {
        'app_key': ApiConstants.appDistributionAppKey,
        'installation_id': await getInstallationId(),
        'platform': _platform,
        'channel': channel,
        'arch': _arch,
        'version': packageInfo.version,
        'build': int.tryParse(packageInfo.buildNumber) ?? 0,
        'os_version': Platform.operatingSystemVersion,
      },
    );
  }

  Future<void> sendUpdateResult(String event,
      {String channel = 'stable'}) async {
    if (!_isSupportedPlatform) return;

    final packageInfo = await PackageInfo.fromPlatform();
    await _dio.post(
      ApiConstants.appUpdateResult,
      data: {
        'app_key': ApiConstants.appDistributionAppKey,
        'installation_id': await getInstallationId(),
        'event': event,
        'platform': _platform,
        'channel': channel,
        'from_version': packageInfo.version,
        'from_build': int.tryParse(packageInfo.buildNumber) ?? 0,
      },
    );
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
    if (Platform.isMacOS) return 'macos';
    if (Platform.isWindows) return 'windows';
    return 'unknown';
  }

  bool get _isSupportedPlatform => Platform.isMacOS || Platform.isWindows;

  String get _arch {
    final executable = Platform.resolvedExecutable.toLowerCase();
    if (executable.contains('arm64') || executable.contains('aarch64')) {
      return 'arm64';
    }
    return Platform.version.toLowerCase().contains('arm64') ? 'arm64' : 'x64';
  }
}
