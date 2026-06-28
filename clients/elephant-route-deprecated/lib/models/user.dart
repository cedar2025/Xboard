class User {
  final String email;
  final int transferEnable; // 总流量(bytes)
  final int u; // 已上传(bytes)
  final int d; // 已下载(bytes)
  final int? expiredAt; // 到期时间(timestamp)
  final int balance; // 余额
  final int? planId; // 套餐ID

  User({
    required this.email,
    required this.transferEnable,
    required this.u,
    required this.d,
    this.expiredAt,
    required this.balance,
    this.planId,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      email: json['email'] ?? '',
      transferEnable: json['transfer_enable'] ?? 0,
      u: json['u'] ?? 0,
      d: json['d'] ?? 0,
      expiredAt: json['expired_at'],
      balance: json['balance'] ?? 0,
      planId: json['plan_id'],
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
}
