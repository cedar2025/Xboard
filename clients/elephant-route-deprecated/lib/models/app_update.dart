class AppUpdateInfo {
  const AppUpdateInfo({
    required this.platform,
    required this.channel,
    required this.version,
    required this.buildNumber,
    required this.downloadUrl,
    required this.force,
    this.arch,
    this.minSupportedBuild = 0,
    this.fileSize,
    this.sha256,
    this.releaseNotes,
    this.publishedAt,
  });

  final String platform;
  final String channel;
  final String? arch;
  final String version;
  final int buildNumber;
  final int minSupportedBuild;
  final String downloadUrl;
  final int? fileSize;
  final String? sha256;
  final String? releaseNotes;
  final bool force;
  final int? publishedAt;

  factory AppUpdateInfo.fromJson(Map<String, dynamic> json,
      {required bool force}) {
    return AppUpdateInfo(
      platform: (json['platform'] ?? '').toString(),
      channel: (json['channel'] ?? 'stable').toString(),
      arch: json['arch']?.toString(),
      version: (json['version'] ?? '').toString(),
      buildNumber: _toInt(json['build_number']),
      minSupportedBuild: _toInt(json['min_supported_build']),
      downloadUrl: (json['download_url'] ?? '').toString(),
      fileSize: json['file_size'] == null ? null : _toInt(json['file_size']),
      sha256: json['sha256']?.toString(),
      releaseNotes: json['release_notes']?.toString(),
      force: force,
      publishedAt:
          json['published_at'] == null ? null : _toInt(json['published_at']),
    );
  }

  static int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }
}

class AppUpdateCheckResult {
  const AppUpdateCheckResult({
    required this.hasUpdate,
    required this.force,
    this.latest,
  });

  final bool hasUpdate;
  final bool force;
  final AppUpdateInfo? latest;

  factory AppUpdateCheckResult.fromJson(Map<String, dynamic> json) {
    final force = json['force'] == true || json['is_force'] == true;
    final latestJson = json['latest'];
    return AppUpdateCheckResult(
      hasUpdate: json['has_update'] == true || json['hasUpdate'] == true,
      force: force,
      latest: latestJson is Map
          ? AppUpdateInfo.fromJson(
              Map<String, dynamic>.from(latestJson),
              force: force,
            )
          : null,
    );
  }
}
