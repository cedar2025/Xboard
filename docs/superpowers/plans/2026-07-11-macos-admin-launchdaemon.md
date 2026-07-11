# macOS Admin LaunchDaemon Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the failing ad-hoc `SMAppService` TUN helper registration with a fixed, administrator-authorized LaunchDaemon while preserving the single unsigned ARM64 DMG.

**Architecture:** The app bundles a fixed installer script and legacy launchd plist beside `ElephantTunHelper`. `AppDelegate` validates the `/Applications` bundle, removes stale `SMAppService` registration, invokes only the bundled installer through the standard administrator prompt, compares helper SHA-256 values for upgrades, and gates TUN startup on an XPC health check. The root installer owns all privileged paths and supports only idempotent `install` and `uninstall` actions.

**Tech Stack:** Flutter/Dart, Swift, NSXPC, AppleScript administrator authorization, launchd/LaunchDaemon, POSIX shell, ServiceManagement migration, codesign, hdiutil.

---

### Task 1: Bundle a fixed privileged installer

**Files:**
- Create: `clients/elephant-route-deprecated/macos/ElephantTunHelper/install-helper.sh`
- Create: `clients/elephant-route-deprecated/macos/ElephantTunHelper/com.elphantroute.elephantNetwork.tunhelper.legacy.plist`
- Modify: `clients/elephant-route-deprecated/macos/build_tun_helper.sh`
- Modify: `clients/elephant-route-deprecated/test/macos_distribution_contract_test.dart`

- [ ] **Step 1: Write failing package contract assertions**

Assert that the app bundle build copies `install-helper.sh` and the legacy plist into `Contents/Resources/ElephantTunHelper`, and that the installer contains only the fixed label, helper destination, plist destination, `install|uninstall` action switch, `launchctl bootout/bootstrap`, `root:wheel`, and restrictive modes.

- [ ] **Step 2: Run the contract and confirm failure**

Run: `flutter test test/macos_distribution_contract_test.dart --no-pub`

Expected: FAIL because the installer resources do not exist.

- [ ] **Step 3: Implement the installer and plist**

The installer must resolve and validate `/Applications/大象网络.app`, reject symlinks and caller-supplied destinations, stage files under `/Library/PrivilegedHelperTools` and `/Library/LaunchDaemons`, use atomic `mv`, set helper `root:wheel 0755` and plist `root:wheel 0644`, validate the plist with `plutil -lint`, then bootstrap the fixed system label. `uninstall` must boot out the job and remove only the fixed helper, plist, and install metadata.

- [ ] **Step 4: Bundle installer resources during helper build**

Extend `macos/build_tun_helper.sh` to copy the two source resources into `${APP_CONTENTS}/Resources/ElephantTunHelper` with executable mode only on the installer.

- [ ] **Step 5: Verify script and contract**

Run: `sh -n macos/ElephantTunHelper/install-helper.sh && plutil -lint macos/ElephantTunHelper/com.elphantroute.elephantNetwork.tunhelper.legacy.plist && flutter test test/macos_distribution_contract_test.dart --no-pub`

Expected: both syntax checks and the Flutter contract pass.

### Task 2: Replace helper registration with administrator installation

**Files:**
- Modify: `clients/elephant-route-deprecated/macos/Runner/AppDelegate.swift`
- Modify: `clients/elephant-route-deprecated/test/core/services/mac_runtime_service_contract_test.dart`

- [ ] **Step 1: Add failing native source contracts**

Assert `AppDelegate` defines fixed installed paths, calculates SHA-256 with `CryptoKit`, exposes `HELPER_INSTALL_REQUIRED`, `HELPER_REFRESH_REQUIRED`, `AUTHORIZATION_CANCELLED`, `HELPER_INSTALL_FAILED`, and calls `/usr/bin/osascript` without accepting Flutter command text or paths.

- [ ] **Step 2: Run the focused test and confirm failure**

Run: `flutter test test/core/services/mac_runtime_service_contract_test.dart --no-pub`

Expected: FAIL because the administrator installer state machine does not exist.

- [ ] **Step 3: Implement status and hash inspection**

Add fixed URLs for bundled and installed helper resources, compare SHA-256 values, inspect `launchctl print system/com.elphantroute.elephantNetwork.tunhelper`, and return `notRegistered`, `refreshRequired`, `enabled`, or `connectionFailed` without triggering authorization from read-only status calls.

- [ ] **Step 4: Implement fixed administrator invocation**

`ensureTunHelper` must require `/Applications`, return immediately when installed helper hash and XPC health are valid, best-effort unregister the stale `SMAppService`, and invoke the bundled installer through an AppleScript `on run argv` handler with administrator privileges. Only the fixed installer path and fixed `install` action may be supplied. Map AppleScript error `-128` to `AUTHORIZATION_CANCELLED` and all other failures to `HELPER_INSTALL_FAILED`.

- [ ] **Step 5: Verify post-install health**

After installation, wait for launchd and call helper `getStatus`; return `enabled` only when the reply has `ok=true`. Otherwise return `HELPER_HEALTH_CHECK_FAILED` with launchd output.

- [ ] **Step 6: Run focused tests**

Run: `flutter test test/core/services/mac_runtime_service_contract_test.dart test/core/singbox/macos_vpn_permission_policy_test.dart --no-pub`

Expected: PASS.

### Task 3: Harden helper caller and runtime paths

**Files:**
- Modify: `clients/elephant-route-deprecated/macos/ElephantTunHelper/main.swift`
- Modify: `clients/elephant-route-deprecated/macos/Runner/AppDelegate.swift`
- Modify: `clients/elephant-route-deprecated/test/core/services/mac_runtime_service_contract_test.dart`

- [ ] **Step 1: Write failing security contracts**

Assert the helper reads the NSXPC connection effective UID, accepts only the current console user, and validates config and binary paths under that user’s exact Application Support directory.

- [ ] **Step 2: Run focused tests and confirm failure**

Run: `flutter test test/core/services/mac_runtime_service_contract_test.dart --no-pub`

Expected: FAIL because the current helper accepts every XPC connection and validates only a suffix.

- [ ] **Step 3: Implement connection and path checks**

Use the public `connection.effectiveUserIdentifier`, compare it with the owner UID of `/dev/console`, and resolve that UID’s home directory with `getpwuid`. Reject UID 0, other users, and missing console sessions. Store the accepted UID and home directory on the exported helper object, then require resolved config/binary paths to begin with that exact home directory’s `Library/Application Support/com.elphantroute.elephantNetwork/sing-box/` path and match the fixed filenames.

- [ ] **Step 4: Run focused tests**

Run: `flutter test test/core/services/mac_runtime_service_contract_test.dart --no-pub`

Expected: PASS.

### Task 4: Align Flutter setup, errors, and uninstall behavior

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/macos_tun_permission.dart`
- Modify: `clients/elephant-route-deprecated/lib/widgets/mac_setup_guide.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/services/mac_runtime_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/macos_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/test/core/singbox/macos_vpn_permission_policy_test.dart`
- Modify: `clients/elephant-route-deprecated/test/widgets/android_macos_feature_parity_test.dart`

- [ ] **Step 1: Write failing permission/UI contracts**

Assert installer-required, refresh-required, authorization-cancelled, install-failed, and health-check-failed errors preserve native messages; setup copy says “管理员授权安装后台网络组件” and no longer directs users to Login Items & Extensions.

- [ ] **Step 2: Run focused tests and confirm failure**

Run: `flutter test test/core/singbox/macos_vpn_permission_policy_test.dart test/widgets/android_macos_feature_parity_test.dart --no-pub`

Expected: FAIL on the old approval guidance.

- [ ] **Step 3: Implement the shared permission flow**

Keep the public status set stable, map the new error codes, call the single `ensureTunHelper` path from the main button, tray, and recovery, and never start TUN until `enabled` is returned. User cancellation remains recoverable and does not auto-reopen the password prompt.

- [ ] **Step 4: Add fixed uninstall channel operation**

Expose `uninstallTunHelper` through the existing runtime channel and invoke the same bundled installer with only the fixed `uninstall` action. Do not add an About/Diagnostics page; the operation is for the documented uninstall flow and future installer integration.

- [ ] **Step 5: Run focused tests**

Run: `flutter test test/core/singbox/macos_vpn_permission_policy_test.dart test/widgets/android_macos_feature_parity_test.dart --no-pub`

Expected: PASS.

### Task 5: Update packaging docs and perform real integration verification

**Files:**
- Modify: `clients/elephant-route-deprecated/build_macos_beta.sh`
- Modify: `clients/elephant-route-deprecated/docs/macos-beta-release.md`
- Modify: `clients/elephant-route-deprecated/README.md`
- Modify: `docs/macos-client-update-release.md`
- Modify: `.github/workflows/macos-client.yml`

- [ ] **Step 1: Document the administrator install lifecycle**

Replace Login Items approval guidance with the first-install/helper-upgrade password prompt, fixed paths, cancellation behavior, and administrator uninstall cleanup. Keep the no-Developer-ID and Gatekeeper instructions explicit.

- [ ] **Step 2: Add release resource and launch smoke checks**

Verify the DMG app contains the installer, legacy plist, helper, and only ARM64 Mach-O files. Launch the mounted app executable for at least three seconds and fail packaging/CI if dyld exits. Continue requiring one DMG and strict `codesign` verification.

- [ ] **Step 3: Run static and automated verification**

Run: `dart format lib test && flutter analyze && flutter test --no-pub && git diff --check`

Expected: analyzer exits 0, all Flutter tests pass, and diff check is clean.

- [ ] **Step 4: Build the unsigned ARM64 DMG**

Run: `./build_macos_beta.sh`

Expected: `build/macos-beta` contains only `ElephantRoute-macos-arm64.dmg`; the mounted app passes strict codesign and real launch smoke tests.

- [ ] **Step 5: Perform administrator integration test**

Install the DMG app into `/Applications`, remove the stale SMAppService registration, trigger one administrator authorization, then verify `launchctl print system/com.elphantroute.elephantNetwork.tunhelper`, a root `ElephantTunHelper` process, successful XPC `getStatus`, TUN connect/disconnect, route/proxy restoration, helper hash-based reinstall, cancellation behavior, and uninstall cleanup.

- [ ] **Step 6: Record artifact evidence**

Report exact DMG byte size, SHA-256, Mach-O count, architecture result, test count, launchd status, XPC health result, and any clean-machine acceptance work that still remains.
