import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:launch_at_startup/launch_at_startup.dart';

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
      _enabled = Platform.isWindows && await launchAtStartup.isEnabled();
    } catch (error) {
      debugPrint('Failed to read launch-at-startup state: $error');
      _enabled = false;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> setEnabled(bool enabled) async {
    if (!Platform.isWindows || _loading || _enabled == enabled) return;
    _loading = true;
    notifyListeners();
    try {
      if (enabled) {
        await launchAtStartup.enable();
      } else {
        await launchAtStartup.disable();
      }
      _enabled = await launchAtStartup.isEnabled();
    } finally {
      _loading = false;
      notifyListeners();
    }
  }
}
