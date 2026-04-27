import 'dart:io';
import 'package:flutter/foundation.dart';

class ApiConstants {
  static const bool allowInsecureCertificates = bool.fromEnvironment(
    'ALLOW_INSECURE_CERTS',
    defaultValue: false,
  );

  // 基础 URL
  static String get baseUrl {
    const envBaseUrl = String.fromEnvironment('BASE_URL');
    if (envBaseUrl.isNotEmpty) return envBaseUrl;

    if (kIsWeb) return 'https://www.elphantroute.com';
    // 真机访问
    if (!kIsWeb && (Platform.isAndroid || Platform.isIOS)) {
      return 'https://www.elphantroute.com';
    }
    return 'https://www.elphantroute.com';
  }

  // API 路径
  static const String login = '/api/v1/passport/auth/login';
  static const String register = '/api/v1/passport/auth/register';
  static const String forget = '/api/v1/passport/auth/forget';
  static const String userInfo = '/api/v1/user/info';
  static const String subscribe = '/api/v1/client/subscribe';
  static const String getSubscribe = '/api/v1/user/getSubscribe';
  static const String inviteFetch = '/api/v1/user/invite/fetch';
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

  // Clash API(本地流量监控)
  static const String clashTraffic = 'http://127.0.0.1:9090/traffic';
}
