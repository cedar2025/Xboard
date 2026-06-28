#!/bin/bash
set -euo pipefail

APP_NAME="ElephantRoute"
MACOS_ARCH="${MACOS_ARCH:-arm64}"
case "${MACOS_ARCH}" in
  arm64|x64) ;;
  *)
    echo "MACOS_ARCH must be arm64 or x64, got: ${MACOS_ARCH}" >&2
    exit 1
    ;;
esac

APP_PATH="${1:-build/macos-beta/${APP_NAME}-macos-${MACOS_ARCH}.app}"
DMG_NAME="${2:-${APP_NAME}-macos-${MACOS_ARCH}.dmg}"
APPLE_ID="${APPLE_ID:-}"
APPLE_TEAM_ID="${APPLE_TEAM_ID:-}"
APPLE_APP_PASSWORD="${APPLE_APP_PASSWORD:-}"
DEVELOPER_ID_APP="${DEVELOPER_ID_APP:-}"

if [[ ! -d "${APP_PATH}" ]]; then
  echo "App bundle not found: ${APP_PATH}" >&2
  exit 1
fi

if [[ -z "${DEVELOPER_ID_APP}" ]]; then
  echo "DEVELOPER_ID_APP is required, example: Developer ID Application: Your Team" >&2
  exit 1
fi

echo "==> Signing ${APP_PATH}"
if [[ -f "${APP_PATH}/Contents/MacOS/ElephantTunHelper" ]]; then
  codesign --force --options runtime --sign "${DEVELOPER_ID_APP}" \
    "${APP_PATH}/Contents/MacOS/ElephantTunHelper"
fi
codesign --deep --force --options runtime --sign "${DEVELOPER_ID_APP}" "${APP_PATH}"

echo "==> Packaging DMG ${DMG_NAME}"
hdiutil create -volname "${APP_NAME}" -srcfolder "${APP_PATH}" -ov -format UDZO "${DMG_NAME}"

if [[ -n "${APPLE_ID}" && -n "${APPLE_TEAM_ID}" && -n "${APPLE_APP_PASSWORD}" ]]; then
  echo "==> Submitting DMG for notarization"
  xcrun notarytool submit "${DMG_NAME}" \
    --apple-id "${APPLE_ID}" \
    --team-id "${APPLE_TEAM_ID}" \
    --password "${APPLE_APP_PASSWORD}" \
    --wait

  echo "==> Stapling ticket"
  xcrun stapler staple "${APP_PATH}"
else
  echo "==> Skipping notarization because APPLE_ID / APPLE_TEAM_ID / APPLE_APP_PASSWORD are not fully set"
fi

echo "==> Release artifact ready: $(pwd)/${DMG_NAME}"
