# macOS V2BOX-Style Connection Latency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace macOS Clash `/delay` measurements with an isolated sing-box session that performs two complete HTTP requests over one reusable proxy connection and reports the lower valid duration, matching V2BOX behavior without touching V2BOX or the production VPN core.

**Architecture:** Add a platform-neutral `ConnectionLatencyManager` capability, implemented only by `MacosVpnService`. The macOS implementation builds a temporary no-TUN sing-box configuration with four private selector workers and four loopback mixed inbounds, starts and owns that process, selects nodes through its private Clash API, then sends the same URL twice through one libcurl process and returns the lower valid duration under one five-second deadline. `NodeProvider` uses this batch capability only on macOS; Android, Windows, web, and mock behavior remain on the existing path.

**Tech Stack:** Flutter/Dart, `dart:io` (`Process`, `ServerSocket`), macOS `/usr/bin/curl`, sing-box 1.12.25 Clash API, Flutter test.

---

## Task 1: Define the optional connection-latency capability

**Files:**

- Create: `lib/core/singbox/connection_latency_manager.dart`
- Test: `test/core/singbox/connection_latency_manager_test.dart`

- [ ] **Step 1: Write the failing capability contract test**

```dart
import 'package:elephant_network/core/singbox/connection_latency_manager.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('connection latency result exposes latency and elapsed time', () {
    const result = ConnectionLatencyResult(
      latencyMs: 253,
      elapsedMs: 1164,
    );

    expect(result.latencyMs, 253);
    expect(result.elapsedMs, 1164);
  });
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run:

```bash
flutter test test/core/singbox/connection_latency_manager_test.dart
```

Expected: compile failure because `connection_latency_manager.dart` does not exist.

- [ ] **Step 3: Add the capability and immutable result type**

```dart
typedef ConnectionLatencyResultCallback = void Function(
  String nodeTag,
  ConnectionLatencyResult result,
);

abstract interface class ConnectionLatencyManager {
  Future<Map<String, ConnectionLatencyResult>> testConnectionLatencies({
    required List<String> nodeTags,
    required String testUrl,
    required int timeoutMs,
    required int concurrency,
    ConnectionLatencyResultCallback? onResult,
  });

  Future<void> stopConnectionLatencyTest();
}

class ConnectionLatencyResult {
  const ConnectionLatencyResult({
    required this.latencyMs,
    required this.elapsedMs,
  });

  final int latencyMs;
  final int elapsedMs;
}
```

- [ ] **Step 4: Run the test and confirm it passes**

Run:

```bash
flutter test test/core/singbox/connection_latency_manager_test.dart
```

Expected: `All tests passed!`

- [ ] **Step 5: Commit the capability**

```bash
git add lib/core/singbox/connection_latency_manager.dart test/core/singbox/connection_latency_manager_test.dart
git commit -m "feat(mac): define connection latency capability"
```

## Task 2: Build a safe isolated sing-box speed-test configuration

**Files:**

- Create: `lib/core/singbox/macos_latency_config.dart`
- Create: `test/core/singbox/macos_latency_config_test.dart`

- [ ] **Step 1: Write failing tests for the isolated configuration**

Cover all of these assertions:

```dart
final output = MacosLatencyConfigBuilder.build(
  sourceConfig: sourceConfig,
  nodeTags: const ['node-a', 'node-b'],
  workerPorts: const [31001, 31002],
  clashApiPort: 31003,
);

expect(output['inbounds'], hasLength(2));
expect(output['inbounds'][0], containsPair('listen', '127.0.0.1'));
expect(output['inbounds'][0], containsPair('type', 'mixed'));
expect(output['inbounds'], isNot(contains(predicate((v) => v['type'] == 'tun'))));
expect(output['experimental']['clash_api']['external_controller'],
    '127.0.0.1:31003');
expect(output['experimental'], isNot(contains('cache_file')));
expect(output, isNot(contains('use_tun_mode')));
expect(output['route']['rules'], containsAll([
  {'inbound': ['__elephant_latency_in_0'], 'outbound': '__elephant_latency_worker_0'},
  {'inbound': ['__elephant_latency_in_1'], 'outbound': '__elephant_latency_worker_1'},
]));
```

Also assert:

- four requested workers produce exactly four selector outbounds;
- selectors contain only concrete existing node tags;
- unknown node tags are rejected with `ArgumentError`;
- source config is not mutated;
- original TUN inbounds and production Clash API port are absent;
- node credentials are preserved in memory but never printed by the builder.

- [ ] **Step 2: Run the tests and confirm they fail**

Run:

```bash
flutter test test/core/singbox/macos_latency_config_test.dart
```

Expected: compile failure because `MacosLatencyConfigBuilder` does not exist.

- [ ] **Step 3: Implement the pure builder**

Implementation rules:

```dart
class MacosLatencyConfigBuilder {
  static const inboundPrefix = '__elephant_latency_in_';
  static const workerPrefix = '__elephant_latency_worker_';

  static Map<String, dynamic> build({
    required String sourceConfig,
    required List<String> nodeTags,
    required List<int> workerPorts,
    required int clashApiPort,
  }) {
    // Decode to a fresh map, remove use_tun_mode, replace all inbounds,
    // retain concrete node/direct outbounds, append private selectors,
    // replace route rules with inbound-to-worker rules, set a private Clash API,
    // remove cache_file, and set log level to warn.
  }
}
```

Use selector objects of this exact shape:

```dart
{
  'type': 'selector',
  'tag': '__elephant_latency_worker_0',
  'outbounds': concreteNodeTags,
  'default': concreteNodeTags.first,
}
```

Do not add TUN fields, `auto_route`, system proxy settings, or production port `9090`.

- [ ] **Step 4: Run builder tests**

Run:

```bash
flutter test test/core/singbox/macos_latency_config_test.dart
```

Expected: `All tests passed!`

- [ ] **Step 5: Validate a generated fixture with sing-box 1.12.25**

Add a test fixture based on a redacted valid source config and write its generated JSON to a temporary file. Run the bundled binary check from a test or shell helper:

```bash
ENABLE_DEPRECATED_SPECIAL_OUTBOUNDS=true \
ENABLE_DEPRECATED_LEGACY_DNS_SERVERS=true \
ENABLE_DEPRECATED_TUN_ADDRESS_X=true \
assets/bin/sing-box-darwin-arm64 check -c /tmp/elephant-latency-config.json
```

Expected: exit code `0`.

- [ ] **Step 6: Commit configuration generation**

```bash
git add lib/core/singbox/macos_latency_config.dart test/core/singbox/macos_latency_config_test.dart
git commit -m "feat(mac): build isolated latency core config"
```

## Task 3: Implement the V2BOX-compatible two-request probe

**Files:**

- Create: `lib/core/singbox/macos_curl_connection_probe.dart`
- Create: `test/core/singbox/macos_curl_connection_probe_test.dart`

- [ ] **Step 1: Write failing tests for timing semantics**

Test the libcurl write-out parser with two complete transfers:

```dart
final result = MacosCurlConnectionProbe.parseOutput(
  '204 1.163000\n204 0.260000\n',
  elapsedMs: 1450,
);
expect(result.latencyMs, 260);
expect(result.attempts, [1163, 260]);
```

Also assert:

- one curl process receives the same URL twice;
- one failed attempt plus one valid attempt returns the valid duration;
- two failed attempts return `-1`;
- the five-second deadline covers both attempts together;
- cancellation terminates only the owned curl PID.

- [ ] **Step 2: Run tests and confirm they fail**

Run:

```bash
flutter test test/core/singbox/macos_curl_connection_probe_test.dart
```

Expected: compile failure because `MacosCurlConnectionProbe` does not exist.

- [ ] **Step 3: Implement sequential min-valid behavior**

The actual HTTP layer starts one curl process per node and passes the same URL
twice so libcurl reuses its connection. Parse `%{http_code} %{time_total}` for
each transfer, accept HTTP 200/204, and return the lower valid value.

```text
/usr/bin/curl --silent --show-error --location \
  --proxy http://127.0.0.1:$workerPort \
  --max-time 5 --write-out '%{http_code} %{time_total}\n' \
  $testUrl $testUrl
```

- [ ] **Step 4: Run probe tests**

Run:

```bash
flutter test test/core/singbox/macos_curl_connection_probe_test.dart
```

Expected: `All tests passed!`

- [ ] **Step 5: Commit probe semantics**

```bash
git add lib/core/singbox/macos_curl_connection_probe.dart test/core/singbox/macos_curl_connection_probe_test.dart
git commit -m "feat(mac): match v2box connection probe timing"
```

## Task 4: Add an owned temporary sing-box latency session

**Files:**

- Create: `lib/core/singbox/macos_latency_session.dart`
- Create: `test/core/singbox/macos_latency_session_test.dart`
- Modify: `lib/core/singbox/macos_singbox_runtime.dart`

- [ ] **Step 1: Write failing lifecycle tests with injected runtime operations**

Introduce narrow dependencies so tests never start or kill a real process:

```dart
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
```

Cover:

- config is validated before the process starts;
- readiness waits on only the private Clash API port;
- unexpected process exit aborts queued nodes immediately;
- `close()` sends SIGTERM, escalates only its owned PID to SIGKILL after one second, and deletes only its own temporary directory;
- repeated `close()` is safe;
- no call path invokes `MacRuntimeService.stopCore`, changes system proxy, changes routes, or references V2BOX;
- four workers process at most four nodes simultaneously;
- each worker selects a node with `PUT /proxies/{worker}` before probing;
- per-node callback is emitted as soon as a result is available.

- [ ] **Step 2: Run lifecycle tests and confirm they fail**

Run:

```bash
flutter test test/core/singbox/macos_latency_session_test.dart
```

Expected: compile failure because `MacosLatencySession` does not exist.

- [ ] **Step 3: Make runtime validation injectable without changing production defaults**

Add typedefs in `macos_singbox_runtime.dart`:

```dart
typedef MacosConfigValidator = Future<MacosConfigValidationResult> Function({
  required String binaryPath,
  required String configPath,
});
```

The default remains `MacosSingBoxRuntime.validateConfig` and retains all three compatibility environment variables.

- [ ] **Step 4: Implement session startup and private ports**

`MacosLatencySession.start()` must:

1. allocate `workerCount + 1` loopback ports with `ServerSocket.bind(InternetAddress.loopbackIPv4, 0)`;
2. close reservations immediately before starting the process;
3. create a uniquely owned directory using `Directory.systemTemp.createTemp('elephant-network-latency-')`;
4. write only `config.json` there;
5. validate with the bundled core;
6. start `sing-box run -c config.json` with:

```dart
{
  ...Platform.environment,
  ...MacosSingBoxRuntime.compatibilityEnvironment,
}
```

7. capture only a rolling sanitized last error line;
8. wait up to three seconds for `GET http://127.0.0.1:{privateApiPort}/proxies`, while racing process exit.

- [ ] **Step 5: Implement worker probing**

Each worker starts one fresh `/usr/bin/curl` client for each node and passes the
same URL twice in one invocation, allowing libcurl to reuse the CONNECT/TLS
transport for the second attempt:

```text
/usr/bin/curl --proxy http://127.0.0.1:$workerPort \
  --max-time 5 --write-out '%{http_code} %{time_total}\n' \
  $testUrl $testUrl
```

Before each node, select it through the private API:

```http
PUT /proxies/__elephant_latency_worker_0
Content-Type: application/json

{"name":"node-a"}
```

Parse the two write-out lines, accept only HTTP 200/204, and select the lower
valid duration. The session enforces one five-second total process deadline,
then terminates only that owned curl PID. A new curl process is used for the next
node so no pooled connection crosses selector choices.

- [ ] **Step 6: Add safe diagnostics**

Log only:

```text
macOS latency node=<tag> url=<scheme://host/path> latency=<ms> elapsed=<ms>
```

Never log JSON configuration, outbound maps, server addresses, passwords, UUIDs, TLS keys, or subscription URLs. Sanitize sing-box error lines before returning them.

- [ ] **Step 7: Run lifecycle and existing runtime tests**

Run:

```bash
flutter test test/core/singbox/macos_latency_session_test.dart \
  test/core/singbox/macos_singbox_runtime_test.dart
```

Expected: `All tests passed!`

- [ ] **Step 8: Commit the session**

```bash
git add lib/core/singbox/macos_latency_session.dart \
  lib/core/singbox/macos_singbox_runtime.dart \
  test/core/singbox/macos_latency_session_test.dart
git commit -m "feat(mac): run isolated connection latency core"
```

## Task 5: Integrate the session into `MacosVpnService`

**Files:**

- Modify: `lib/core/singbox/macos_vpn_service.dart`
- Create: `test/core/singbox/macos_vpn_service_latency_test.dart`

- [ ] **Step 1: Write failing service integration tests**

Use an injected `MacosLatencySessionFactory` and assert:

- `MacosVpnService` implements `ConnectionLatencyManager`;
- it passes `_lastSanitizedConfig`, `_singboxBinPath`, test URL, timeout, and concurrency to the session;
- starting a new test closes only the previous latency session;
- `stopConnectionLatencyTest()` closes only the latency session;
- `stopSpeedTest()` delegates to `stopConnectionLatencyTest()`;
- production `stop()` closes the owned latency session before stopping the main
  core, without broad process matching or touching V2BOX;
- no service latency path calls `_runtime.stopCore()`.

- [ ] **Step 2: Run the integration test and confirm it fails**

Run:

```bash
flutter test test/core/singbox/macos_vpn_service_latency_test.dart
```

Expected: compile/test failure because the service lacks the capability.

- [ ] **Step 3: Implement the capability**

Change the declaration:

```dart
class MacosVpnService implements VpnManager, ConnectionLatencyManager {
```

Add one owned field:

```dart
MacosLatencySession? _latencySession;
```

`testConnectionLatencies()` must reject missing sanitized config/core path with a readable exception, close any previous owned session, create/start a new session, run the batch, and always close it in `finally`. `stopSpeedTest()` and `stopConnectionLatencyTest()` are idempotent. `dispose()` schedules owned latency cleanup without touching the production process.

- [ ] **Step 4: Run service integration tests**

Run:

```bash
flutter test test/core/singbox/macos_vpn_service_latency_test.dart
```

Expected: `All tests passed!`

- [ ] **Step 5: Commit service integration**

```bash
git add lib/core/singbox/macos_vpn_service.dart test/core/singbox/macos_vpn_service_latency_test.dart
git commit -m "feat(mac): expose isolated latency session"
```

## Task 6: Route only macOS node tests through the new capability

**Files:**

- Modify: `lib/providers/node_provider.dart`
- Create: `test/providers/node_provider_latency_test.dart`
- Modify: `test/core/singbox/latency_test_policy_test.dart`

- [ ] **Step 1: Write failing provider behavior tests**

Create a fake `VpnManager` that also implements `ConnectionLatencyManager` and assert:

- macOS calls `testConnectionLatencies()` once for the full concrete-node batch;
- endpoint is Google when unset or equal to the old Cloudflare default;
- a custom endpoint is preserved;
- timeout is `5000` and concurrency is `4`;
- incremental callbacks update the matching UI node immediately;
- a result of `-1` renders as timeout;
- native latency updates remain ignored during and briefly after active testing;
- non-macOS continues to call the existing Clash `/delay` implementation;
- the isolated macOS path does not wait for production port `9090` readiness.

- [ ] **Step 2: Run tests and confirm they fail**

Run:

```bash
flutter test test/providers/node_provider_latency_test.dart \
  test/core/singbox/latency_test_policy_test.dart
```

Expected: macOS batch expectations fail before integration.

- [ ] **Step 3: Add a platform-injectable decision helper**

Because host tests may not run on macOS, keep platform detection at the edge and extract the dispatch decision into a testable method/helper. Production passes `Platform.isMacOS`; tests pass explicit booleans. Do not fake or mutate `Platform` globals.

- [ ] **Step 4: Integrate the batch path**

In `testAllLatencies()`:

```dart
if (isMacOS && _vpnManager is ConnectionLatencyManager) {
  final manager = _vpnManager as ConnectionLatencyManager;
  final results = await manager.testConnectionLatencies(
    nodeTags: realNodes.map((node) => node.name).toList(growable: false),
    testUrl: probeUrls.single,
    timeoutMs: LatencyTestPolicy.v2boxConnectionTimeoutMs,
    concurrency: LatencyTestPolicy.concurrency,
    onResult: _applyConnectionLatencyResult,
  );
  // Apply any final results not already emitted.
} else {
  await _testNodesConcurrently(
    realNodes,
    probeUrls,
    LatencyTestPolicy.timeoutMsFor(latencyProfile),
    LatencyTestPolicy.concurrency,
  );
}
```

Skip `_waitForLocalClashApi()` only for the isolated macOS capability. Keep the existing gate and implementation for Android and Windows.

- [ ] **Step 5: Replace new macOS diagnostics with `AppLogger`**

Record node tag, redacted endpoint, returned latency, and total elapsed time. Do not add configuration or credential logging. Existing unrelated debug logging can remain outside this task.

- [ ] **Step 6: Run provider and policy tests**

Run:

```bash
flutter test test/providers/node_provider_latency_test.dart \
  test/core/singbox/latency_test_policy_test.dart
```

Expected: `All tests passed!`

- [ ] **Step 7: Commit provider integration**

```bash
git add lib/providers/node_provider.dart \
  test/providers/node_provider_latency_test.dart \
  test/core/singbox/latency_test_policy_test.dart
git commit -m "feat(mac): use v2box-style node latency tests"
```

## Task 7: Regression verification and local macOS build

**Files:**

- Modify only if a regression is found; do not touch Android/Windows behavior intentionally.

- [ ] **Step 1: Format changed Dart files**

```bash
dart format lib/core/singbox lib/providers/node_provider.dart \
  test/core/singbox test/providers
```

- [ ] **Step 2: Run focused tests**

```bash
flutter test test/core/singbox/connection_latency_manager_test.dart \
  test/core/singbox/macos_latency_config_test.dart \
  test/core/singbox/macos_curl_connection_probe_test.dart \
  test/core/singbox/macos_latency_session_test.dart \
  test/core/singbox/macos_vpn_service_latency_test.dart \
  test/providers/node_provider_latency_test.dart \
  test/core/singbox/latency_test_policy_test.dart
```

Expected: `All tests passed!`

- [ ] **Step 3: Run full Flutter checks**

```bash
flutter analyze
flutter test
```

Expected: analyze exits `0`; full tests report `All tests passed!`.

- [ ] **Step 4: Run existing contract tests and whitespace check**

Discover the repository’s existing Node contract command from `package.json`/scripts, then run it unchanged. Also run:

```bash
git diff --check
```

Expected: all contract tests pass and `git diff --check` prints nothing.

- [ ] **Step 5: Build the ARM64 beta artifact**

```bash
./build_macos_beta.sh
```

Expected: ARM64 App/DMG build succeeds, signing verification succeeds, and the bundled binary reports `sing-box version 1.12.25`.

- [ ] **Step 6: Install without manipulating V2BOX**

Install the generated local beta using the existing build script/install flow. Do not quit, disconnect, kill, reconfigure, or automate V2BOX. Do not start Elephant Network TUN while V2BOX owns the active TUN. App launch and isolated speed-test checks are allowed because the temporary core has no TUN or system proxy changes.

- [ ] **Step 7: Runtime acceptance**

With V2BOX still connected:

1. confirm the app launches;
2. run one Elephant Network node-list speed test;
3. verify temporary sing-box uses private loopback ports, then exits and removes its temporary directory;
4. confirm V2BOX remains connected throughout;
5. compare five alternating measurements on the same node and Google endpoint, excluding the first warm-up;
6. accept when Elephant Network median does not remain above V2BOX median by more than `max(100ms, 30%)`.

- [ ] **Step 8: Final scope review**

Review:

```bash
git status --short
git diff --stat
git diff -- lib/core/singbox lib/providers/node_provider.dart test
```

Confirm no server API, subscription format, Android, Windows, website upload, or release publishing changes were introduced.

- [ ] **Step 9: Final implementation commit**

If verification required small uncommitted fixes:

```bash
git add <only-the-reviewed-fix-files>
git commit -m "fix(mac): finalize v2box-style latency testing"
```

## Plan self-review

- [ ] Every requirement in `docs/superpowers/specs/2026-07-13-macos-v2box-latency-design.md` maps to a task above.
- [ ] No step disconnects or modifies V2BOX.
- [ ] No step modifies the official sing-box 1.12.25 binary.
- [ ] The five-second timeout is one total per-node budget, not five seconds per request.
- [ ] The same libcurl process performs both sequential requests for a node.
- [ ] At most four isolated selector/inbound workers run concurrently.
- [ ] Cleanup is scoped to the temporary session’s PID, clients, ports, and directory.
- [ ] Logs contain no credentials or full configuration.
- [ ] Placeholder scan returns no `TODO`, `TBD`, `FIXME`, `...`, or pseudo-code markers in production files.
- [ ] New method signatures and result types are consistent across service, provider, fakes, and tests.
