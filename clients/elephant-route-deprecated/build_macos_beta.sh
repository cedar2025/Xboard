#!/bin/bash
set -euo pipefail

APP_NAME="ElephantRoute"
EXPORT_DIR="build/macos-beta"
BASE_URL="${BASE_URL:-https://www.elephant223.com}"
APP_DISTRIBUTION_URL="${APP_DISTRIBUTION_URL:-${BASE_URL}}"
ALLOW_INSECURE_CERTS="${ALLOW_INSECURE_CERTS:-false}"
MACOS_ARCH="${MACOS_ARCH:-arm64}"

case "${MACOS_ARCH}" in
  arm64)
    THIN_ARCH="arm64"
    KEEP_SING_BOX="sing-box-darwin-arm64"
    DROP_SING_BOX="sing-box-darwin-amd64"
    ;;
  x64)
    THIN_ARCH="x86_64"
    KEEP_SING_BOX="sing-box-darwin-amd64"
    DROP_SING_BOX="sing-box-darwin-arm64"
    ;;
  *)
    echo "MACOS_ARCH must be arm64 or x64, got: ${MACOS_ARCH}" >&2
    exit 1
    ;;
esac

APP_BUNDLE="${EXPORT_DIR}/${APP_NAME}-macos-${MACOS_ARCH}.app"
DMG_PATH="${EXPORT_DIR}/${APP_NAME}-macos-${MACOS_ARCH}.dmg"
PRESERVED_EXPORT_DIR=""

preserve_existing_outputs() {
  if [[ ! -d "${EXPORT_DIR}" ]]; then
    return 0
  fi

  PRESERVED_EXPORT_DIR="$(mktemp -d)"
  cp -R "${EXPORT_DIR}" "${PRESERVED_EXPORT_DIR}/macos-beta"
}

restore_existing_outputs() {
  if [[ -z "${PRESERVED_EXPORT_DIR}" ]]; then
    return 0
  fi

  mkdir -p "$(dirname "${EXPORT_DIR}")"
  rm -rf "${EXPORT_DIR}"
  mv "${PRESERVED_EXPORT_DIR}/macos-beta" "${EXPORT_DIR}"
  rm -rf "${PRESERVED_EXPORT_DIR}"
}

thin_macho_file() {
  local file_path="$1"
  local info
  info="$(lipo -info "${file_path}" 2>/dev/null || true)"
  if [[ "${info}" != *"Architectures in the fat file"* ]]; then
    return 0
  fi
  if [[ "${info}" != *"${THIN_ARCH}"* ]]; then
    echo "Missing ${THIN_ARCH} slice in ${file_path}" >&2
    return 1
  fi

  local tmp_path="${file_path}.thin"
  lipo "${file_path}" -thin "${THIN_ARCH}" -output "${tmp_path}"
  mv "${tmp_path}" "${file_path}"
  if [[ -x "${file_path}" ]]; then
    chmod 755 "${file_path}"
  fi
}

prune_for_arch() {
  local app_path="$1"
  local assets_dir="${app_path}/Contents/Frameworks/App.framework/Versions/A/Resources/flutter_assets/assets"

  rm -f "${assets_dir}/bin/windows/sing-box-windows-amd64.exe"
  rm -rf "${assets_dir}/bin/windows"
  rm -f "${assets_dir}/bin/${DROP_SING_BOX}"
  test -f "${assets_dir}/bin/${KEEP_SING_BOX}"

  rm -f "${assets_dir}/fonts/SourceHanSansCN-Regular.otf"
  rm -f "${assets_dir}/fonts/SourceHanSansCN-Bold.otf"
  rmdir "${assets_dir}/fonts" 2>/dev/null || true

  while IFS= read -r macho_file; do
    thin_macho_file "${macho_file}"
  done < <(find "${app_path}" -type f -perm -111 -print)
}

echo "==> Building macOS beta for ${APP_NAME}"
echo "==> BASE_URL=${BASE_URL}"
echo "==> APP_DISTRIBUTION_URL=${APP_DISTRIBUTION_URL}"
echo "==> ALLOW_INSECURE_CERTS=${ALLOW_INSECURE_CERTS}"
echo "==> MACOS_ARCH=${MACOS_ARCH} (${THIN_ARCH})"

export MACOS_ARCH
preserve_existing_outputs
flutter clean
restore_existing_outputs
flutter pub get
flutter build macos --release \
  --dart-define=BASE_URL="${BASE_URL}" \
  --dart-define=APP_DISTRIBUTION_URL="${APP_DISTRIBUTION_URL}" \
  --dart-define=ALLOW_INSECURE_CERTS="${ALLOW_INSECURE_CERTS}"

mkdir -p "${EXPORT_DIR}"
rm -rf "${APP_BUNDLE}" "${DMG_PATH}"
cp -R "build/macos/Build/Products/Release/${APP_NAME}.app" "${APP_BUNDLE}"

echo "==> Pruning and thinning ${APP_BUNDLE}"
prune_for_arch "${APP_BUNDLE}"

echo "==> Packaging ${DMG_PATH}"
hdiutil create -volname "${APP_NAME}" -srcfolder "${APP_BUNDLE}" -ov -format UDZO "${DMG_PATH}"

echo "==> App output: $(pwd)/${APP_BUNDLE}"
echo "==> DMG output: $(pwd)/${DMG_PATH}"
du -sh "${APP_BUNDLE}" "${DMG_PATH}"
echo "==> Next step for production: sign, notarize, and package with release_macos_dmg.sh."
