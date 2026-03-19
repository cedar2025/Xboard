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
flutter run -d macos --dart-define=BASE_URL=https://www.elphantroute.com
```

## Release

See [docs/macos-beta-release.md](./docs/macos-beta-release.md).
