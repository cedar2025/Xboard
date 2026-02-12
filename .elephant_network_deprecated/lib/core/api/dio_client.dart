import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../utils/constants.dart';
import 'interceptors/auth_interceptor.dart';

class DioClient {
  late Dio _dio;
  final _storage = const FlutterSecureStorage();

  DioClient() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConstants.baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
    ));
    
    // 添加认证拦截器
    _dio.interceptors.add(AuthInterceptor(_storage));
  }

  Dio get dio => _dio;
  FlutterSecureStorage get storage => _storage;
}
