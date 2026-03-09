import 'package:dio/dio.dart';
import '../storage/local_storage.dart';
import '../../utils/constants.dart';
import 'interceptors/auth_interceptor.dart';

class DioClient {
  late Dio _dio;
  final _storage = const LocalStorage();

  DioClient() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConstants.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
    ));
    
    // 添加认证拦截器
    _dio.interceptors.add(AuthInterceptor(_storage));
  }

  Dio get dio => _dio;
  LocalStorage get storage => _storage;
}
