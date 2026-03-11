import 'package:dio/dio.dart';
import '../storage/local_storage.dart';
import '../../utils/constants.dart';
import 'interceptors/auth_interceptor.dart';

import 'package:dio/io.dart';
import 'dart:io';

class DioClient {
  late Dio _dio;
  final _storage = const LocalStorage();

  DioClient() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConstants.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
    ));
    
    // 忽略 HTTPS 证书错误
    _dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        client.badCertificateCallback = (X509Certificate cert, String host, int port) => true;
        return client;
      },
    );

    // 添加认证拦截器
    _dio.interceptors.add(AuthInterceptor(_storage));
  }

  Dio get dio => _dio;
  LocalStorage get storage => _storage;
}
