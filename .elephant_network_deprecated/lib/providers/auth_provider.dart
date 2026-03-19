import 'package:flutter/foundation.dart';
import '../core/api/dio_client.dart';
import '../core/api/services/auth_service.dart';
import '../core/storage/local_storage.dart';
import '../core/api/services/comm_service.dart';
import '../utils/api_error_handler.dart';

class AuthProvider with ChangeNotifier {
  static const String sessionHintKey = 'auth_session_hint';

  final AuthService _authService;
  final CommService _commService;
  final LocalStorage _localStorage = const LocalStorage();

  bool _isLoading = false;
  String? _errorMessage;
  bool _isLoggedIn = false;
  bool _hasValidatedSession = false;

  AuthProvider(DioClient dioClient)
      : _authService = AuthService(dioClient),
        _commService = CommService(dioClient);

  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get isLoggedIn => _isLoggedIn;
  bool get hasValidatedSession => _hasValidatedSession;

  /// 获取当前 Token
  Future<String?> getToken() => _authService.getToken();

  Future<void> loadStartupLoginHint() async {
    final hint = await _localStorage.read(key: sessionHintKey);
    _isLoggedIn = hint == 'true';
    _hasValidatedSession = false;
    notifyListeners();
  }

  /// 获取快速登录 URL
  Future<String?> getQuickLoginUrl(String? redirect) async {
    try {
      final token = await _authService.getToken();
      debugPrint(
          'DEBUG: AuthProvider.getQuickLoginUrl - Current Token: ${token?.substring(0, 10)}...');
      final url = await _authService.getQuickLoginUrl(redirect);
      debugPrint('DEBUG: AuthProvider.getQuickLoginUrl - Generated URL: $url');
      return url;
    } catch (e) {
      _errorMessage = ApiErrorHandler.getMessage(e);
      notifyListeners();
      return null;
    }
  }

  /// 检查登录状态
  Future<void> checkLoginStatus() async {
    _isLoggedIn = await _authService.isLoggedIn();
    _hasValidatedSession = _isLoggedIn;
    await _localStorage.write(
      key: sessionHintKey,
      value: _isLoggedIn ? 'true' : null,
    );
    notifyListeners();
  }

  /// 登录
  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await _authService.login(email, password);
      final token = data['auth_data'];

      await _authService.saveToken(token);
      _isLoggedIn = true;
      _hasValidatedSession = true;
      await _localStorage.write(key: sessionHintKey, value: 'true');
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _errorMessage = ApiErrorHandler.getMessage(e);
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  /// 发送邮箱验证码
  Future<bool> sendEmailCode(String email) async {
    try {
      await _commService.sendEmailVerify(email);
      return true;
    } catch (e) {
      _errorMessage = ApiErrorHandler.getMessage(e);
      notifyListeners();
      return false;
    }
  }

  /// 注册
  Future<bool> register(String email, String password, String emailCode,
      {String? inviteCode}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await _authService.register(
        email,
        password,
        emailCode,
        inviteCode: inviteCode,
      );
      final token = data['auth_data'];

      await _authService.saveToken(token);
      _isLoggedIn = true;
      _hasValidatedSession = true;
      await _localStorage.write(key: sessionHintKey, value: 'true');
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _errorMessage = ApiErrorHandler.getMessage(e);
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  /// 找回密码
  Future<bool> forgetPassword(
      String email, String password, String emailCode) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _authService.forget(email, password, emailCode);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _errorMessage = ApiErrorHandler.getMessage(e);
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  /// 退出登录
  Future<void> logout() async {
    await _authService.clearToken();
    _isLoggedIn = false;
    _hasValidatedSession = false;
    await _localStorage.delete(key: sessionHintKey);
    notifyListeners();
  }

  /// 清除错误信息
  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }
}
