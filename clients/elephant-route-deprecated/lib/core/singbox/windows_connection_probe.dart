import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'connection_latency_manager.dart';

typedef WindowsCurlProcessCallback = void Function(Process process);

class WindowsConnectionProbe {
  const WindowsConnectionProbe._();

  static Future<ConnectionLatencyResult> run({
    required int proxyPort,
    required String testUrl,
    required Duration timeout,
    WindowsCurlProcessCallback? onProcessStarted,
    WindowsCurlProcessCallback? onProcessFinished,
  }) async {
    final uri = Uri.tryParse(testUrl);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw const FormatException('invalid latency test URL');
    }

    final stopwatch = Stopwatch()..start();
    Process? process;
    var processExited = false;
    var output = '';
    try {
      process = await Process.start(
        resolveCurlExecutable(),
        buildArguments(
          proxyPort: proxyPort,
          testUrl: testUrl,
          timeout: timeout,
        ),
        runInShell: false,
      );
      onProcessStarted?.call(process);
      final stdoutFuture = utf8.decoder.bind(process.stdout).join();
      final stderrFuture = process.stderr.drain<void>();
      final remaining = timeout - stopwatch.elapsed;
      if (remaining <= Duration.zero) {
        process.kill();
      } else {
        try {
          await process.exitCode.timeout(remaining);
          processExited = true;
        } on TimeoutException {
          process.kill();
          try {
            await process.exitCode.timeout(const Duration(milliseconds: 250));
            processExited = true;
          } on TimeoutException {
            process.kill(ProcessSignal.sigkill);
          }
        }
      }
      output = await stdoutFuture;
      try {
        await stderrFuture;
      } catch (_) {
        // stderr is diagnostic-only and may close during cancellation.
      }
    } catch (_) {
      // A missing curl binary or a cancelled child is a node timeout.
    } finally {
      stopwatch.stop();
      if (process != null && processExited) {
        onProcessFinished?.call(process);
      }
    }
    return parseOutput(output, elapsedMs: stopwatch.elapsedMilliseconds);
  }

  static String resolveCurlExecutable() {
    final systemRoot = Platform.environment['SystemRoot'] ?? r'C:\Windows';
    final bundledByWindows = '$systemRoot\\System32\\curl.exe';
    return File(bundledByWindows).existsSync() ? bundledByWindows : 'curl.exe';
  }

  static List<String> buildArguments({
    required int proxyPort,
    required String testUrl,
    required Duration timeout,
  }) {
    final timeoutSeconds =
        (timeout.inMilliseconds / Duration.millisecondsPerSecond)
            .toStringAsFixed(3);
    return <String>[
      '--silent',
      '--show-error',
      '--location',
      '--output',
      'NUL',
      '--proxy',
      'http://127.0.0.1:$proxyPort',
      '--connect-timeout',
      timeoutSeconds,
      '--max-time',
      timeoutSeconds,
      '--write-out',
      '%{http_code} %{time_total}\n',
      // One curl process with two URLs is intentional: libcurl reuses the
      // established proxy and TLS connection for the second transfer.
      testUrl,
      testUrl,
    ];
  }

  static ConnectionLatencyResult parseOutput(
    String output, {
    required int elapsedMs,
  }) {
    final attempts = <int>[];
    for (final rawLine in const LineSplitter().convert(output)) {
      final parts = rawLine.trim().split(RegExp(r'\s+'));
      if (parts.length != 2) continue;
      final statusCode = int.tryParse(parts[0]);
      final seconds = double.tryParse(parts[1]);
      if (statusCode == null || seconds == null) continue;
      final latencyMs = (seconds * Duration.millisecondsPerSecond).round();
      attempts.add(
        statusCode == HttpStatus.ok || statusCode == HttpStatus.noContent
            ? latencyMs
            : -1,
      );
    }

    final valid = attempts.where((latency) => latency > 0);
    final selected = valid.isEmpty
        ? -1
        : valid.reduce((best, latency) => latency < best ? latency : best);
    return ConnectionLatencyResult(
      latencyMs: selected,
      elapsedMs: elapsedMs,
      attempts: List<int>.unmodifiable(attempts),
    );
  }
}
