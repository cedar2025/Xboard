# App Distribution Backend

Standalone Laravel + Blade backend for distributing macOS and Windows desktop
client releases.

## Features

- Independent administrator login system.
- Roles: `owner`, `admin`, `viewer`.
- App registry with stable app keys.
- Version upload and release management for `macos` and `windows`.
- Local private artifact storage under `storage/app/artifacts`.
- Public update check API.
- Controlled download route with download logs.
- Anonymous device heartbeat and update-event telemetry.
- Dashboard, device statistics, download logs, and audit logs.

## Local Setup

```bash
cd app-distribution
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8088
```

Default seeded administrator:

- Email: value of `ADMIN_EMAIL`, default `admin@example.com`
- Password: value of `ADMIN_PASSWORD`, default `ChangeMe123!`

Change the default password immediately after first login.

## Client Configuration

Build the Flutter desktop client with:

```bash
flutter build macos --release \
  --dart-define=APP_DISTRIBUTION_URL=https://updates.example.com \
  --dart-define=APP_DISTRIBUTION_APP_KEY=elephant-route-desktop
```

Windows uses the same defines.

## Public APIs

### Check Update

```http
GET /api/v1/update/check?app_key=elephant-route-desktop&platform=macos&channel=stable&version=1.0.0&build=1&installation_id=<anonymous-id>
```

Response:

```json
{
  "status": "success",
  "data": {
    "has_update": true,
    "force": false,
    "latest": {
      "version": "1.0.1",
      "build_number": 2,
      "download_url": "https://updates.example.com/download/1",
      "file_size": 12345678,
      "sha256": "...",
      "release_notes": "..."
    }
  }
}
```

### Heartbeat

```http
POST /api/v1/telemetry/heartbeat
```

JSON body:

```json
{
  "app_key": "elephant-route-desktop",
  "installation_id": "anonymous-device-id",
  "platform": "macos",
  "channel": "stable",
  "version": "1.0.0",
  "build": 1,
  "os_version": "macOS ..."
}
```

### Update Result

```http
POST /api/v1/telemetry/update-result
```

Valid events: `download_clicked`, `download_opened`, `installed_after_update`.

## Release Workflow

1. Build, sign, notarize, and package the macOS or Windows client outside this backend.
2. Log in as `owner` or `admin`.
3. Create or select the App record.
4. Upload a version artifact as draft.
5. Confirm SHA256 and file size.
6. Publish the version to `stable` or `beta`.
7. Use forced update or `min_supported_build` only for security or compatibility breaks.

## Storage

First version uses local private storage. Files are not placed under `public/`.
The download controller streams files and records download logs. The storage
service is isolated in `app/Services/ArtifactStorage.php` so it can be replaced
with S3, OSS, R2, or another object-storage driver later.
