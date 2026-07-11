# macOS ARM Unsigned Production Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship an Apple Silicon-only macOS 13+ DMG with full TUN support, ad-hoc code signing, guided Gatekeeper/helper approval, manual DMG upgrades, and release verification without Developer ID or notarization.

**Architecture:** Keep the existing Flutter UI, `MacosVpnService`, `SMAppService` LaunchDaemon, XPC helper, and bundled sing-box core. Centralize privileged-helper readiness in the VPN service, expose native setup/startup operations through the existing runtime channel, and make the post-pruning release bundle reproducibly ad-hoc signed and verifiable.

**Tech Stack:** Flutter/Dart, Swift/Cocoa, ServiceManagement, XPC, sing-box, Bash, GitHub Actions.

---

### Task 1: Lock the macOS runtime contract

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/core/services/mac_runtime_service.dart`
- Modify: `clients/elephant-route-deprecated/macos/Runner/AppDelegate.swift`
- Test: `clients/elephant-route-deprecated/test/core/services/mac_runtime_service_contract_test.dart`

- [ ] Add contract tests for setup status, settings navigation, helper refresh, and launch-at-login methods.
- [ ] Add native methods for app location, opening Login Items settings, helper refresh, and `SMAppService.mainApp` startup state.
- [ ] Normalize helper status values to `enabled`, `requiresApproval`, `notRegistered`, `notFound`, `refreshRequired`, and `connectionFailed`.
- [ ] Run the contract test and `flutter analyze`.

### Task 2: Centralize TUN permission and setup UX

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/macos_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/providers/vpn_provider.dart`
- Modify: `clients/elephant-route-deprecated/lib/screens/home/dashboard_screen.dart`
- Create: `clients/elephant-route-deprecated/lib/widgets/mac_setup_guide.dart`
- Test: `clients/elephant-route-deprecated/test/core/singbox/macos_vpn_permission_policy_test.dart`

- [ ] Add a pure helper-status permission policy and failing unit tests.
- [ ] Make `MacosVpnService.requestPermission()` register/check the helper and preserve native errors in `VpnState`.
- [ ] Make all connect entry points, including tray actions, use the centralized permission path.
- [ ] Show a one-time macOS setup guide and provide a direct action to open Login Items settings.
- [ ] Run the permission and provider tests.

### Task 3: Restore diagnostics and platform startup controls

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/screens/profile/profile_screen.dart`
- Modify: `clients/elephant-route-deprecated/lib/screens/profile/about_screen.dart`
- Modify: `clients/elephant-route-deprecated/lib/providers/startup_provider.dart`
- Test: `clients/elephant-route-deprecated/test/widgets/profile_update_entry_test.dart`

- [ ] Restore the About and Diagnostics entry on macOS.
- [ ] Display app location and normalized helper state, and add refresh/settings/export actions.
- [ ] Use `SMAppService.mainApp` for optional macOS launch at login while retaining the Windows implementation.
- [ ] Update widget/source contract tests and run them.

### Task 4: Harden manual DMG updates

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/core/api/services/app_update_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/widgets/app_update_dialog.dart`
- Test: `clients/elephant-route-deprecated/test/core/api/app_update_service_test.dart`

- [ ] Require a 64-character SHA-256 for macOS release downloads.
- [ ] Stop the active macOS runtime before opening the verified DMG.
- [ ] Explain manual replacement and relaunch in the update dialog without unregistering the old helper prematurely.
- [ ] Run update tests.

### Task 5: Produce an ARM-only ad-hoc signed DMG

**Files:**
- Modify: `clients/elephant-route-deprecated/build_macos_beta.sh`
- Modify: `clients/elephant-route-deprecated/docs/macos-beta-release.md`
- Create: `clients/elephant-route-deprecated/test/macos_distribution_contract_test.dart`

- [ ] Make arm64 the only accepted release architecture and remove non-arm64 assets.
- [ ] Re-sign nested Mach-O/framework content and the app after pruning, then run strict code-sign verification.
- [ ] Build a DMG staging directory containing the app, `/Applications` symlink, and unsigned-install guide.
- [ ] Print the artifact SHA-256 and document publish, upgrade, uninstall, and clean-machine gates.
- [ ] Run distribution contract tests and build the DMG.

### Task 6: Add CI and complete verification

**Files:**
- Create: `.github/workflows/macos-client.yml`
- Modify: `clients/elephant-route-deprecated/README.md`

- [ ] Add macOS ARM analyze, test, release-build, architecture, code-sign, hash, and artifact-upload checks.
- [ ] Run `flutter analyze`, full `flutter test --no-pub`, `git diff --check`, the ARM packaging script, `lipo -info`, `codesign --verify --deep --strict`, and DMG content inspection.
- [ ] Record that Gatekeeper/helper approval remains blocked on the independent clean ARM Mac acceptance gate until that external test is performed.
