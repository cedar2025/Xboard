import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import '../services/app_logger.dart';
import 'connection_latency_manager.dart';
import 'macos_curl_connection_probe.dart';
import 'macos_latency_config.dart';
import 'macos_singbox_runtime.dart';

abstract interface class MacosLatencyProcess {
  int get pid;
  Stream<List<int>> get stdout;
  Stream<List<int>> get stderr;
  Future<int> get exitCode;
  bool kill([ProcessSignal signal = ProcessSignal.sigterm]);
}

typedef MacosLatencyProcessStarter = Future<MacosLatencyProcess> Function(
  String binaryPath,
  List<String> arguments,
  Map<String, String> environment,
);
typedef MacosLatencyReadinessProbe = Future<bool> Function(int apiPort);
typedef MacosLatencySelectorUpdater = Future<void> Function(
  int apiPort,
  String selectorTag,
  String nodeTag,
);
typedef MacosLatencyHttpProbe = Future<ConnectionLatencyResult> Function({
  required int proxyPort,
  required String testUrl,
  required Duration timeout,
});
typedef MacosLatencyTempDirectoryCreator = Future<Directory> Function();
typedef MacosLatencyLogger = Future<void> Function(String message);

class MacosLatencySession {
  MacosLatencySession({
    required this.binaryPath,
    required this.sourceConfig,
    required List<String> nodeTags,
    required this.testUrl,
    required this.timeoutMs,
    required int workerCount,
    MacosConfigValidator? configValidator,
    MacosLatencyProcessStarter? processStarter,
    MacosLatencyReadinessProbe? readinessProbe,
    MacosLatencySelectorUpdater? selectorUpdater,
    MacosLatencyHttpProbe? httpProbe,
    MacosLatencyTempDirectoryCreator? tempDirectoryCreator,
    MacosLatencyLogger? logger,
  })  : nodeTags = List<String>.unmodifiable(nodeTags),
        workerCount = max(1, min(workerCount, nodeTags.length)),
        _configValidator =
            configValidator ?? MacosSingBoxRuntime.validateConfig,
        _processStarter = processStarter ?? _startProcess,
        _readinessProbe = readinessProbe ?? _probeReadiness,
        _selectorUpdater = selectorUpdater ?? _updateSelector,
        _httpProbe = httpProbe,
        _tempDirectoryCreator = tempDirectoryCreator ?? _createTempDirectory,
        _logger = logger ?? AppLogger.instance.info {
    if (nodeTags.isEmpty) {
      throw ArgumentError.value(nodeTags, 'nodeTags', 'must not be empty');
    }
    if (timeoutMs <= 0) {
      throw ArgumentError.value(timeoutMs, 'timeoutMs', 'must be positive');
    }
  }

  final String binaryPath;
  final String sourceConfig;
  final List<String> nodeTags;
  final String testUrl;
  final int timeoutMs;
  final int workerCount;

  final MacosConfigValidator _configValidator;
  final MacosLatencyProcessStarter _processStarter;
  final MacosLatencyReadinessProbe _readinessProbe;
  final MacosLatencySelectorUpdater _selectorUpdater;
  final MacosLatencyHttpProbe? _httpProbe;
  final MacosLatencyTempDirectoryCreator _tempDirectoryCreator;
  final MacosLatencyLogger _logger;

  Directory? _temporaryDirectory;
  MacosLatencyProcess? _process;
  List<int> _workerPorts = const [];
  int? _apiPort;
  StreamSubscription<String>? _stdoutSubscription;
  StreamSubscription<String>? _stderrSubscription;
  Future<void>? _closeFuture;
  bool _closing = false;
  bool _unexpectedExit = false;
  bool _started = false;
  String? _lastCoreError;
  final Set<Process> _activeCurlProcesses = <Process>{};

  String? get temporaryDirectoryPath => _temporaryDirectory?.path;

  Future<Map<String, ConnectionLatencyResult>> run({
    ConnectionLatencyResultCallback? onResult,
  }) async {
    try {
      await _start();
      return await _runWorkers(onResult: onResult);
    } catch (_) {
      await close();
      rethrow;
    }
  }

  Future<void> _start() async {
    if (_started) return;

    final reservations = await _reservePorts(workerCount + 1);
    try {
      _workerPorts = reservations
          .take(workerCount)
          .map((server) => server.port)
          .toList(growable: false);
      _apiPort = reservations.last.port;
      _temporaryDirectory = await _tempDirectoryCreator();
      final configFile = File('${_temporaryDirectory!.path}/config.json');
      final config = MacosLatencyConfigBuilder.build(
        sourceConfig: sourceConfig,
        nodeTags: nodeTags,
        workerPorts: _workerPorts,
        clashApiPort: _apiPort!,
      );
      await configFile.writeAsString(jsonEncode(config), flush: true);

      final validation = await _configValidator(
        binaryPath: binaryPath,
        configPath: configFile.path,
      );
      if (!validation.isValid) {
        throw MacosLatencyException(
          '测速配置无效: ${validation.error ?? 'sing-box check failed'}',
        );
      }

      await Future.wait(reservations.map((server) => server.close()));
      final process = await _processStarter(
        binaryPath,
        ['run', '-c', configFile.path],
        <String, String>{
          ...Platform.environment,
          ...MacosSingBoxRuntime.compatibilityEnvironment,
        },
      );
      _process = process;
      _listenForCoreOutput(process);
      unawaited(process.exitCode.then((exitCode) {
        if (!_closing) {
          _unexpectedExit = true;
          unawaited(_logger(
            'macOS latency core exited unexpectedly code=$exitCode',
          ));
        }
      }));
      await _waitUntilReady();
      _started = true;
    } finally {
      for (final reservation in reservations) {
        try {
          await reservation.close();
        } catch (_) {
          // Already closed before process startup.
        }
      }
    }
  }

  Future<Map<String, ConnectionLatencyResult>> _runWorkers({
    ConnectionLatencyResultCallback? onResult,
  }) async {
    final results = <String, ConnectionLatencyResult>{};
    var nextIndex = 0;

    void record(String nodeTag, ConnectionLatencyResult result) {
      results[nodeTag] = result;
      if (onResult != null) {
        try {
          onResult(nodeTag, result);
        } catch (_) {
          // A UI callback must not stop the remaining node tests.
        }
      }
    }

    Future<void> worker(int workerIndex) async {
      while (!_closing && !_unexpectedExit) {
        final currentIndex = nextIndex;
        if (currentIndex >= nodeTags.length) return;
        nextIndex++;

        final nodeTag = nodeTags[currentIndex];
        ConnectionLatencyResult result;
        try {
          await _selectorUpdater(
            _apiPort!,
            '${MacosLatencyConfigBuilder.workerPrefix}$workerIndex',
            nodeTag,
          );
          final probe = _httpProbe;
          result = probe == null
              ? await _probeWithCurl(
                  proxyPort: _workerPorts[workerIndex],
                  testUrl: testUrl,
                  timeout: Duration(milliseconds: timeoutMs),
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
        final attempts = result.attempts;
        await _logger(
          'macOS latency node=$nodeTag url=${_safeEndpoint(testUrl)} '
          'first=${attempts.isEmpty ? -1 : attempts.first}ms '
          'second=${attempts.length < 2 ? -1 : attempts[1]}ms '
          'latency=${result.latencyMs}ms elapsed=${result.elapsedMs}ms',
        );
      }
    }

    await Future.wait(
      List.generate(workerCount, (index) => worker(index)),
    );

    for (final nodeTag in nodeTags) {
      if (!results.containsKey(nodeTag)) {
        record(
          nodeTag,
          const ConnectionLatencyResult(latencyMs: -1, elapsedMs: 0),
        );
      }
    }
    return Map<String, ConnectionLatencyResult>.unmodifiable(results);
  }

  Future<void> _waitUntilReady() async {
    final deadline = DateTime.now().add(const Duration(seconds: 3));
    while (DateTime.now().isBefore(deadline)) {
      if (_unexpectedExit) {
        throw MacosLatencyException(
          _lastCoreError == null ? '测速服务启动失败' : '测速服务启动失败: $_lastCoreError',
        );
      }
      if (await _readinessProbe(_apiPort!)) return;
      await Future<void>.delayed(const Duration(milliseconds: 100));
    }
    throw const MacosLatencyException('测速服务启动超时');
  }

  void _listenForCoreOutput(MacosLatencyProcess process) {
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
    if (!lower.contains('error') && !lower.contains('fatal')) return;
    _lastCoreError = _sanitizeCoreError(line);
  }

  Future<void> close() {
    return _closeFuture ??= _closeOwnedResources();
  }

  Future<void> _closeOwnedResources() async {
    _closing = true;
    for (final process in _activeCurlProcesses.toList(growable: false)) {
      process.kill(ProcessSignal.sigterm);
    }
    if (_activeCurlProcesses.isNotEmpty) {
      await Future<void>.delayed(const Duration(milliseconds: 200));
      for (final process in _activeCurlProcesses.toList(growable: false)) {
        process.kill(ProcessSignal.sigkill);
      }
    }
    await _stdoutSubscription?.cancel();
    await _stderrSubscription?.cancel();

    final process = _process;
    if (process != null) {
      var exited = false;
      unawaited(process.exitCode.then((_) => exited = true));
      await Future<void>.delayed(Duration.zero);
      if (!exited) {
        process.kill(ProcessSignal.sigterm);
        await Future.any<void>([
          process.exitCode.then((_) {}),
          Future<void>.delayed(const Duration(seconds: 1)),
        ]);
        await Future<void>.delayed(Duration.zero);
      }
      if (!exited) {
        process.kill(ProcessSignal.sigkill);
        await Future.any<void>([
          process.exitCode.then((_) {}),
          Future<void>.delayed(const Duration(milliseconds: 250)),
        ]);
      }
    }
    _process = null;

    final directory = _temporaryDirectory;
    if (directory != null && await directory.exists()) {
      try {
        await directory.delete(recursive: true);
      } catch (_) {
        // Best-effort cleanup of this session's own temporary directory.
      }
    }
    _temporaryDirectory = null;
    _workerPorts = const [];
    _apiPort = null;
  }

  static Future<List<ServerSocket>> _reservePorts(int count) async {
    final reservations = <ServerSocket>[];
    try {
      for (var index = 0; index < count; index++) {
        reservations.add(
          await ServerSocket.bind(InternetAddress.loopbackIPv4, 0),
        );
      }
      return reservations;
    } catch (_) {
      await Future.wait(reservations.map((server) => server.close()));
      rethrow;
    }
  }

  static Future<Directory> _createTempDirectory() {
    return Directory.systemTemp.createTemp('elephant-network-latency-');
  }

  static Future<MacosLatencyProcess> _startProcess(
    String binaryPath,
    List<String> arguments,
    Map<String, String> environment,
  ) async {
    final process = await Process.start(
      binaryPath,
      arguments,
      environment: environment,
      runInShell: false,
    );
    return _DartLatencyProcess(process);
  }

  static Future<bool> _probeReadiness(int apiPort) async {
    final client = HttpClient()
      ..connectionTimeout = const Duration(milliseconds: 300)
      ..findProxy = (_) => 'DIRECT';
    try {
      final request = await client
          .getUrl(Uri.parse('http://127.0.0.1:$apiPort/proxies'))
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

  static Future<void> _updateSelector(
    int apiPort,
    String selectorTag,
    String nodeTag,
  ) async {
    final client = HttpClient()
      ..connectionTimeout = const Duration(seconds: 1)
      ..findProxy = (_) => 'DIRECT';
    try {
      final encodedSelector = Uri.encodeComponent(selectorTag);
      final request = await client.putUrl(
        Uri.parse('http://127.0.0.1:$apiPort/proxies/$encodedSelector'),
      );
      request.headers.contentType = ContentType.json;
      request.write(jsonEncode(<String, String>{'name': nodeTag}));
      final response =
          await request.close().timeout(const Duration(seconds: 1));
      await response.drain<void>();
      if (response.statusCode != HttpStatus.ok &&
          response.statusCode != HttpStatus.noContent) {
        throw MacosLatencyException(
          '测速节点切换失败 (${response.statusCode})',
        );
      }
    } finally {
      client.close(force: true);
    }
  }

  Future<ConnectionLatencyResult> _probeWithCurl({
    required int proxyPort,
    required String testUrl,
    required Duration timeout,
  }) async {
    return MacosCurlConnectionProbe.run(
      proxyPort: proxyPort,
      testUrl: testUrl,
      timeout: timeout,
      onProcessStarted: _activeCurlProcesses.add,
      onProcessFinished: _activeCurlProcesses.remove,
    );
  }

  static String _safeEndpoint(String value) {
    final uri = Uri.tryParse(value);
    if (uri == null || uri.host.isEmpty) return '<invalid-url>';
    return Uri(
      scheme: uri.scheme,
      host: uri.host,
      port: uri.hasPort ? uri.port : null,
      path: uri.path,
    ).toString();
  }

  static String _sanitizeCoreError(String value) {
    return value
        .replaceAll(RegExp(r'\x1B\[[0-9;]*[mK]'), '')
        .replaceAll(RegExp(r'https?://\S+'), '<url>')
        .replaceAll(
          RegExp(
            r'[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
          ),
          '<id>',
        )
        .replaceAll(
          RegExp(
            r'(password|uuid|token)[=:]\s*\S+',
            caseSensitive: false,
          ),
          r'$1=<redacted>',
        )
        .trim();
  }
}

class _DartLatencyProcess implements MacosLatencyProcess {
  const _DartLatencyProcess(this._process);

  final Process _process;

  @override
  int get pid => _process.pid;

  @override
  Stream<List<int>> get stderr => _process.stderr;

  @override
  Stream<List<int>> get stdout => _process.stdout;

  @override
  Future<int> get exitCode => _process.exitCode;

  @override
  bool kill([ProcessSignal signal = ProcessSignal.sigterm]) {
    return _process.kill(signal);
  }
}

class MacosLatencyException implements Exception {
  const MacosLatencyException(this.message);

  final String message;

  @override
  String toString() => message;
}
