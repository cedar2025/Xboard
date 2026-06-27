#!/bin/bash
set -euo pipefail

APP_NAME="ElephantRoute"
SCHEME="Runner"
EXPORT_DIR="build/macos-beta"
BASE_URL="${BASE_URL:-https://www.elphantroute.com}"
ALLOW_INSECURE_CERTS="${ALLOW_INSECURE_CERTS:-false}"

echo "==> Building macOS beta for ${APP_NAME}"
echo "==> BASE_URL=${BASE_URL}"
echo "==> ALLOW_INSECURE_CERTS=${ALLOW_INSECURE_CERTS}"

flutter clean
flutter pub get
flutter build macos --release \
  --dart-define=BASE_URL="${BASE_URL}" \
  --dart-define=ALLOW_INSECURE_CERTS="${ALLOW_INSECURE_CERTS}"

mkdir -p "${EXPORT_DIR}"
cp -R "build/macos/Build/Products/Release/${APP_NAME}.app" "${EXPORT_DIR}/"

echo "==> Build output: $(pwd)/${EXPORT_DIR}/${APP_NAME}.app"
echo "==> Next step: sign, notarize, and package DMG with your Developer ID credentials."
