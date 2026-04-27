import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api/dio_client.dart';
import '../core/api/services/app_update_service.dart';
import '../core/services/app_logger.dart';
import '../models/app_update.dart';

class AppUpdateProvider with ChangeNotifier {
  AppUpdateProvider(DioClient dioClient)
      : _service = AppUpdateService(dioClient);

  static const _dismissedBuildKey = 'app_update_dismissed_build';

  final AppUpdateService _service;
  AppUpdateInfo? _latest;
  String? _errorMessage;
  bool _isChecking = false;
  bool _promptDismissed = false;

  AppUpdateInfo? get latest => _latest;
  String? get errorMessage => _errorMessage;
  bool get isChecking => _isChecking;
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
      if (result.hasUpdate && result.latest != null) {
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

  Future<bool> openDownloadPage() async {
    final update = _latest;
    if (update == null || update.downloadUrl.isEmpty) return false;

    final uri = Uri.tryParse(update.downloadUrl);
    if (uri == null) return false;

    return launchUrl(uri, mode: LaunchMode.externalApplication);
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
}
