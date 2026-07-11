# Elephant Network Windows App Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a Windows 10/11 x64 Flutter client with Android-equivalent product flows, a privileged TUN service, WebView2, standard installation/uninstallation, safe updates, and release signing gates.

**Architecture:** Keep shared Flutter UI and API code in the active client tree. Move Windows TUN and sing-box ownership into a LocalSystem Windows service reached through a constrained named-pipe protocol and Flutter native channels; package the resulting Win32 bundle with Inno Setup.

**Tech Stack:** Flutter/Dart, C++17/Win32, Windows SCM and named pipes, DPAPI, WebView2, Inno Setup, GitHub Actions.

---

### Task 1: Lock Windows interfaces and regression coverage

**Files:**
- Create: `clients/elephant-route-deprecated/lib/core/singbox/windows_service_protocol.dart`
- Create: `clients/elephant-route-deprecated/test/core/singbox/windows_service_protocol_test.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/windows_vpn_service.dart`

- [ ] Define the fixed service methods, state event fields, error codes, and size limits.
- [ ] Add tests for service status parsing, failure mapping, and malformed responses.
- [ ] Run `flutter test test/core/singbox/windows_service_protocol_test.dart` and require zero failures.

### Task 2: Implement the native service and Flutter bridge

**Files:**
- Create: `clients/elephant-route-deprecated/windows/service/*`
- Create: `clients/elephant-route-deprecated/windows/runner/windows_service_bridge.*`
- Modify: `clients/elephant-route-deprecated/windows/runner/flutter_window.cpp`
- Modify: `clients/elephant-route-deprecated/windows/CMakeLists.txt`

- [ ] Implement SCM install/runtime entry points and a versioned local named-pipe server.
- [ ] Restrict commands to status/start/stop/url-test/select-outbound/speed-test and use only the bundled sing-box path.
- [ ] Write runtime config under `%ProgramData%\\ElephantNetwork\\runtime`, supervise the child process, and return deterministic state/error payloads.
- [ ] Expose MethodChannel `com.elephant.network/windows_service` and EventChannel `com.elephant.network/windows_service/events`.
- [ ] Compile and run native protocol/service tests on a Windows x64 runner.

### Task 3: Complete Flutter Windows parity

**Files:**
- Modify: `clients/elephant-route-deprecated/lib/core/singbox/windows_vpn_service.dart`
- Modify: `clients/elephant-route-deprecated/lib/core/storage/secure_storage.dart`
- Modify: `clients/elephant-route-deprecated/lib/widgets/custom_webview.dart`

- [ ] Route VPN operations and state through the service, including traffic and latency events.
- [ ] Add Windows DPAPI channel storage and migrate then remove plaintext tokens.
- [ ] Add a WebView2-backed Windows view with external-browser fallback.
- [ ] Make invite sharing, About/diagnostics, startup preferences, tray close/exit, and platform labels Windows-aware.
- [ ] Run focused widget/unit tests followed by the complete Flutter test suite and analyzer.

### Task 4: Add installer, updater, and signing gates

**Files:**
- Create: `clients/elephant-route-deprecated/windows/installer/ElephantNetwork.iss`
- Create: `clients/elephant-route-deprecated/scripts/build_windows_release.ps1`
- Modify: `clients/elephant-route-deprecated/lib/core/api/services/app_update_service.dart`

- [ ] Build a per-machine x64 installer with the fixed AppId and standard shortcuts/uninstall registration.
- [ ] Stop the app/core/service before upgrade or uninstall; restore only Elephant-owned legacy proxy settings.
- [ ] Default the uninstall data-removal checkbox on while allowing users to retain data.
- [ ] Download Windows updates as `.exe`, verify SHA-256 and Authenticode, then launch the installer and exit safely.
- [ ] Allow self-signed internal builds but make production release fail closed without trusted signing configuration.

### Task 5: Add Windows CI and end-to-end verification

**Files:**
- Create: `.github/workflows/windows-client.yml`
- Create: `clients/elephant-route-deprecated/windows/tests/*`
- Create: `clients/elephant-route-deprecated/docs/windows-release.md`

- [ ] Run Flutter tests/analyzer, C++ tests, Windows release build, Inno compilation, installer smoke tests, and signature inspection on Windows.
- [ ] Verify fresh install, standard-user connect without repeated UAC, upgrade, connected uninstall, data deletion/retention, and residue cleanup.
- [ ] Document internal certificate trust, production certificate requirements, artifact naming, hashes, and release commands.

