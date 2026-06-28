import 'package:flutter_test/flutter_test.dart';
import 'package:elephant_network/models/user.dart';

void main() {
  group('User Model 测试', () {
    test('fromJson 应该正确解析 JSON 数据', () {
      // Arrange
      final json = {
        'email': 'test@example.com',
        'transfer_enable': 107374182400, // 100GB
        'u': 10737418240, // 10GB 上传
        'd': 21474836480, // 20GB 下载
        'expired_at': 1735660800, // 2025-01-01 00:00:00
        'balance': 10000,
        'plan_id': 1,
      };

      // Act
      final user = User.fromJson(json);

      // Assert
      expect(user.email, 'test@example.com');
      expect(user.transferEnable, 107374182400);
      expect(user.u, 10737418240);
      expect(user.d, 21474836480);
      expect(user.expiredAt, 1735660800);
      expect(user.balance, 10000);
      expect(user.planId, 1);
    });

    test('fromJson 应该处理缺失的可选字段', () {
      // Arrange
      final json = {
        'email': 'test@example.com',
        'transfer_enable': 0,
        'u': 0,
        'd': 0,
        'balance': 0,
      };

      // Act
      final user = User.fromJson(json);

      // Assert
      expect(user.email, 'test@example.com');
      expect(user.expiredAt, null);
      expect(user.planId, null);
    });

    test('usedPercentage 应该正确计算已用流量百分比', () {
      // Arrange
      final user = User(
        email: 'test@example.com',
        transferEnable: 100,
        u: 30,
        d: 20,
        balance: 0,
      );

      // Act
      final percentage = user.usedPercentage;

      // Assert
      expect(percentage, 50.0);
    });

    test('usedPercentage 当总流量为 0 时应该返回 0', () {
      // Arrange
      final user = User(
        email: 'test@example.com',
        transferEnable: 0,
        u: 10,
        d: 20,
        balance: 0,
      );

      // Act
      final percentage = user.usedPercentage;

      // Assert
      expect(percentage, 0);
    });

    test('remainingTraffic 应该正确计算剩余流量', () {
      // Arrange
      final user = User(
        email: 'test@example.com',
        transferEnable: 100,
        u: 30,
        d: 20,
        balance: 0,
      );

      // Act
      final remaining = user.remainingTraffic;

      // Assert
      expect(remaining, 50);
    });

    test('emailInitial 应该返回邮箱首字母大写', () {
      final user = User(
        email: ' alice@example.com ',
        transferEnable: 100,
        u: 0,
        d: 0,
        balance: 0,
      );

      expect(user.emailInitial, 'A');
    });

    test('emailInitial 当邮箱为空时应该返回默认字符', () {
      final user = User(
        email: '',
        transferEnable: 100,
        u: 0,
        d: 0,
        balance: 0,
      );

      expect(user.emailInitial, '?');
    });

    test('isExpired 当未设置过期时间时应该返回 false', () {
      // Arrange
      final user = User(
        email: 'test@example.com',
        transferEnable: 100,
        u: 0,
        d: 0,
        balance: 0,
      );

      // Act & Assert
      expect(user.isExpired, false);
    });

    test('isExpired 当时间未过期时应该返回 false', () {
      // Arrange - 设置一个未来的时间戳（2030年）
      final futureTimestamp = 1893456000; // 2030-01-01
      final user = User(
        email: 'test@example.com',
        transferEnable: 100,
        u: 0,
        d: 0,
        expiredAt: futureTimestamp,
        balance: 0,
      );

      // Act & Assert
      expect(user.isExpired, false);
    });

    test('isExpired 当时间已过期时应该返回 true', () {
      // Arrange - 设置一个过去的时间戳（2020年）
      final pastTimestamp = 1577836800; // 2020-01-01
      final user = User(
        email: 'test@example.com',
        transferEnable: 100,
        u: 0,
        d: 0,
        expiredAt: pastTimestamp,
        balance: 0,
      );

      // Act & Assert
      expect(user.isExpired, true);
    });
  });
}
