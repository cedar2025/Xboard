# macOS Client Update Release

This project exposes app-version metadata through the backend. The first macOS
client update flow is manual download and install: the app checks metadata,
opens the DMG URL, and the user replaces the installed app.

## Admin Publish API

Use the admin API under the configured secure path:

```http
POST /api/v2/{secure_path}/app-version/save
```

Required fields:

- `platform`: `macos`
- `channel`: `stable` or `beta`
- `version`: semantic version, for example `1.0.1`
- `build_number`: monotonically increasing integer
- `download_url`: notarized DMG download URL

Optional fields:

- `arch`: leave empty for a universal build
- `min_supported_build`: clients below this build are blocked from connecting
- `file_size`: DMG size in bytes
- `sha256`: 64-character SHA256 checksum
- `release_notes`: text shown in the client update dialog
- `is_force`: whether this version is mandatory
- `is_enabled`: publish immediately when true

Publish or disable an existing record:

```http
POST /api/v2/{secure_path}/app-version/publish
POST /api/v2/{secure_path}/app-version/disable
```

## Client Check API

The macOS client calls:

```http
GET /api/v1/app/update?platform=macos&channel=stable&version=1.0.0&build=1
```

The response includes `has_update`, `force`, and `latest`. Forced updates block
the home-page TUN connection button until the user opens the new DMG download.

## Release Flow

1. Build the release app and DMG with `MACOS_ARCH=arm64 ./build_macos_beta.sh`
   and `MACOS_ARCH=x64 ./build_macos_beta.sh`.
2. Sign and notarize the app, helper, and DMG.
3. Upload the DMG to the production download host.
4. Record the SHA256 checksum and DMG size.
5. Save and publish the app-version metadata in the admin API.
