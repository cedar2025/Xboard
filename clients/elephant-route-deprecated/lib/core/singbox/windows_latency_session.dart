import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter/foundation.dart';

import 'connection_latency_manager.dart';
import 'windows_connection_probe.dart';
import 'windows_latency_config.dart';

typedef WindowsLatencyProbe = Future<ConnectionLatencyResult> Function({
  required int proxyPort,
  required String testUrl,
  required Duration timeout,
});

class WindowsLatencySession {
  WindowsLatencySession({
    required this.binaryPath,
    required this.sourceConfig,
    required List<String> nodeTags,
    required this.testUrl,
    required this.timeoutMs,
    required int workerCount,
    WindowsLatencyProbe? probe,
  })  : nodeTags = List<String>.unmodifiable(nodeTags),
        workerCount = max(1, min(workerCount, nodeTags.length)),
        _probe = probe {
    if (nodeTags.isEmpty) {
      throw ArgumentError.value(nodeTags, 'nodeTags', 'must not be empty');
    }
    if (timeoutMs <= 0) {
      throw ArgumentError.value(timeoutMs, 'timeoutMs', 'must be positive');
    }
  }

  static const Map<String, String> compatibilityEnvironment = {
    'ENABLE_DEPRECATED_SPECIAL_OUTBOUNDS': 'true',
    'ENABLE_DEPRECATED_LEGACY_DNS_SERVERS': 'true',
    'ENABLE_DEPRECATED_TUN_ADDRESS_X': 'true',
  };

  final String binaryPath;
  final String sourceConfig;
  final List<String> nodeTags;
  final String testUrl;
  final int timeoutMs;
  final int workerCount;
  final WindowsLatencyProbe? _probe;

  Directory? _temporaryDirectory;
  Process? _process;
  List<int> _workerPorts = const [];
  int? _apiPort;
  StreamSubscription<String>? _stdoutSubscription;
  StreamSubscription<String>? _stderrSubscription;
  Future<void>? _closeFuture;
  bool _closing = false;
  bool _unexpectedExit = false;
  String? _lastCoreError;
  final Set<Process> _activeCurlProcesses = <Process>{};

  Future<Map<String, ConnectionLatencyResult>> run({
    ConnectionLatencyResultCallback? onResult,
  }) async {
    try {
      await _start();
      return await _runWorkers(onResult: onResult);
    } finally {
      await close();
    }
  }

  Future<void> _start() async {
    final reservations = await _reservePorts(workerCount + 1);
    try {
      _workerPorts = reservations
          .take(workerCount)
          .map((server) => server.port)
          .toList(growable: false);
      _apiPort = reservations.last.port;
      _temporaryDirectory =
          await Directory.systemTemp.createTemp('elephant-windows-latency-');
      final configFile = File('${_temporaryDirectory!.path}\\config.json');
      final config = WindowsLatencyConfigBuilder.build(
        sourceConfig: sourceConfig,
        nodeTags: nodeTags,
        workerPorts: _workerPorts,
        clashApiPort: _apiPort!,
      );
      await configFile.writeAsString(jsonEncode(config), flush: true);
      await _validateConfig(configFile.path);

      await Future.wait(reservations.map((server) => server.close()));
      final process = await Process.start(
        binaryPath,
        ['run', '-c', configFile.path],
        environment: <String, String>{
          ...Platform.environment,
          ...compatibilityEnvironment,
        },
        workingDirectory: _temporaryDirectory!.path,
        runInShell: false,
      );
      _process = process;
      _listenForCoreOutput(process);
      unawaited(process.exitCode.then((exitCode) {
        if (!_closing) {
          _unexpectedExit = true;
          debugPrint(
            '[SPEED_TEST_WINDOWS] isolated core exited code=$exitCode',
          );
        }
      }));
      await _waitUntilReady();
    } finally {
      for (final reservation in reservations) {
        try {
          await reservation.close();
        } catch (_) {
          // The socket may already be closed before process startup.
        }
      }
    }
  }

  Future<void> _validateConfig(String configPath) async {
    final process = await Process.start(
      binaryPath,
      ['check', '-c', configPath],
      environment: <String, String>{
        ...Platform.environment,
        ...compatibilityEnvironment,
      },
      runInShell: false,
    );
    final output = <String>[];
    final stdoutFuture =
        utf8.decoder.bind(process.stdout).join().then(output.add);
    final stderrFuture =
        utf8.decoder.bind(process.stderr).join().then(output.add);
    int exitCode;
    try {
      exitCode = await process.exitCode.timeout(const Duration(seconds: 5));
    } on TimeoutException {
      process.kill();
      throw const WindowsLatencyException('测速配置校验超时');
    }
    await Future.wait([stdoutFuture, stderrFuture]);
    if (exitCode != 0) {
      final error = _sanitizeCoreError(output.join('\n'));
      throw WindowsLatencyException(
        error.isEmpty ? '测速配置无效' : '测速配置无效: $error',
      );
    }
  }

  Future<Map<String, ConnectionLatencyResult>> _runWorkers({
    ConnectionLatencyResultCallback? onResult,
  }) async {
    final results = <String, ConnectionLatencyResult>{};
    var nextIndex = 0;

    void record(String tag, ConnectionLatencyResult result) {
      results[tag] = result;
      try {
        onResult?.call(tag, result);
      } catch (_) {
        // UI callbacks must not stop remaining probes.
      }
    }

    Future<void> worker(int workerIndex) async {
      while (!_closing && !_unexpectedExit) {
        final index = nextIndex++;
        if (index >= nodeTags.length) return;
        final nodeTag = nodeTags[index];
        ConnectionLatencyResult result;
        try {
          await _selectNode(workerIndex, nodeTag);
          final probe = _probe;
          result = probe == null
              ? await WindowsConnectionProbe.run(
                  proxyPort: _workerPorts[workerIndex],
                  testUrl: testUrl,
                  timeout: Duration(milliseconds: timeoutMs),
                  onProcessStarted: _activeCurlProcesses.add,
                  onProcessFinished: _activeCurlProcesses.remove,
                )
              : await probe(
                  proxyPort: _workerPorts[workerIndex],
                  testUrl: testUrl,
                  timeout: Duration(milliseconds: timeoutMs),
                );
        } catch (_) {
          result = const ConnectionLatencyResult(
            latencyMs: -1,
            elapsedMs: 0,
          );
        }
        record(nodeTag, result);
        debugPrint(
          '[SPEED_TEST_WINDOWS] node=$nodeTag '
          'attempts=${result.attempts} latency=${result.latencyMs}ms '
          'elapsed=${result.elapsedMs}ms',
        );
      }
    }

    await Future.wait(List.generate(workerCount, worker));
    for (final nodeTag in nodeTags) {
      results.putIfAbsent(
        nodeTag,
        () => const ConnectionLatencyResult(latencyMs: -1, elapsedMs: 0),
      );
    }
    return Map<String, ConnectionLatencyResult>.unmodifiable(results);
  }

  Future<void> _waitUntilReady() async {
    final deadline = DateTime.now().add(const Duration(seconds: 3));
    while (DateTime.now().isBefore(deadline)) {
      if (_unexpectedExit) {
        throw WindowsLatencyException(
          _lastCoreError == null ? '测速服务启动失败' : '测速服务启动失败: $_lastCoreError',
        );
      }
      if (await _isApiReady()) return;
      await Future<void>.delayed(const Duration(milliseconds: 100));
    }
    throw const WindowsLatencyException('测速服务启动超时');
  }

  Future<bool> _isApiReady() async {
    final client = HttpClient();
    client.findProxy = (_) => 'DIRECT';
    client.connectionTimeout = const Duration(milliseconds: 300);
    try {
      final request = await client
          .getUrl(Uri.parse('http://127.0.0.1:$_apiPort/proxies'))
          .timeout(const Duration(milliseconds: 350));
      final response =
          await request.close().timeout(const Duration(milliseconds: 350));
      await response.drain<void>();
      return response.statusCode == HttpStatus.ok;
    } catch (_) {
      return false;
    } finally {
      client.close(force: true);
    }
  }

  Future<void> _selectNode(int workerIndex, String nodeTag) async {
    final client = HttpClient();
    client.findProxy = (_) => 'DIRECT';
    client.connectionTimeout = const Duration(seconds: 1);
    try {
      final selector = Uri.encodeComponent(
        '${WindowsLatencyConfigBuilder.workerPrefix}$workerIndex',
      );
      final request = await client.putUrl(
        Uri.parse('http://127.0.0.1:$_apiPort/proxies/$selector'),
      );
      request.headers.contentType = ContentType.json;
      request.write(jsonEncode(<String, String>{'name': nodeTag}));
      final response =
          await request.close().timeout(const Duration(seconds: 1));
      await response.drain<void>();
      if (response.statusCode != HttpStatus.ok &&
          response.statusCode != HttpStatus.noContent) {
        throw WindowsLatencyException(
          '测速节点切换失败 (${response.statusCode})',
        );
      }
    } finally {
      client.close(force: true);
    }
  }

  void _listenForCoreOutput(Process process) {
    _stdoutSubscription = utf8.decoder
        .bind(process.stdout)
        .transform(const LineSplitter())
        .listen(_captureCoreError);
    _stderrSubscription = utf8.decoder
        .bind(process.stderr)
        .transform(const LineSplitter())
        .listen(_captureCoreError);
  }

  void _captureCoreError(String line) {
    final lower = line.toLowerCase();
    if (lower.contains('error') || lower.contains('fatal')) {
      _lastCoreError = _sanitizeCoreError(line);
    }
  }

  Future<void> close() => _closeFuture ??= _closeOwnedResources();

  Future<void> _closeOwnedResources() async {
    _closing = true;
    for (final curl in _activeCurlProcesses.toList(growable: false)) {
      curl.kill();
    }
    _activeCurlProcesses.clear();
    final process = _process;
    if (process != null) {
      process.kill();
      try {
        await process.exitCode.timeout(const Duration(seconds: 1));
      } on TimeoutException {
        process.kill(ProcessSignal.sigkill);
      }
    }
    _process = null;
    await _stdoutSubscription?.cancel();
    await _stderrSubscription?.cancel();
    final directory = _temporaryDirectory;
    if (directory != null && await directory.exists()) {
      try {
        await directory.delete(recursive: true);
      } catch (_) {
        // Best-effort cleanup of files owned by this session.
      }
    }
    _temporaryDirectory = null;
    _workerPorts = const [];
    _apiPort = null;
  }

  static Future<List<ServerSocket>> _reservePorts(int count) async {
    final sockets = <ServerSocket>[];
    try {
      for (var index = 0; index < count; index++) {
        sockets.add(await ServerSocket.bind(InternetAddress.loopbackIPv4, 0));
      }
      return sockets;
    } catch (_) {
      await Future.wait(sockets.map((socket) => socket.close()));
      rethrow;
    }
  }

  static String _sanitizeCoreError(String value) {
    return value
        .replaceAll(RegExp(r'\x1B\[[0-9;]*[mK]'), '')
        .replaceAll(RegExp(r'https?://\S+'), '<url>')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
  }
}

class WindowsLatencyException implements Exception {
  const WindowsLatencyException(this.message);

  final String message;

  @override
  String toString() => message;
}
