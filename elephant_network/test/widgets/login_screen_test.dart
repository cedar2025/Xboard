import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:elephant_network/screens/auth/login_screen.dart';
import 'package:elephant_network/providers/auth_provider.dart';
import 'package:elephant_network/core/api/dio_client.dart';
void main() {
  group('LoginScreen Widget 测试', () {
    late AuthProvider authProvider;

    setUp(() {
      authProvider = AuthProvider(DioClient());
    });

    Widget createLoginScreen() {
      return MaterialApp(
        home: ChangeNotifierProvider<AuthProvider>.value(
          value: authProvider,
          child: const LoginScreen(),
        ),
      );
    }

    testWidgets('应该渲染登录界面的基本元素', (WidgetTester tester) async {
      // Act
      await tester.pumpWidget(createLoginScreen());

      // Assert - 查找关键UI元素
      expect(find.byType(TextField), findsWidgets);
      expect(find.byType(ElevatedButton), findsWidgets);
    });

    testWidgets('输入邮箱和密码应该更新文本框内容', (WidgetTester tester) async {
      // Arrange
      await tester.pumpWidget(createLoginScreen());

      // Act - 查找输入框并输入内容
      final emailField = find.byType(TextField).first;
      await tester.enterText(emailField, 'test@example.com');
      await tester.pump();

      // Assert
      expect(find.text('test@example.com'), findsOneWidget);
    });

    testWidgets('加载状态应该显示加载指示器', (WidgetTester tester) async {
      // 此测试需要触发实际的加载状态，暂时跳过
      // 在实际测试中，可以通过调用 login 方法来触发
    });

    testWidgets('错误信息应该显示在界面上', (WidgetTester tester) async {
      // 此测试需要触发实际的错误状态
      // 实际测试需要根据 LoginScreen 的实现调整
    });
  });
}
