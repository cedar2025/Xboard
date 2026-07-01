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
        'next_reset_at': 1738339200, // 2025-02-01 00:00:00
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
      expect(user.nextResetAt, 1738339200);
      expect(user.balance, 10000);
      expect(user.planId, 1);
    });

    test('fromJson 应该保留套餐流量和流量包字段', () {
      final json = {
        'email': 'test@example.com',
        'transfer_enable': 161061273600, // 150GB effective total
        'u': 10737418240,
        'd': 10737418240,
        'balance': 0,
        'plan_transfer_enable': 107374182400,
        'plan_used_traffic': 21474836480,
        'plan_remaining_traffic': 85899345920,
        'traffic_package_total': 53687091200,
        'traffic_package_remaining': 53687091200,
        'effective_transfer_enable': 161061273600,
        'effective_remaining_traffic': 139586437120,
      };

      final user = User.fromJson(json);

      expect(user.planTransferEnable, 107374182400);
      expect(user.planUsedTraffic, 21474836480);
      expect(user.planRemainingTraffic, 85899345920);
      expect(user.trafficPackageTotal, 53687091200);
      expect(user.trafficPackageRemaining, 53687091200);
      expect(user.effectiveTransferEnable, 161061273600);
      expect(user.effectiveRemainingTraffic, 139586437120);
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
      expect(user.nextResetAt, null);
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

    test('trafficBreakdown 应该优先从套餐流量扣除本地未上报流量', () {
      final user = User(
        email: 'test@example.com',
        transferEnable: 160,
        u: 20,
        d: 20,
        balance: 0,
        planTransferEnable: 100,
        planUsedTraffic: 40,
        planRemainingTraffic: 60,
        trafficPackageTotal: 60,
        trafficPackageRemaining: 60,
        effectiveTransferEnable: 160,
        effectiveRemainingTraffic: 120,
      );

      final breakdown = user.trafficBreakdown(pendingTraffic: 25);

      expect(breakdown.planUsedTraffic, 65);
      expect(breakdown.planRemainingTraffic, 35);
      expect(breakdown.trafficPackageRemaining, 60);
      expect(breakdown.effectiveRemainingTraffic, 95);
    });

    test('trafficBreakdown 应该在套餐用完后扣除流量包', () {
      final user = User(
        email: 'test@example.com',
        transferEnable: 160,
        u: 20,
        d: 20,
        balance: 0,
        planTransferEnable: 100,
        planUsedTraffic: 40,
        planRemainingTraffic: 60,
        trafficPackageTotal: 60,
        trafficPackageRemaining: 60,
        effectiveTransferEnable: 160,
        effectiveRemainingTraffic: 120,
      );

      final breakdown = user.trafficBreakdown(pendingTraffic: 75);

      expect(breakdown.planUsedTraffic, 100);
      expect(breakdown.planRemainingTraffic, 0);
      expect(breakdown.trafficPackageRemaining, 45);
      expect(breakdown.effectiveRemainingTraffic, 45);
    });

    test('copyWithTrafficData 应该合并订阅流量字段且保留账户字段', () {
      final user = User(
        email: 'test@example.com',
        transferEnable: 100,
        u: 10,
        d: 15,
        expiredAt: 1893456000,
        nextResetAt: 1896134400,
        balance: 300,
        planId: 7,
      );

      final updated = user.copyWithTrafficData({
        'u': 20,
        'd': 25,
        'transfer_enable': 180,
        'plan_transfer_enable': 120,
        'plan_used_traffic': 45,
        'plan_remaining_traffic': 75,
        'traffic_package_total': 60,
        'traffic_package_remaining': 60,
        'effective_transfer_enable': 180,
        'effective_remaining_traffic': 135,
        'next_reset_at': 1898812800,
      });

      expect(updated.email, user.email);
      expect(updated.expiredAt, user.expiredAt);
      expect(updated.nextResetAt, 1898812800);
      expect(updated.balance, user.balance);
      expect(updated.planId, user.planId);
      expect(updated.u, 20);
      expect(updated.d, 25);
      expect(updated.planTransferEnable, 120);
      expect(updated.trafficPackageRemaining, 60);
      expect(updated.effectiveRemainingTraffic, 135);
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
