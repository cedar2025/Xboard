import 'package:flutter/foundation.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api/services/app_update_service.dart';
import '../core/services/app_logger.dart';
import '../models/app_update.dart';

class AppUpdateProvider with ChangeNotifier {
  AppUpdateProvider({AppUpdateService? service})
      : _service = service ?? AppUpdateService();

  static const _dismissedBuildKey = 'app_update_dismissed_build';
  static const _reportedInstallBuildKey = 'app_update_reported_install_build';

  final AppUpdateService _service;
  AppUpdateInfo? _latest;
  String? _errorMessage;
  String? _downloadErrorMessage;
  bool _isChecking = false;
  bool _isDownloading = false;
  bool _promptDismissed = false;
  double _downloadProgress = 0;

  AppUpdateInfo? get latest => _latest;
  String? get errorMessage => _errorMessage;
  String? get downloadErrorMessage => _downloadErrorMessage;
  bool get isChecking => _isChecking;
  bool get isDownloading => _isDownloading;
  double get downloadProgress => _downloadProgress;
  bool get hasUpdate => _latest != null;
  bool get forceUpdateRequired => _latest?.force == true;
  bool get shouldPrompt =>
      _latest != null && (!_promptDismissed || forceUpdateRequired);

  Future<AppUpdateInfo?> checkForUpdate({bool silent = false}) async {
    if (_isChecking) return _latest;

    _isChecking = true;
    if (!silent) {
      _errorMessage = null;
      notifyListeners();
    }

    try {
      final result = await _service.checkForUpdate();
      if ((result.hasUpdate || result.force) && result.latest != null) {
        _latest = result.latest;
        await _loadDismissedState();
      } else {
        _latest = null;
        _promptDismissed = false;
      }
      _errorMessage = null;
      return _latest;
    } catch (e, stackTrace) {
      _errorMessage = '检查更新失败，请稍后重试';
      if (!silent) {
        await AppLogger.instance
            .error('App update check failed', error: e, stackTrace: stackTrace);
      }
      return _latest;
    } finally {
      _isChecking = false;
      notifyListeners();
    }
  }

  Future<void> dismissCurrentUpdate() async {
    final update = _latest;
    if (update == null || update.force) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_dismissedBuildKey, update.buildNumber);
    _promptDismissed = true;
    notifyListeners();
  }

  Future<bool> downloadAndInstallUpdate() async {
    final update = _latest;
    if (update == null || update.downloadUrl.isEmpty) return false;

    _isDownloading = true;
    _downloadProgress = 0;
    _downloadErrorMessage = null;
    notifyListeners();

    try {
      await _safeTelemetry('download_clicked');
      final artifact = await _service.downloadUpdate(
        update,
        onProgress: (received, total) {
          if (total <= 0) return;
          _downloadProgress = (received / total).clamp(0, 1).toDouble();
          notifyListeners();
        },
      );
      await _safeTelemetry('download_opened');
      await _service.installDownloadedApk(artifact);
      _downloadProgress = 1;
      return true;
    } catch (e, stackTrace) {
      _downloadErrorMessage = '下载更新失败，请稍后重试';
      await AppLogger.instance.error('App update download failed',
          error: e, stackTrace: stackTrace);
      return false;
    } finally {
      _isDownloading = false;
      notifyListeners();
    }
  }

  Future<void> sendHeartbeat() async {
    await _safeCall(() => _service.sendHeartbeat(), silent: true);
    await _sendInstalledAfterUpdateIfNeeded();
  }

  Future<void> _loadDismissedState() async {
    final update = _latest;
    if (update == null || update.force) {
      _promptDismissed = false;
      return;
    }

    final prefs = await SharedPreferences.getInstance();
    _promptDismissed = prefs.getInt(_dismissedBuildKey) == update.buildNumber;
  }

  Future<void> _safeTelemetry(String event) async {
    await _safeCall(() => _service.sendUpdateResult(event), silent: true);
  }

  Future<void> _sendInstalledAfterUpdateIfNeeded() async {
    try {
      final packageInfo = await PackageInfo.fromPlatform();
      final currentBuild = int.tryParse(packageInfo.buildNumber) ?? 0;
      if (currentBuild <= 0) return;

      final prefs = await SharedPreferences.getInstance();
      if (prefs.getInt(_reportedInstallBuildKey) == currentBuild) return;

      await _service.sendUpdateResult('installed_after_update');
      await prefs.setInt(_reportedInstallBuildKey, currentBuild);
    } catch (e, stackTrace) {
      await AppLogger.instance.error('App update install report failed',
          error: e, stackTrace: stackTrace);
    }
  }

  Future<void> _safeCall(Future<void> Function() action,
      {required bool silent}) async {
    try {
      await action();
    } catch (e, stackTrace) {
      if (!silent) {
        await AppLogger.instance.error('App update telemetry failed',
            error: e, stackTrace: stackTrace);
      }
    }
  }
}
