# macOS VPN Lifecycle Stability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent the macOS VPN from stopping because of duplicate object disposal, make all intentional shutdown paths explicit, and prevent native cleanup commands from crashing the app.

**Architecture:** The top-level Provider is the sole owner of `VpnManager`; dependent providers cancel only resources they created. A small single-flight coordinator makes macOS stop operations idempotent, while explicit stop reasons flow from Flutter to the native runtime. Swift serializes native stop cleanup and replaces unsafe `Process.launch()` with a throwing `Process.run()` path that logs and returns errors.

**Tech Stack:** Flutter/Dart, Provider, Swift, Flutter MethodChannel, Foundation `Process`, XCTest-style source contracts through `flutter_test`.

---

### Task 1: Add stop reasons and single-flight stop coordination

**Files:**
- Create: `clients/elephant-route-deprecated/lib/core/singbox/vpn_stop_coordinator.dart`
- Create: `clients/elephant-route-deprecated/test/core/singbox/vpn_stop_coordinator_test.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/vpn_manager.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/macos_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/windows_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/real_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/mock_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/test/providers/vpn_provider_test.dart`

- [ ] **Step 1: Write the failing coordinator tests**

Create tests that start two calls before the first completer finishes, assert the task body runs once, assert both callers receive the same result, then assert a later call starts a new task after completion.

```dart
test('coalesces concurrent runs and resets after completion', () async {
  final coordinator = VpnStopCoordinator<int>();
  final first = Completer<int>();
  var calls = 0;

  Future<int> task() {
    calls++;
    return first.future;
  }

  final a = coordinator.run(task);
  final b = coordinator.run(task);
  expect(calls, 1);
  first.complete(7);
  expect(await Future.wait([a, b]), [7, 7]);

  expect(await coordinator.run(() async {
    calls++;
    return 8;
  }), 8);
  expect(calls, 2);
});
```

- [ ] **Step 2: Run the focused test and confirm failure**

Run: `cd clients/elephant-route-deprecated && flutter test test/core/singbox/vpn_stop_coordinator_test.dart --no-pub`

Expected: FAIL because `VpnStopCoordinator` does not exist.

- [ ] **Step 3: Implement the coordinator and stop reason enum**

Define `VpnStopReason` in `vpn_manager.dart` with wire values `user_toggle`, `tray_exit`, `app_terminate`, `update_install`, `node_switch`, `startup_recovery`, and `unspecified`. Change the interface to:

```dart
Future<void> stop({VpnStopReason reason = VpnStopReason.unspecified});
```

Implement `VpnStopCoordinator<T>` with a private `Future<T>? _inFlight`; `run()` returns `_inFlight` when present and clears it in `whenComplete` only if it is still the same Future.

- [ ] **Step 4: Make macOS stop single-flight**

Wrap the complete `MacosVpnService.stop()` state transition and `_runtime.stopCore(reason: reason.wireValue)` call in `VpnStopCoordinator<void>.run`. Log both the requested reason and reuse of an in-flight stop. Update other platform implementations and test fakes to accept the optional reason without changing their behavior.

- [ ] **Step 5: Run focused and interface tests**

Run: `cd clients/elephant-route-deprecated && flutter test test/core/singbox/vpn_stop_coordinator_test.dart test/providers/vpn_provider_test.dart test/core/singbox/windows_vpn_service_test.dart --no-pub`

Expected: PASS.

### Task 2: Establish one owner for `VpnManager`

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/providers/vpn_provider.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/macos_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/test/providers/vpn_provider_test.dart`
- Create: `clients/elephant-route-deprecated/test/core/singbox/macos_vpn_lifecycle_contract_test.dart`

- [ ] **Step 1: Write failing ownership tests**

Add a counting `VpnManager` fake and verify disposing `VpnProvider` cancels its state subscription but never invokes the injected manager’s `dispose()` or `stop()`.

```dart
vpnProvider.dispose();
expect(manager.disposeCalls, 0);
expect(manager.stopCalls, 0);
```

Add a source contract asserting `MacosVpnService.dispose()` closes Dart resources but contains no `stop()` call.

- [ ] **Step 2: Run the focused tests and confirm failure**

Run: `cd clients/elephant-route-deprecated && flutter test test/providers/vpn_provider_test.dart test/core/singbox/macos_vpn_lifecycle_contract_test.dart --no-pub`

Expected: FAIL because both `VpnProvider.dispose()` and `MacosVpnService.dispose()` currently initiate manager cleanup.

- [ ] **Step 3: Remove duplicate ownership**

Store the `stateStream.listen(...)` result in a `StreamSubscription<VpnState>`. In `VpnProvider.dispose()`, cancel that subscription and the traffic timer, but do not call `_vpnManager.dispose()`. Keep the existing top-level `Provider<VpnManager>(dispose: ...)` as the sole owner.

- [ ] **Step 4: Make macOS disposal resource-only**

Remove the fire-and-forget `stop()` call from `MacosVpnService.dispose()`. Mark the service disposed before closing the state controller, and make `_updateState()` return without adding events after disposal.

- [ ] **Step 5: Run ownership tests**

Run: `cd clients/elephant-route-deprecated && flutter test test/providers/vpn_provider_test.dart test/core/singbox/macos_vpn_lifecycle_contract_test.dart --no-pub`

Expected: PASS.

### Task 3: Route every intentional shutdown through an explicit reason

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/providers/vpn_provider.dart`
- Modify: `clients/elephant-route-deprecated/lib/widgets/tray_controller.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/api/services/app_update_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/services/mac_runtime_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/macos_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/test/core/services/mac_runtime_service_contract_test.dart`
- Modify: `clients/elephant-route-deprecated/test/core/singbox/macos_vpn_lifecycle_contract_test.dart`

- [ ] **Step 1: Write failing stop-source contracts**

Assert that `MacRuntimeService.stopCore` requires a reason and sends `{'reason': reason}`; tray exit uses `VpnStopReason.trayExit`; update installation sends `update_install`; node switching sends `node_switch`; ordinary button disconnect defaults to `user_toggle`.

- [ ] **Step 2: Run contracts and confirm failure**

Run: `cd clients/elephant-route-deprecated && flutter test test/core/services/mac_runtime_service_contract_test.dart test/core/singbox/macos_vpn_lifecycle_contract_test.dart --no-pub`

Expected: FAIL on missing reason arguments.

- [ ] **Step 3: Update Flutter shutdown APIs**

Change `VpnProvider.disconnect` to:

```dart
Future<void> disconnect({
  VpnStopReason reason = VpnStopReason.userToggle,
}) async {
  _stopTrafficPolling();
  await _vpnManager.stop(reason: reason);
}
```

Pass `trayExit` from `TrayController`. Update `MacRuntimeService.stopCore({required String reason})` to invoke `stopCore` with the reason map. Pass `nodeSwitch` in the macOS node-switch restart flow.

- [ ] **Step 4: Mark update installation explicitly**

Change the macOS update flow’s direct runtime call to include `{'reason': 'update_install'}`. Preserve the existing behavior of waiting for the core to stop before opening the artifact.

- [ ] **Step 5: Run focused tests**

Run: `cd clients/elephant-route-deprecated && flutter test test/providers/vpn_provider_test.dart test/core/services/mac_runtime_service_contract_test.dart test/core/singbox/macos_vpn_lifecycle_contract_test.dart --no-pub`

Expected: PASS.

### Task 4: Serialize native cleanup and eliminate `Process.launch()` crashes

**Files:**
- Modify: `clients/elephant-route-deprecated/macos/Runner/AppDelegate.swift`
- Modify: `clients/elephant-route-deprecated/test/core/services/mac_runtime_service_contract_test.dart`

- [ ] **Step 1: Add failing native source contracts**

Assert the source no longer contains `process.launch()` or `process.launchPath`, uses `process.executableURL` and `try process.run()`, logs command failures, records `reason=`, uses `app_terminate` and `startup_recovery`, and serializes `stopCoreInternal` with a dedicated lock or serial runtime queue.

- [ ] **Step 2: Run the contract and confirm failure**

Run: `cd clients/elephant-route-deprecated && flutter test test/core/services/mac_runtime_service_contract_test.dart --no-pub`

Expected: FAIL on the legacy process API and missing stop reasons.

- [ ] **Step 3: Parse and log stop reasons**

In the `stopCore` MethodChannel handler, accept `reason` with fallback `unspecified`. Pass `app_terminate` from `applicationWillTerminate`, `startup_recovery` from launch recovery, and internal reasons from start/restart cleanup. Prefix native stop logs with `Stopping macOS runtime reason=<reason>`.

- [ ] **Step 4: Make native stop cleanup mutually exclusive**

Protect the full `stopCoreInternal` body with a dedicated `NSLock` and `defer { unlock() }`. Do not hold the lock around unrelated read-only status operations. Ensure all exit paths still attempt Helper stop and proxy restoration.

- [ ] **Step 5: Replace unsafe shell execution**

Implement `runCommand` with `Process.executableURL`, `try process.run()`, pipe draining, `waitUntilExit()`, and a `catch` that logs executable, arguments, and localized error before returning a failure string. Never rethrow from cleanup.

- [ ] **Step 6: Run native contracts and compile Swift**

Run: `cd clients/elephant-route-deprecated && flutter test test/core/services/mac_runtime_service_contract_test.dart --no-pub && flutter build macos --debug`

Expected: contract PASS and debug app build exits 0.

### Task 5: Full verification and real macOS acceptance

**Files:**
- No planned file changes.

- [ ] **Step 1: Format and run static analysis**

Run: `cd clients/elephant-route-deprecated && dart format lib test && flutter analyze`

Expected: formatter exits 0 and analyzer reports `No issues found!`.

- [ ] **Step 2: Run the complete automated test suite**

Run: `cd clients/elephant-route-deprecated && flutter test --no-pub`

Expected: all Flutter tests pass with zero failures.

- [ ] **Step 3: Build both macOS configurations**

Run: `cd clients/elephant-route-deprecated && flutter build macos --debug && flutter build macos --release`

Expected: both commands exit 0 and produce `build/macos/Build/Products/Debug/ElephantRoute.app` and `build/macos/Build/Products/Release/ElephantRoute.app`.

- [ ] **Step 4: Check patch hygiene**

Run: `git diff --check && git status --short`

Expected: no whitespace errors; status contains only the planned lifecycle files and tests.

- [ ] **Step 5: Perform isolated 10-minute connection test**

Disconnect V2BOX, Clash Verge, Karing, Shadowrocket, Stash, Hiddify, and other VPN/TUN software. Start the updated app from `/Applications`, connect, verify core PID, `/proxies`, DNS and routes, then keep periodic direct HTTP probes running for at least 10 minutes. Close the window during the interval and confirm the tunnel and probes remain healthy.

- [ ] **Step 6: Verify both explicit exit paths**

Reconnect and use the tray “退出应用”; then repeat with `Command+Q`. For each path verify the native log contains the correct reason, the Elephant `sing-box` process exits, normal network access succeeds, and no Elephant-owned TUN route remains.

- [ ] **Step 7: Recheck the original crash boundary**

Trigger connect/disconnect repeatedly and confirm no new `ElephantRoute*.ips` report appears under `~/Library/Logs/DiagnosticReports`, especially no `Abort trap: 6` originating from `AppDelegate.runCommand`.
