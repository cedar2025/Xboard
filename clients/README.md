# Client Applications

This directory contains client application code that is separate from the main
Xboard Laravel backend.

## Active Android Client

`android-singbox/` is the current native Android VPN client.

- Stack: Kotlin, Jetpack Compose, Gradle, sing-box `libbox.aar`
- Package name: `cn.moncn.sing_box_windows`
- Build from this directory:

```bash
cd clients/android-singbox
./gradlew assembleDebug
./gradlew assembleRelease
```

## Deprecated ElephantRoute Client

`elephant-route-deprecated/` is the legacy Flutter multi-platform client.

- Platforms: Android, macOS, Windows
- Package/project name: `elephant_network`
- Android package name: `com.elephantroute`
- Status: deprecated; keep for reference and historical builds unless a future
  cleanup task explicitly removes it.

## Distribution Backend

`app-distribution/` intentionally remains at the repository root. It is a
standalone Laravel backend for release distribution and telemetry, not client
application source code.
