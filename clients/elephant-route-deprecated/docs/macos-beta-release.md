# macOS Beta Release

## Build

```bash
MACOS_ARCH=arm64 ./build_macos_beta.sh
MACOS_ARCH=x64 ./build_macos_beta.sh
```

Optional environment variables:

```bash
BASE_URL=https://www.elephant223.com
APP_DISTRIBUTION_URL=https://www.elephant223.com
ALLOW_INSECURE_CERTS=false
MACOS_ARCH=arm64
```

`MACOS_ARCH` must be `arm64` or `x64`. The build script writes
`build/macos-beta/ElephantRoute-macos-${MACOS_ARCH}.app` and
`build/macos-beta/ElephantRoute-macos-${MACOS_ARCH}.dmg`, pruning non-target
sing-box binaries and thinning universal Mach-O files for the selected
architecture.

## Sign / Notarize / DMG

```bash
DEVELOPER_ID_APP="Developer ID Application: Example Team" \
APPLE_ID="name@example.com" \
APPLE_TEAM_ID="TEAMID1234" \
APPLE_APP_PASSWORD="app-specific-password" \
MACOS_ARCH=arm64 \
./release_macos_dmg.sh
```

## Manual QA

1. Install the generated `.app` or `.dmg` on both Apple Silicon and Intel Macs.
2. Verify login, subscription fetch, connect, disconnect, node switch, and proxy restore.
3. Verify both proxy mode and TUN mode.
4. Export diagnostics from the About screen after one successful and one failed session.
