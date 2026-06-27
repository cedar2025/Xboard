import 'package:dio/dio.dart';
import '../../services/app_logger.dart';
import '../../storage/local_storage.dart';
import '../../storage/secure_storage.dart';
import '../../../providers/auth_provider.dart';

class AuthInterceptor extends Interceptor {
  final SecureStorage storage;
  final LocalStorage _localStorage = const LocalStorage();

  AuthInterceptor(this.storage);

  @override
  void onRequest(
      RequestOptions options, RequestInterceptorHandler handler) async {
    // 从安全存储读取 token，增加 try-catch 处理卸载重装导致的解密失败 (BadPaddingException)
    String? token;
    try {
      token = await storage.read(key: 'auth_token');
    } catch (e) {
      await AppLogger.instance.error('Secure storage read failed', error: e);
      await storage.deleteAll();
    }

    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    super.onRequest(options, handler);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    // Token 失效处理
    if (err.response?.statusCode == 401) {
      await storage.delete(key: 'auth_token');
      await _localStorage.delete(key: AuthProvider.sessionHintKey);
      await AppLogger.instance.warn('Auth token removed after 401 response');
    }
    super.onError(err, handler);
  }
}
