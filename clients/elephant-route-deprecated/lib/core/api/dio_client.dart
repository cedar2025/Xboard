import 'package:dio/dio.dart';
import '../services/app_logger.dart';
import '../storage/secure_storage.dart';
import '../../utils/constants.dart';
import 'interceptors/auth_interceptor.dart';
import 'package:dio/io.dart';
import 'dart:io';

class DioClient {
  late Dio _dio;
  final _secureStorage = const SecureStorage();

  DioClient() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConstants.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
    ));

    _dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        if (ApiConstants.allowInsecureCertificates) {
          client.badCertificateCallback =
              (X509Certificate cert, String host, int port) => true;
          AppLogger.instance.warn(
              'Insecure certificate validation is enabled for ${ApiConstants.baseUrl}');
        }
        return client;
      },
    );

    // 添加认证拦截器
    _dio.interceptors.add(AuthInterceptor(_secureStorage));
  }

  Dio get dio => _dio;
  SecureStorage get storage => _secureStorage;
}
