import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:launch_at_startup/launch_at_startup.dart';

import '../core/services/mac_runtime_service.dart';

class StartupProvider with ChangeNotifier {
  StartupProvider() {
    _load();
  }

  bool _enabled = false;
  bool _loading = true;

  bool get enabled => _enabled;
  bool get loading => _loading;

  Future<void> _load() async {
    try {
      if (Platform.isWindows) {
        _enabled = await launchAtStartup.isEnabled();
      } else if (Platform.isMacOS) {
        final status =
            await MacRuntimeService.instance.getLaunchAtLoginStatus();
        _enabled = status['enabled'] == true;
      } else {
        _enabled = false;
      }
    } catch (error) {
      debugPrint('Failed to read launch-at-startup state: $error');
      _enabled = false;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> setEnabled(bool enabled) async {
    if ((!Platform.isWindows && !Platform.isMacOS) ||
        _loading ||
        _enabled == enabled) {
      return;
    }
    _loading = true;
    notifyListeners();
    try {
      if (Platform.isMacOS) {
        final status =
            await MacRuntimeService.instance.setLaunchAtLoginEnabled(enabled);
        _enabled = status['enabled'] == true;
      } else {
        if (enabled) {
          await launchAtStartup.enable();
        } else {
          await launchAtStartup.disable();
        }
        _enabled = await launchAtStartup.isEnabled();
      }
    } finally {
      _loading = false;
      notifyListeners();
    }
  }
}
