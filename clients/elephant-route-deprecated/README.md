# Elephant Network Client

Flutter desktop/mobile client for Xboard-compatible subscriptions.

## Supported platforms

- Windows 10/11 x64 production release
- macOS public beta
- Android production APK

## Windows production release

The Windows app is distributed as an Inno Setup installer and includes:

- system proxy and TUN connection modes
- a local Windows service for standard-user TUN operation
- system tray integration and optional launch at startup
- in-app update checks with SHA-256 installer verification
- upgrade and uninstall handling for the app, service, core process, and owned proxy settings

Windows application binaries and the installer are currently distributed
without an Authenticode signature. Users may see an unknown-publisher or
Microsoft Defender SmartScreen warning during installation.

Build and release instructions are documented in
[docs/windows-release.md](./docs/windows-release.md).

## Development

```bash
flutter pub get
flutter run -d macos --dart-define=BASE_URL=https://www.elephant223.com
```

For Windows development, run Flutter from a Windows 10/11 x64 environment with
Visual Studio 2022 Desktop development with C++ installed.

## macOS release

See [docs/macos-beta-release.md](./docs/macos-beta-release.md).

## Android production APK

Use `./build_prod.sh` to generate the production Android APK.

- Output format: `build/app/outputs/flutter-apk/elephant-route-android-release-arm64-v<version>.apk`
- Current recorded APK version: see `android_release_version.txt`
- If no version file exists, the first APK is `V1.0`
- After each successful production build, the script records the generated version; the next build increments the minor version automatically (`V1.0` -> `V1.1`)
