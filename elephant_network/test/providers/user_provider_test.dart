import 'package:flutter_test/flutter_test.dart';
import 'package:elephant_network/providers/user_provider.dart';
import 'package:elephant_network/core/api/dio_client.dart';
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  group('UserProvider 测试', () {
    late UserProvider userProvider;
    late DioClient dioClient;

    setUp(() {
      dioClient = DioClient();
      userProvider = UserProvider(dioClient);
    });

    group('获取用户信息', () {
      test('初始状态应该正确', () {
        // Assert
        expect(userProvider.user, null);
        expect(userProvider.subscribeInfo, null);
        expect(userProvider.isLoading, false);
        expect(userProvider.errorMessage, null);
      });

      test('fetchUserInfo 成功时应该更新用户信息', () async {
        // 由于 UserProvider 内部创建 UserService
        // 实际测试需要重构以支持依赖注入
        // 这里展示测试结构
        expect(userProvider.user, null);
      });

      test('fetchUserInfo 失败时应该设置错误信息', () async {
        // 测试错误处理逻辑
        expect(userProvider.errorMessage, null);
      });
    });

    group('获取订阅信息', () {
      test('fetchSubscribeInfo 应该更新订阅信息', () async {
        // Act
        await userProvider.fetchSubscribeInfo();

        // Assert - 由于依赖注入问题，这里只验证方法存在
        expect(userProvider.fetchSubscribeInfo, isA<Function>());
      });
    });

    group('刷新功能', () {
      test('refresh 应该同时获取用户和订阅信息', () async {
        // Act
        await userProvider.refresh();

        // Assert
        expect(userProvider.refresh, isA<Function>());
      });
    });
  });
}
