import 'dart:io';
import 'package:flutter/foundation.dart';

class ApiConstants {
  static const bool allowInsecureCertificates = bool.fromEnvironment(
    'ALLOW_INSECURE_CERTS',
    defaultValue: false,
  );

  static const String domainConfigUrl = String.fromEnvironment(
    'DOMAIN_CONFIG_URL',
    defaultValue: 'https://www.elephant-ipcheck.com/api/domains',
  );

  static const String domainHealthPath = '/api/v1/guest/domain/check';

  static const bool disableDynamicDomain = bool.fromEnvironment(
    'DISABLE_DYNAMIC_DOMAIN',
    defaultValue: false,
  );

  // 基础 URL
  static String get baseUrl {
    const envBaseUrl = String.fromEnvironment('BASE_URL');
    if (envBaseUrl.isNotEmpty) return envBaseUrl;

    if (kIsWeb) return 'https://www.elephant111.com';
    // 真机访问
    if (!kIsWeb && (Platform.isAndroid || Platform.isIOS)) {
      return 'https://www.elephant111.com';
    }
    return 'https://www.elephant111.com';
  }

  static String get appDistributionBaseUrl {
    const envBaseUrl = String.fromEnvironment('APP_DISTRIBUTION_URL');
    if (envBaseUrl.isNotEmpty) return envBaseUrl;
    return baseUrl;
  }

  static bool get hasAppDistributionOverride {
    const envBaseUrl = String.fromEnvironment('APP_DISTRIBUTION_URL');
    return envBaseUrl.isNotEmpty;
  }

  static const String appDistributionAppKey = String.fromEnvironment(
    'APP_DISTRIBUTION_APP_KEY',
    defaultValue: 'elephant-route-desktop',
  );

  static const String androidAppDistributionAppKey = String.fromEnvironment(
    'ANDROID_APP_DISTRIBUTION_APP_KEY',
    defaultValue: 'elephant-route-android',
  );

  // API 路径
  static const String login = '/api/v1/passport/auth/login';
  static const String register = '/api/v1/passport/auth/register';
  static const String forget = '/api/v1/passport/auth/forget';
  static const String userInfo = '/api/v1/user/info';
  static const String subscribe = '/api/v1/client/subscribe';
  static const String getSubscribe = '/api/v1/user/getSubscribe';
  static const String inviteFetch = '/api/v1/user/invite/fetch';
  static const String inviteSave = '/api/v1/user/invite/save';
  static const String planList = '/api/v1/user/plan/fetch';
  static const String createOrder = '/api/v1/user/order/save';
  static const String checkout = '/api/v1/user/order/checkout';
  static const String checkOrder = '/api/v1/user/order/check';
  static const String orderDetails = '/api/v1/user/order/detail';
  static const String cancelOrder = '/api/v1/user/order/cancel';
  static const String sendEmailVerify = '/api/v1/passport/comm/sendEmailVerify';
  static const String changePassword = '/api/v1/user/changePassword';
  static const String quickLogin = '/api/v1/passport/auth/getQuickLoginUrl';
  static const String appUpdate = '/api/v1/app/update';
  static const String appHeartbeat = '/api/v1/telemetry/heartbeat';
  static const String appUpdateResult = '/api/v1/telemetry/update-result';

  // Clash API(本地流量监控)
  static const String clashTraffic = 'http://127.0.0.1:9090/traffic';
}
