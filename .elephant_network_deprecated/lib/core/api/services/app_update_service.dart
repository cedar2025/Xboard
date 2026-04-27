import 'dart:io';

import 'package:package_info_plus/package_info_plus.dart';

import '../../../models/app_update.dart';
import '../../../utils/constants.dart';
import '../dio_client.dart';

class AppUpdateService {
  AppUpdateService(this._client);

  final DioClient _client;

  Future<AppUpdateCheckResult> checkForUpdate({
    String channel = 'stable',
  }) async {
    final packageInfo = await PackageInfo.fromPlatform();
    final platform = Platform.isMacOS
        ? 'macos'
        : Platform.isWindows
            ? 'windows'
            : Platform.isAndroid
                ? 'android'
                : Platform.isIOS
                    ? 'ios'
                    : 'unknown';

    final response = await _client.dio.get(
      ApiConstants.appUpdate,
      queryParameters: {
        'platform': platform,
        'channel': channel,
        'version': packageInfo.version,
        'build': int.tryParse(packageInfo.buildNumber) ?? 0,
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
}
