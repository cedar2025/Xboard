import 'package:dio/dio.dart';
import '../../storage/local_storage.dart';

class AuthInterceptor extends Interceptor {
  final LocalStorage storage;

  AuthInterceptor(this.storage);

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    // 从安全存储读取 token，增加 try-catch 处理卸载重装导致的解密失败 (BadPaddingException)
    String? token;
    try {
      token = await storage.read(key: 'auth_token');
    } catch (e) {
      print('DEBUG: storage.read token failed (possibly reinstall issue): $e');
      await storage.deleteAll(); // 清除损坏的加密数据
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
      // TODO: 跳转登录页
    }
    super.onError(err, handler);
  }
}
