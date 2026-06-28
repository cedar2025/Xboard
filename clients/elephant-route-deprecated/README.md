# ElephantRoute Client

Flutter desktop/mobile client for Xboard-compatible subscriptions.

## Current focus

- macOS public beta
- system proxy mode
- TUN mode with elevated launch path
- diagnostics export and runtime recovery

## Development

```bash
flutter pub get
flutter run -d macos --dart-define=BASE_URL=https://www.elephant223.com
```

## Release

See [docs/macos-beta-release.md](./docs/macos-beta-release.md).

## Android production APK

Use `./build_prod.sh` to generate the production Android APK.

- Output format: `build/app/outputs/flutter-apk/app-release-prd-V<version>.apk`
- Current recorded APK version: see `android_release_version.txt`
- If no version file exists, the first APK is `V1.0`
- After each successful production build, the script records the generated version; the next build increments the minor version automatically (`V1.0` -> `V1.1`)
