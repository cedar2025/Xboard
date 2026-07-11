# macOS Android Feature Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the unsigned Apple Silicon macOS build use the same current product flows as Android, resolve the runtime domain before app startup, check for updates automatically, remove avatars and diagnostics UI, and publish exactly one DMG.

**Architecture:** A new `AppBootstrap` owns the single `DomainResolver` and injects the resolved URL into both `DioClient` and `AppUpdateService` before `runApp`. Startup update checking moves to `SplashScreen`, while dashboard/profile/node/share behavior remains shared across Android and macOS except for required desktop system controls. Packaging stays ad-hoc signed and ARM64-only, but cleans the export directory so the release contains only the DMG.

**Tech Stack:** Flutter/Dart, Provider, Dio, macOS Swift/SMAppService, shell packaging, Flutter tests, codesign/hdiutil.

---

### Task 1: Bootstrap one resolved runtime domain

**Files:**
- Create: `clients/elephant-route-deprecated/lib/core/app_bootstrap.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/api/dio_client.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/api/services/app_update_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/providers/app_update_provider.dart`
- Modify: `clients/elephant-route-deprecated/lib/main.dart`
- Test: `clients/elephant-route-deprecated/test/core/app_bootstrap_test.dart`

- [x] **Step 1: Write a failing bootstrap contract test**

Create a fake `DomainResolver` whose `resolve()` returns `https://runtime.example/`, then assert `AppBootstrap.initialize()` calls it once and both injected clients begin with that URL.

- [x] **Step 2: Run the focused test and verify it fails**

Run: `flutter test test/core/app_bootstrap_test.dart --no-pub`

Expected: FAIL because `AppBootstrap` and injectable initial base URLs do not exist.

- [x] **Step 3: Implement the shared bootstrap**

Add `AppBootstrap.initialize({DomainResolver? domainResolver})`, await `resolver.resolve()`, construct `DioClient(domainResolver: resolver, initialBaseUrl: resolvedUrl)`, and construct `AppUpdateService(domainResolver: resolver, initialBaseUrl: resolvedUrl)`. Preserve an explicit `APP_DISTRIBUTION_URL` override for update distribution.

- [x] **Step 4: Inject bootstrap dependencies before the widget tree**

In `main()`, await `AppBootstrap.initialize()` before desktop window setup and `runApp`. Make `MyApp` require the bootstrap, register `Provider.value(value: bootstrap.dioClient)`, and create `AppUpdateProvider(service: bootstrap.appUpdateService)`.

- [x] **Step 5: Run the focused test and verify it passes**

Run: `flutter test test/core/app_bootstrap_test.dart --no-pub`

Expected: PASS.

### Task 2: Check updates during every startup

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/main.dart`
- Modify: `clients/elephant-route-deprecated/lib/widgets/main_scaffold.dart`
- Test: `clients/elephant-route-deprecated/test/widgets/startup_update_contract_test.dart`

- [x] **Step 1: Write a failing startup source contract**

Assert that `main.dart` initializes `AppBootstrap` before `runApp`, `SplashScreen` sends heartbeat and checks for updates, and `main_scaffold.dart` no longer owns the one-time startup check.

- [x] **Step 2: Run the focused test and verify it fails**

Run: `flutter test test/widgets/startup_update_contract_test.dart --no-pub`

Expected: FAIL because update checking currently starts only after login in `MainScaffold`.

- [x] **Step 3: Move the update gate to SplashScreen**

Restore login state, send heartbeat, call `checkForUpdate(silent: true)`, show `showAppUpdateDialog` when `shouldPrompt`, then navigate to home or login. Catch update failures without blocking login navigation.

- [x] **Step 4: Remove duplicate update logic from MainScaffold**

Convert `MainScaffold` to a stateless widget and remove `AppUpdateProvider`, `showAppUpdateDialog`, `_checkedUpdate`, and `didChangeDependencies` from that file.

- [x] **Step 5: Run the focused test and verify it passes**

Run: `flutter test test/widgets/startup_update_contract_test.dart --no-pub`

Expected: PASS.

### Task 3: Align shared Android and macOS product behavior

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/screens/home/dashboard_screen.dart`
- Modify: `clients/elephant-route-deprecated/lib/screens/profile/profile_screen.dart`
- Delete: `clients/elephant-route-deprecated/lib/screens/profile/about_screen.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/services/invite_share_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/latency_test_policy.dart`
- Modify: `clients/elephant-route-deprecated/lib/providers/node_provider.dart`
- Modify: `clients/elephant-route-deprecated/lib/screens/home/node_selection_screen.dart`
- Delete: `clients/elephant-route-deprecated/test/widgets/about_screen_test.dart`
- Modify: `clients/elephant-route-deprecated/test/widgets/profile_update_entry_test.dart`
- Modify: `clients/elephant-route-deprecated/test/core/singbox/latency_test_policy_test.dart`
- Test: `clients/elephant-route-deprecated/test/widgets/android_macos_feature_parity_test.dart`

- [x] **Step 1: Write failing parity contracts**

Assert dashboard/profile no longer render `emailInitial`, profile contains direct `检查更新` but no `AboutScreen` or `关于与诊断`, macOS is included in invite sharing, and macOS node latency tests require a connected local core.

- [x] **Step 2: Run the focused parity tests and verify they fail**

Run: `flutter test test/widgets/profile_update_entry_test.dart test/widgets/android_macos_feature_parity_test.dart test/core/singbox/latency_test_policy_test.dart --no-pub`

Expected: FAIL on the old avatar, diagnostics, sharing, and latency policies.

- [x] **Step 3: Remove avatars and diagnostics UI**

Delete the avatar widgets from dashboard and profile user summaries, remove the macOS About/Diagnostics entry and import, delete `AboutScreen`, and retain the existing direct `检查更新` entry.

- [x] **Step 4: Enable shared invite and node behavior on macOS**

Show the invite entry on Android, Windows, and macOS. Use web share URLs on Windows/macOS. Extend `LatencyTestPolicy.requiresConnectedVpn` with `isMacOS`, pass it from `NodeProvider`, and mirror the connected requirement in `NodeSelectionScreen`.

- [x] **Step 5: Run focused tests and verify they pass**

Run: `flutter test test/widgets/profile_update_entry_test.dart test/widgets/android_macos_feature_parity_test.dart test/core/singbox/latency_test_policy_test.dart --no-pub`

Expected: PASS.

### Task 4: Export exactly one unsigned ARM64 DMG

**Files:**
- Modify: `clients/elephant-route-deprecated/build_macos_beta.sh`
- Modify: `.github/workflows/macos-client.yml`
- Modify: `clients/elephant-route-deprecated/test/macos_distribution_contract_test.dart`
- Modify: `clients/elephant-route-deprecated/README.md`
- Modify: `clients/elephant-route-deprecated/docs/macos-beta-release.md`

- [x] **Step 1: Tighten the packaging contract test**

Assert the build script removes and recreates the export directory, removes the external app bundle after DMG creation, signs ad-hoc, validates ARM64 Mach-O content, and never invokes notarization.

- [x] **Step 2: Implement deterministic one-file export**

Remove preservation of old export contents, recreate `build/macos-beta`, generate the DMG, delete the copied `.app`, and make CI mount the DMG to verify the embedded app rather than expecting a sibling app bundle.

- [x] **Step 3: Run static and full Flutter verification**

Run: `dart format lib test && flutter analyze && flutter test --no-pub`

Expected: analyzer exits 0 and all tests pass.

- [x] **Step 4: Build and inspect the final artifact**

Run: `./build_macos_beta.sh`, list `build/macos-beta`, mount the DMG, run `codesign --verify --deep --strict` on the embedded app, and inspect every Mach-O with `file` to confirm ARM64-only output.

Expected: the export directory contains exactly `ElephantRoute-macos-arm64.dmg`; strict ad-hoc signature verification passes; no Intel slice is present.

- [x] **Step 5: Record release evidence**

Calculate `shasum -a 256 build/macos-beta/ElephantRoute-macos-arm64.dmg`, report its size/hash, and confirm `git diff --check` is clean without committing unrelated user changes.
