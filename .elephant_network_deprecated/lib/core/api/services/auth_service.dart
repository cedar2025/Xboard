import '../dio_client.dart';
import '../../../utils/constants.dart';

class AuthService {
  final DioClient _client;

  AuthService(this._client);

  /// 用户登录
  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await _client.dio.post(
      ApiConstants.login,
      data: {'email': email, 'password': password},
    );
    return response.data['data'];
  }

  /// 用户注册
  Future<Map<String, dynamic>> register(
    String email, 
    String password,
    String emailCode,
    {String? inviteCode}
  ) async {
    final data = {
      'email': email,
      'password': password,
      'email_code': emailCode,
    };
    
    if (inviteCode != null && inviteCode.isNotEmpty) {
      data['invite_code'] = inviteCode;
    }
    
    final response = await _client.dio.post(
      ApiConstants.register,
      data: data,
    );
    return response.data['data'];
  }

  /// 找回密码
  Future<bool> forget(String email, String password, String emailCode) async {
    final response = await _client.dio.post(
      ApiConstants.forget,
      data: {
        'email': email,
        'password': password,
        'email_code': emailCode,
      },
    );
    return response.data['data'];
  }

  /// 保存 Token
  Future<void> saveToken(String token) async {
    await _client.storage.write(key: 'auth_token', value: token);
  }

  /// 获取 Token
  Future<String?> getToken() async {
    try {
      return await _client.storage.read(key: 'auth_token');
    } catch (e) {
      print('DEBUG: [AuthService] getToken failed: $e');
      try {
        await _client.storage.deleteAll();
      } catch (_) {}
      return null;
    }
  }

  /// 获取快速登录 URL
  Future<String> getQuickLoginUrl(String? redirect) async {
    final response = await _client.dio.post(
      ApiConstants.quickLogin,
      data: redirect != null ? {'redirect': redirect} : null,
    );
    return response.data['data'];
  }

  /// 清除 Token
  Future<void> clearToken() async {
    try {
      await _client.storage.delete(key: 'auth_token');
    } catch (e) {
      print('DEBUG: [AuthService] clearToken failed: $e');
      try {
        await _client.storage.deleteAll();
      } catch (_) {}
    }
  }

  /// 检查是否已登录
  Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }
}
