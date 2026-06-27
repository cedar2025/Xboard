import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';

class AppLogger {
  AppLogger._();

  static final AppLogger instance = AppLogger._();

  File? _logFile;
  bool _initialized = false;

  Future<void> initialize() async {
    if (_initialized) return;
    final supportDir = await getApplicationSupportDirectory();
    final logsDir = Directory('${supportDir.path}/logs');
    if (!await logsDir.exists()) {
      await logsDir.create(recursive: true);
    }
    _logFile = File('${logsDir.path}/dart.log');
    if (!await _logFile!.exists()) {
      await _logFile!.create(recursive: true);
    }
    _initialized = true;
    await info('Logger initialized at ${_logFile!.path}');
  }

  Future<String?> getLogPath() async {
    if (!_initialized) {
      await initialize();
    }
    return _logFile?.path;
  }

  Future<void> info(String message) => _write('INFO', message);
  Future<void> warn(String message) => _write('WARN', message);
  Future<void> error(String message,
      {Object? error, StackTrace? stackTrace}) async {
    final buffer = StringBuffer(message);
    if (error != null) {
      buffer.write(' | error=$error');
    }
    if (stackTrace != null) {
      buffer.write('\n$stackTrace');
    }
    await _write('ERROR', buffer.toString());
  }

  Future<void> _write(String level, String message) async {
    if (!_initialized) {
      await initialize();
    }
    final line = '[${DateTime.now().toIso8601String()}] [$level] $message';
    debugPrint(line);
    try {
      await _logFile?.writeAsString('$line\n',
          mode: FileMode.append, flush: true);
    } catch (_) {
      // Logging must never break the main flow.
    }
  }
}
