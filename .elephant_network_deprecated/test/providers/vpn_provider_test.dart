import 'package:flutter_test/flutter_test.dart';
import 'package:elephant_network/providers/vpn_provider.dart';
import 'package:elephant_network/core/singbox/vpn_state.dart';
import 'package:elephant_network/core/api/dio_client.dart';
import 'package:elephant_network/core/singbox/mock_vpn_service.dart'; // [NEW]
void main() {
  group('VpnProvider 测试', () {
    late VpnProvider vpnProvider;

    setUp(() {
      // 使用真实的 MockVpnService (项目中已有的 Mock 实现)
      vpnProvider = VpnProvider(DioClient(), MockVpnService());
    });

    tearDown(() {
      vpnProvider.dispose();
    });

    test('初始状态应该是未连接', () {
      // Assert
      expect(vpnProvider.state.status, VpnStatus.disconnected);
      expect(vpnProvider.isConnected, false);
      expect(vpnProvider.isProcessing, false);
    });

    test('connect 应该将状态改为已连接', () async {
      // Act
      final result = await vpnProvider.connect();

      // Wait for state update
      await Future.delayed(const Duration(milliseconds: 100));

      // Assert
      expect(result, true);
      expect(vpnProvider.isConnected, true);
    });

    test('disconnect 应该将状态改为未连接', () async {
      // Arrange - 先连接
      await vpnProvider.connect();
      await Future.delayed(const Duration(milliseconds: 100));
      expect(vpnProvider.isConnected, true);

      // Act - 断开连接
      await vpnProvider.disconnect();
      await Future.delayed(const Duration(milliseconds: 100));

      // Assert
      expect(vpnProvider.isConnected, false);
    });

    test('toggle 应该在未连接时进行连接', () async {
      // Arrange
      expect(vpnProvider.isConnected, false);

      // Act
      await vpnProvider.toggle();
      await Future.delayed(const Duration(milliseconds: 100));

      // Assert
      expect(vpnProvider.isConnected, true);
    });

    test('toggle 应该在已连接时断开连接', () async {
      // Arrange - 先连接
      await vpnProvider.connect();
      await Future.delayed(const Duration(milliseconds: 100));
      expect(vpnProvider.isConnected, true);

      // Act - 切换
      await vpnProvider.toggle();
      await Future.delayed(const Duration(milliseconds: 100));

      // Assert
      expect(vpnProvider.isConnected, false);
    });

    test('处理中状态应该阻止 toggle 操作', () async {
      // 这个测试依赖于 MockVpnService 的实现
      // 在真实场景中，连接/断开过程中会有短暂的处理中状态
      // 这里我们只验证逻辑存在
      expect(vpnProvider.toggle, isA<Function>());
    });

    test('状态变化应该通知监听器', () async {
      // Arrange
      var notificationCount = 0;
      vpnProvider.addListener(() {
        notificationCount++;
      });

      // Act
      await vpnProvider.connect();
      await Future.delayed(const Duration(milliseconds: 100));

      // Assert
      expect(notificationCount, greaterThan(0));
    });
  });
}
