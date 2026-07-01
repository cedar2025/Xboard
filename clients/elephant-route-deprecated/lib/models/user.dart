class User {
  final String email;
  final int transferEnable; // 总流量(bytes)
  final int u; // 已上传(bytes)
  final int d; // 已下载(bytes)
  final int? expiredAt; // 到期时间(timestamp)
  final int? nextResetAt; // 下次流量重置时间(timestamp)
  final int balance; // 余额
  final int? planId; // 套餐ID
  final int planTransferEnable; // 套餐总流量(bytes)
  final int planUsedTraffic; // 套餐已用流量(bytes)
  final int planRemainingTraffic; // 套餐剩余流量(bytes)
  final int trafficPackageTotal; // 有效流量包总量(bytes)
  final int trafficPackageRemaining; // 流量包剩余流量(bytes)
  final int effectiveTransferEnable; // 可用总流量(bytes)
  final int effectiveRemainingTraffic; // 可用剩余流量(bytes)

  User({
    required this.email,
    required this.transferEnable,
    required this.u,
    required this.d,
    this.expiredAt,
    this.nextResetAt,
    required this.balance,
    this.planId,
    int? planTransferEnable,
    int? planUsedTraffic,
    int? planRemainingTraffic,
    this.trafficPackageTotal = 0,
    this.trafficPackageRemaining = 0,
    int? effectiveTransferEnable,
    int? effectiveRemainingTraffic,
  })  : planTransferEnable = planTransferEnable ?? transferEnable,
        planUsedTraffic = planUsedTraffic ?? (u + d),
        planRemainingTraffic = planRemainingTraffic ??
            (transferEnable - u - d).clamp(0, transferEnable),
        effectiveTransferEnable = effectiveTransferEnable ?? transferEnable,
        effectiveRemainingTraffic = effectiveRemainingTraffic ??
            (transferEnable - u - d).clamp(0, transferEnable);

  factory User.fromJson(Map<String, dynamic> json) {
    final transferEnable = _toInt(json['transfer_enable']);
    final u = _toInt(json['u']);
    final d = _toInt(json['d']);
    final planTransferEnable =
        _toInt(json['plan_transfer_enable'], fallback: transferEnable);
    final planUsedTraffic = _toInt(json['plan_used_traffic'], fallback: u + d);
    final planRemainingTraffic = _toInt(
      json['plan_remaining_traffic'],
      fallback:
          (planTransferEnable - planUsedTraffic).clamp(0, planTransferEnable),
    );
    final trafficPackageRemaining = _toInt(json['traffic_package_remaining']);
    final effectiveTransferEnable = _toInt(
      json['effective_transfer_enable'],
      fallback: transferEnable,
    );
    final effectiveRemainingTraffic = _toInt(
      json['effective_remaining_traffic'],
      fallback: (effectiveTransferEnable - planUsedTraffic)
          .clamp(0, effectiveTransferEnable),
    );

    return User(
      email: json['email'] ?? '',
      transferEnable: transferEnable,
      u: u,
      d: d,
      expiredAt: json['expired_at'],
      nextResetAt: json['next_reset_at'],
      balance: _toInt(json['balance']),
      planId: json['plan_id'],
      planTransferEnable: planTransferEnable,
      planUsedTraffic: planUsedTraffic,
      planRemainingTraffic: planRemainingTraffic,
      trafficPackageTotal: _toInt(json['traffic_package_total']),
      trafficPackageRemaining: trafficPackageRemaining,
      effectiveTransferEnable: effectiveTransferEnable,
      effectiveRemainingTraffic: effectiveRemainingTraffic,
    );
  }

  // 计算已用流量百分比
  double get usedPercentage {
    if (transferEnable == 0) return 0;
    return ((u + d) / transferEnable) * 100;
  }

  // 剩余流量(bytes)
  int get remainingTraffic {
    return transferEnable - u - d;
  }

  TrafficBreakdown trafficBreakdown({int pendingTraffic = 0}) {
    final pending = pendingTraffic.clamp(0, 1 << 62);
    final pendingFromPlan = pending.clamp(0, planRemainingTraffic);
    final pendingFromPackage =
        (pending - pendingFromPlan).clamp(0, trafficPackageRemaining);

    final adjustedPlanRemaining =
        (planRemainingTraffic - pendingFromPlan).clamp(0, planTransferEnable);
    final adjustedPlanUsed =
        (planUsedTraffic + pendingFromPlan).clamp(0, planTransferEnable);
    final adjustedPackageRemaining =
        (trafficPackageRemaining - pendingFromPackage)
            .clamp(0, trafficPackageRemaining);

    return TrafficBreakdown(
      planTransferEnable: planTransferEnable,
      planUsedTraffic: adjustedPlanUsed,
      planRemainingTraffic: adjustedPlanRemaining,
      trafficPackageTotal: trafficPackageTotal,
      trafficPackageRemaining: adjustedPackageRemaining,
      effectiveTransferEnable: effectiveTransferEnable,
      effectiveRemainingTraffic:
          (adjustedPlanRemaining + adjustedPackageRemaining)
              .clamp(0, effectiveTransferEnable),
    );
  }

  User copyWithTrafficData(Map<String, dynamic> json) {
    final nextU = _toInt(json['u'] ?? json['upload'], fallback: u);
    final nextD = _toInt(json['d'] ?? json['download'], fallback: d);
    final nextTransferEnable =
        _toInt(json['transfer_enable'], fallback: transferEnable);
    final nextPlanTransferEnable = _toInt(
      json['plan_transfer_enable'],
      fallback: planTransferEnable,
    );
    final nextPlanUsedTraffic = _toInt(
      json['plan_used_traffic'],
      fallback: nextU + nextD,
    );
    final nextPlanRemainingTraffic = _toInt(
      json['plan_remaining_traffic'],
      fallback: (nextPlanTransferEnable - nextPlanUsedTraffic)
          .clamp(0, nextPlanTransferEnable),
    );

    return User(
      email: email,
      transferEnable: nextTransferEnable,
      u: nextU,
      d: nextD,
      expiredAt: expiredAt,
      nextResetAt: _toNullableInt(json['next_reset_at']) ?? nextResetAt,
      balance: balance,
      planId: planId,
      planTransferEnable: nextPlanTransferEnable,
      planUsedTraffic: nextPlanUsedTraffic,
      planRemainingTraffic: nextPlanRemainingTraffic,
      trafficPackageTotal: _toInt(
        json['traffic_package_total'],
        fallback: trafficPackageTotal,
      ),
      trafficPackageRemaining: _toInt(
        json['traffic_package_remaining'],
        fallback: trafficPackageRemaining,
      ),
      effectiveTransferEnable: _toInt(
        json['effective_transfer_enable'],
        fallback: nextTransferEnable,
      ),
      effectiveRemainingTraffic: _toInt(
        json['effective_remaining_traffic'],
        fallback: (nextTransferEnable - nextPlanUsedTraffic)
            .clamp(0, nextTransferEnable),
      ),
    );
  }

  // 邮箱首字母，用于本地用户标识替代远程头像
  String get emailInitial {
    final trimmedEmail = email.trim();
    if (trimmedEmail.isEmpty) return '?';
    return trimmedEmail.substring(0, 1).toUpperCase();
  }

  // 是否已过期
  bool get isExpired {
    if (expiredAt == null) return false;
    return DateTime.now().millisecondsSinceEpoch > expiredAt! * 1000;
  }

  @override
  String toString() {
    return 'User(email=$email, u=$u, d=$d, transferEnable=$transferEnable, expired=$expiredAt)';
  }

  static int _toInt(dynamic value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value) ?? fallback;
    return fallback;
  }

  static int? _toNullableInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
  }
}

class TrafficBreakdown {
  final int planTransferEnable;
  final int planUsedTraffic;
  final int planRemainingTraffic;
  final int trafficPackageTotal;
  final int trafficPackageRemaining;
  final int effectiveTransferEnable;
  final int effectiveRemainingTraffic;

  const TrafficBreakdown({
    required this.planTransferEnable,
    required this.planUsedTraffic,
    required this.planRemainingTraffic,
    required this.trafficPackageTotal,
    required this.trafficPackageRemaining,
    required this.effectiveTransferEnable,
    required this.effectiveRemainingTraffic,
  });

  int get trafficPackageUsed {
    return (trafficPackageTotal - trafficPackageRemaining)
        .clamp(0, trafficPackageTotal);
  }

  double get usedPercentage {
    if (effectiveTransferEnable <= 0) return 0;
    return ((effectiveTransferEnable - effectiveRemainingTraffic) /
            effectiveTransferEnable *
            100)
        .clamp(0.0, 100.0);
  }
}
