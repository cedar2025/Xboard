#!/bin/bash
set -euo pipefail

APP_NAME="ElephantRoute"
EXPORT_DIR="build/macos-beta"
BASE_URL="${BASE_URL:-https://www.elephant223.com}"
APP_DISTRIBUTION_URL="${APP_DISTRIBUTION_URL:-}"
ALLOW_INSECURE_CERTS="${ALLOW_INSECURE_CERTS:-false}"
MACOS_ARCH="arm64"
THIN_ARCH="arm64"
KEEP_SING_BOX="sing-box-darwin-arm64"
DROP_SING_BOX="sing-box-darwin-amd64"

APP_BUNDLE="${EXPORT_DIR}/${APP_NAME}-macos-${MACOS_ARCH}.app"
DMG_PATH="${EXPORT_DIR}/${APP_NAME}-macos-${MACOS_ARCH}.dmg"
STAGING_DIR=""
SMOKE_PID=""
SMOKE_LOG=""

cleanup() {
  if [[ -n "${SMOKE_PID}" ]] && kill -0 "${SMOKE_PID}" 2>/dev/null; then
    kill "${SMOKE_PID}" 2>/dev/null || true
    wait "${SMOKE_PID}" 2>/dev/null || true
  fi
  if [[ -n "${SMOKE_LOG}" ]]; then
    rm -f "${SMOKE_LOG}"
  fi
  if [[ -n "${STAGING_DIR}" && -d "${STAGING_DIR}" ]]; then
    rm -rf "${STAGING_DIR}"
  fi
}
trap cleanup EXIT

smoke_test_app() {
  local app_path="$1"
  SMOKE_LOG="$(mktemp)"
  "${app_path}/Contents/MacOS/ElephantRoute" >"${SMOKE_LOG}" 2>&1 &
  SMOKE_PID=$!
  sleep 3
  if ! kill -0 "${SMOKE_PID}" 2>/dev/null; then
    wait "${SMOKE_PID}" 2>/dev/null || true
    cat "${SMOKE_LOG}" >&2
    echo "Packaged app failed the launch smoke test" >&2
    return 1
  fi
  kill "${SMOKE_PID}" 2>/dev/null || true
  wait "${SMOKE_PID}" 2>/dev/null || true
  SMOKE_PID=""
  rm -f "${SMOKE_LOG}"
  SMOKE_LOG=""
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

is_macho() {
  /usr/bin/file "$1" | /usr/bin/grep -q "Mach-O"
}

adhoc_sign_app() {
  local app_path="$1"

  while IFS= read -r file_path; do
    if is_macho "${file_path}"; then
      codesign --force --timestamp=none --sign - "${file_path}"
    fi
  done < <(find "${app_path}/Contents" -type f -print)

  codesign --force --timestamp=none \
    --identifier "com.elphantroute.elephantNetwork.tunhelper" --sign - \
    "${app_path}/Contents/MacOS/ElephantTunHelper"

  while IFS= read -r framework_path; do
    codesign --force --timestamp=none --sign - "${framework_path}"
  done < <(find "${app_path}/Contents/Frameworks" -type d -name '*.framework' -print | sort -r)

  codesign --force --timestamp=none --entitlements \
    "macos/Runner/Release.entitlements" --sign - "${app_path}"
  codesign --verify --deep --strict --verbose=4 "${app_path}"
}

echo "==> Building macOS beta for ${APP_NAME}"
echo "==> BASE_URL=${BASE_URL}"
echo "==> APP_DISTRIBUTION_URL=${APP_DISTRIBUTION_URL:-runtime resolved domain}"
echo "==> ALLOW_INSECURE_CERTS=${ALLOW_INSECURE_CERTS}"
echo "==> MACOS_ARCH=${MACOS_ARCH} (${THIN_ARCH})"

export MACOS_ARCH
flutter clean
flutter pub get
BUILD_ARGS=(
  --release
  --dart-define=BASE_URL="${BASE_URL}"
  --dart-define=ALLOW_INSECURE_CERTS="${ALLOW_INSECURE_CERTS}"
)
if [[ -n "${APP_DISTRIBUTION_URL}" ]]; then
  BUILD_ARGS+=(--dart-define=APP_DISTRIBUTION_URL="${APP_DISTRIBUTION_URL}")
fi
flutter build macos "${BUILD_ARGS[@]}"

rm -rf "${EXPORT_DIR}"
mkdir -p "${EXPORT_DIR}"
cp -R "build/macos/Build/Products/Release/${APP_NAME}.app" "${APP_BUNDLE}"

echo "==> Pruning and thinning ${APP_BUNDLE}"
prune_for_arch "${APP_BUNDLE}"

echo "==> Applying ad-hoc signatures after pruning"
adhoc_sign_app "${APP_BUNDLE}"

echo "==> Running packaged app launch smoke test"
smoke_test_app "${APP_BUNDLE}"

echo "==> Packaging ${DMG_PATH}"
STAGING_DIR="$(mktemp -d)"
cp -R "${APP_BUNDLE}" "${STAGING_DIR}/大象网络.app"
ln -s /Applications "${STAGING_DIR}/Applications"
cat > "${STAGING_DIR}/安装说明.txt" <<'EOF'
大象网络 macOS ARM 无证书版

1. 将“大象网络.app”拖入“Applications”。
2. 首次打开若被 macOS 拦截，请前往“系统设置 > 隐私与安全性”并点击“仍要打开”。
3. 首次开启加速或后台组件升级时，按提示输入管理员密码完成安装。
4. 更新时退出旧版本，再从 DMG 拖入“Applications”并选择“替换”。
5. 卸载后台组件：
   sudo "/Applications/大象网络.app/Contents/Resources/ElephantTunHelper/install-helper.sh" uninstall

本版本使用 ad-hoc 签名，不使用 Developer ID，也未提交 Apple 公证。
EOF
hdiutil create -volname "大象网络" -srcfolder "${STAGING_DIR}" -ov -format UDZO "${DMG_PATH}"

rm -rf "${APP_BUNDLE}"

echo "==> DMG output: $(pwd)/${DMG_PATH}"
du -sh "${DMG_PATH}"
echo "==> SHA-256"
shasum -a 256 "${DMG_PATH}"
echo "==> Code signing: ad-hoc (Developer ID and notarization disabled)"
