/// 格式化字节数为可读格式
String formatBytes(int bytes) {
  if (bytes < 1024) return '${bytes}B';
  if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(2)}KB';
  if (bytes < 1024 * 1024 * 1024) {
    return '${(bytes / (1024 * 1024)).toStringAsFixed(2)}MB';
  }
  return '${(bytes / (1024 * 1024 * 1024)).toStringAsFixed(2)}GB';
}

/// 格式化时间戳为日期
String formatTimestamp(int? timestamp) {
  if (timestamp == null) return '未知';
  final date = DateTime.fromMillisecondsSinceEpoch(timestamp * 1000);
  return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
}

/// 计算剩余天数
int getDaysRemaining(int? expiredAt) {
  if (expiredAt == null) return 0;
  final expireDate = DateTime.fromMillisecondsSinceEpoch(expiredAt * 1000);
  final now = DateTime.now();
  return expireDate.difference(now).inDays;
}
