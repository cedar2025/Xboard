import 'dart:async';
import 'dart:io';

import 'package:elephant_network/core/singbox/connection_latency_manager.dart';
import 'package:elephant_network/core/singbox/macos_latency_session.dart';
import 'package:elephant_network/core/singbox/macos_singbox_runtime.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const sourceConfig = '''
{
  "outbounds": [
    {"type":"vless","tag":"node-a","server":"a.invalid","server_port":443,"uuid":"a"},
    {"type":"vless","tag":"node-b","server":"b.invalid","server_port":443,"uuid":"b"},
    {"type":"vless","tag":"node-c","server":"c.invalid","server_port":443,"uuid":"c"},
    {"type":"vless","tag":"node-d","server":"d.invalid","server_port":443,"uuid":"d"},
    {"type":"vless","tag":"node-e","server":"e.invalid","server_port":443,"uuid":"e"},
    {"type":"direct","tag":"direct"}
  ]
}
''';

  test('validates configuration before starting the process', () async {
    var starts = 0;
    final session = MacosLatencySession(
      binaryPath: '/tmp/sing-box',
      sourceConfig: sourceConfig,
      nodeTags: const ['node-a'],
      testUrl: 'https://www.gstatic.com/generate_204',
      timeoutMs: 5000,
      workerCount: 1,
      configValidator: ({required binaryPath, required configPath}) async =>
          const MacosConfigValidationResult.invalid('invalid fixture'),
      processStarter: (binaryPath, arguments, environment) async {
        starts++;
        return _FakeLatencyProcess();
      },
      readinessProbe: (apiPort) async => true,
    );

    await expectLater(session.run(), throwsA(isA<MacosLatencyException>()));
    expect(starts, 0);
    await session.close();
  });

  test('limits node probes to four concurrent workers', () async {
    final process = _FakeLatencyProcess();
    var inFlight = 0;
    var maxInFlight = 0;
    final session = _session(
      process: process,
      nodeTags: const [
        'node-a',
        'node-b',
        'node-c',
        'node-d',
        'node-e',
      ],
      workerCount: 4,
      httpProbe: (
          {required proxyPort, required testUrl, required timeout}) async {
        inFlight++;
        maxInFlight = inFlight > maxInFlight ? inFlight : maxInFlight;
        await Future<void>.delayed(const Duration(milliseconds: 20));
        inFlight--;
        return const ConnectionLatencyResult(
          latencyMs: 100,
          elapsedMs: 120,
          attempts: [400, 100],
        );
      },
    );

    final results = await session.run();

    expect(results, hasLength(5));
    expect(maxInFlight, 4);
    await session.close();
  });

  test('selects a node before probing and emits incremental results', () async {
    final process = _FakeLatencyProcess();
    final events = <String>[];
    final callbacks = <String>[];
    final session = _session(
      process: process,
      nodeTags: const ['node-a', 'node-b'],
      workerCount: 1,
      selectorUpdater: (apiPort, selectorTag, nodeTag) async {
        events.add('select:$selectorTag:$nodeTag');
      },
      httpProbe: (
          {required proxyPort, required testUrl, required timeout}) async {
        events.add('probe:$proxyPort');
        return const ConnectionLatencyResult(
          latencyMs: 250,
          elapsedMs: 800,
          attempts: [800, 250],
        );
      },
    );

    await session.run(
      onResult: (nodeTag, result) => callbacks.add(nodeTag),
    );

    expect(events[0], 'select:__elephant_latency_worker_0:node-a');
    expect(events[1], startsWith('probe:'));
    expect(events[2], 'select:__elephant_latency_worker_0:node-b');
    expect(events[3], startsWith('probe:'));
    expect(callbacks, ['node-a', 'node-b']);
    await session.close();
  });

  test('unexpected core exit stops scheduling and marks remaining nodes',
      () async {
    final process = _FakeLatencyProcess();
    var probes = 0;
    final session = _session(
      process: process,
      nodeTags: const ['node-a', 'node-b', 'node-c'],
      workerCount: 1,
      httpProbe: (
          {required proxyPort, required testUrl, required timeout}) async {
        probes++;
        process.completeExit(23);
        await Future<void>.delayed(Duration.zero);
        return const ConnectionLatencyResult(
          latencyMs: 100,
          elapsedMs: 100,
          attempts: [100, 100],
        );
      },
    );

    final results = await session.run();

    expect(probes, 1);
    expect(results['node-a']?.latencyMs, 100);
    expect(results['node-b']?.latencyMs, -1);
    expect(results['node-c']?.latencyMs, -1);
    await session.close();
  });

  test('close is idempotent and kills only the owned process', () async {
    final process = _FakeLatencyProcess(completeWhenKilled: true);
    final session = _session(
      process: process,
      nodeTags: const ['node-a'],
      workerCount: 1,
    );

    await session.run();
    await Future.wait([session.close(), session.close()]);

    expect(process.signals, [ProcessSignal.sigterm]);
    expect(session.temporaryDirectoryPath, isNull);
  });
}

MacosLatencySession _session({
  required _FakeLatencyProcess process,
  required List<String> nodeTags,
  required int workerCount,
  MacosLatencySelectorUpdater? selectorUpdater,
  MacosLatencyHttpProbe? httpProbe,
}) {
  return MacosLatencySession(
    binaryPath: '/tmp/sing-box',
    sourceConfig: '''
{
  "outbounds": [
    {"type":"vless","tag":"node-a","server":"a.invalid","server_port":443,"uuid":"a"},
    {"type":"vless","tag":"node-b","server":"b.invalid","server_port":443,"uuid":"b"},
    {"type":"vless","tag":"node-c","server":"c.invalid","server_port":443,"uuid":"c"},
    {"type":"vless","tag":"node-d","server":"d.invalid","server_port":443,"uuid":"d"},
    {"type":"vless","tag":"node-e","server":"e.invalid","server_port":443,"uuid":"e"},
    {"type":"direct","tag":"direct"}
  ]
}
''',
    nodeTags: nodeTags,
    testUrl: 'https://www.gstatic.com/generate_204',
    timeoutMs: 5000,
    workerCount: workerCount,
    configValidator: ({required binaryPath, required configPath}) async =>
        const MacosConfigValidationResult.valid(),
    processStarter: (binaryPath, arguments, environment) async => process,
    readinessProbe: (apiPort) async => true,
    selectorUpdater:
        selectorUpdater ?? (apiPort, selectorTag, nodeTag) async {},
    httpProbe: httpProbe ??
        ({required proxyPort, required testUrl, required timeout}) async =>
            const ConnectionLatencyResult(
              latencyMs: 200,
              elapsedMs: 500,
              attempts: [500, 200],
            ),
    logger: (message) async {},
  );
}

class _FakeLatencyProcess implements MacosLatencyProcess {
  _FakeLatencyProcess({this.completeWhenKilled = true});

  final bool completeWhenKilled;
  final _exit = Completer<int>();
  final signals = <ProcessSignal>[];

  @override
  int get pid => 4242;

  @override
  Stream<List<int>> get stderr => const Stream.empty();

  @override
  Stream<List<int>> get stdout => const Stream.empty();

  @override
  Future<int> get exitCode => _exit.future;

  @override
  bool kill([ProcessSignal signal = ProcessSignal.sigterm]) {
    signals.add(signal);
    if (completeWhenKilled && !_exit.isCompleted) {
      _exit.complete(0);
    }
    return true;
  }

  void completeExit(int code) {
    if (!_exit.isCompleted) _exit.complete(code);
  }
}
