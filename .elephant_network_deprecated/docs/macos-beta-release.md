# macOS Beta Release

## Build

```bash
./build_macos_beta.sh
```

Optional environment variables:

```bash
BASE_URL=https://www.elphantroute.com
ALLOW_INSECURE_CERTS=false
```

## Sign / Notarize / DMG

```bash
DEVELOPER_ID_APP="Developer ID Application: Example Team" \
APPLE_ID="name@example.com" \
APPLE_TEAM_ID="TEAMID1234" \
APPLE_APP_PASSWORD="app-specific-password" \
./release_macos_dmg.sh
```

## Manual QA

1. Install the generated `.app` or `.dmg` on both Apple Silicon and Intel Macs.
2. Verify login, subscription fetch, connect, disconnect, node switch, and proxy restore.
3. Verify both proxy mode and TUN mode.
4. Export diagnostics from the About screen after one successful and one failed session.
