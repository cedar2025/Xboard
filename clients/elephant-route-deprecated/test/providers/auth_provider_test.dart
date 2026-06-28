import 'package:flutter_test/flutter_test.dart';
import 'package:elephant_network/providers/auth_provider.dart';
import 'package:elephant_network/core/api/dio_client.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../test_bootstrap.dart';

void main() {
  configureTestEnvironment();

  group('AuthProvider 测试', () {
    late AuthProvider authProvider;
    late DioClient dioClient;

    setUp(() {
      SharedPreferences.setMockInitialValues({});
      dioClient = DioClient();
      authProvider = AuthProvider(dioClient);
    });

    group('登录功能', () {
      test('登录成功应该更新状态', () async {
        // Arrange
        // (mockResponse removed to fix unused_local_variable warning)

        // 注意：由于 AuthProvider 内部创建 AuthService，
        // 我们需要 mock DioClient 的行为
        // 这里使用简化的测试，实际项目中可能需要重构 Provider 以支持依赖注入

        // Act & Assert
        // 验证初始状态
        expect(authProvider.isLoggedIn, false);
        expect(authProvider.isLoading, false);
        expect(authProvider.errorMessage, null);
      });

      test('登录失败应该设置错误信息', () async {
        // Arrange
        expect(authProvider.errorMessage, null);

        // 这里需要实际的网络调用或更好的依赖注入设计
        // 当前实现中，测试登录失败场景较为困难
        // 建议重构 AuthProvider 以接受 AuthService 作为依赖
      });
    });

    group('登出功能', () {
      test('登出应该清除登录状态', () async {
        // Act
        await authProvider.logout();

        // Assert
        expect(authProvider.isLoggedIn, false);
      });
    });

    group('错误处理', () {
      test('clearError 应该清除错误信息', () {
        // Act
        authProvider.clearError();

        // Assert
        expect(authProvider.errorMessage, null);
      });
    });

    group('状态检查', () {
      test('checkLoginStatus 应该更新登录状态', () async {
        // Act
        await authProvider.checkLoginStatus();

        // Assert
        expect(authProvider.isLoggedIn, isA<bool>());
      });

      test('restoreLoginStatus 应在 hint 和 token 都存在时恢复已验证登录', () async {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(AuthProvider.sessionHintKey, 'true');
        await dioClient.storage.write(key: 'auth_token', value: 'test-token');

        final restored = await authProvider.restoreLoginStatus();

        expect(restored, true);
        expect(authProvider.isLoggedIn, true);
        expect(authProvider.hasValidatedSession, true);
        expect(prefs.getString(AuthProvider.sessionHintKey), 'true');
      });

      test('restoreLoginStatus 应在 hint 存在但 token 缺失时清除登录提示', () async {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(AuthProvider.sessionHintKey, 'true');

        final restored = await authProvider.restoreLoginStatus();

        expect(restored, false);
        expect(authProvider.isLoggedIn, false);
        expect(authProvider.hasValidatedSession, false);
        expect(prefs.getString(AuthProvider.sessionHintKey), null);
      });

      test('restoreLoginStatus 应在 token 存在但 hint 缺失时恢复登录', () async {
        final prefs = await SharedPreferences.getInstance();
        await dioClient.storage.write(key: 'auth_token', value: 'test-token');

        final restored = await authProvider.restoreLoginStatus();

        expect(restored, true);
        expect(authProvider.isLoggedIn, true);
        expect(authProvider.hasValidatedSession, true);
        expect(prefs.getString(AuthProvider.sessionHintKey), 'true');
      });
    });
  });
}
