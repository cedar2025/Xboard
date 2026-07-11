#!/bin/sh
set -eu

HELPER_NAME="ElephantTunHelper"
HELPER_LABEL="com.elphantroute.elephantNetwork.tunhelper"
HELPER_DIR="${PROJECT_DIR}/ElephantTunHelper"
APP_CONTENTS="${TARGET_BUILD_DIR}/${CONTENTS_FOLDER_PATH}"
HELPER_OUTPUT="${APP_CONTENTS}/MacOS/${HELPER_NAME}"
HELPER_RESOURCE_OUTPUT="${APP_CONTENTS}/Resources/ElephantTunHelper"
MACOS_ARCH="${MACOS_ARCH:-${NATIVE_ARCH_ACTUAL:-${CURRENT_ARCH:-$(uname -m)}}}"
case "${MACOS_ARCH}" in
  arm64)
    SWIFT_TARGET="arm64-apple-macos13.0"
    ;;
  x64|x86_64|amd64)
    SWIFT_TARGET="x86_64-apple-macos13.0"
    ;;
  undefined_arch)
    SWIFT_TARGET="$(uname -m)-apple-macos13.0"
    ;;
  *)
    SWIFT_TARGET="${MACOS_ARCH}-apple-macos13.0"
    ;;
esac

mkdir -p "${APP_CONTENTS}/MacOS"

xcrun swiftc \
  -target "${SWIFT_TARGET}" \
  -O \
  "${HELPER_DIR}/main.swift" \
  -o "${HELPER_OUTPUT}"

chmod 755 "${HELPER_OUTPUT}"

mkdir -p "${HELPER_RESOURCE_OUTPUT}"
install -m 755 "${HELPER_DIR}/install-helper.sh" \
  "${HELPER_RESOURCE_OUTPUT}/install-helper.sh"
install -m 644 \
  "${HELPER_DIR}/com.elphantroute.elephantNetwork.tunhelper.legacy.plist" \
  "${HELPER_RESOURCE_OUTPUT}/com.elphantroute.elephantNetwork.tunhelper.legacy.plist"

if [ -n "${EXPANDED_CODE_SIGN_IDENTITY:-}" ] && [ "${EXPANDED_CODE_SIGN_IDENTITY}" != "-" ]; then
  codesign --force --timestamp=none --options runtime \
    --identifier "${HELPER_LABEL}" \
    --sign "${EXPANDED_CODE_SIGN_IDENTITY}" "${HELPER_OUTPUT}"
else
  codesign --force --timestamp=none \
    --identifier "${HELPER_LABEL}" --sign - "${HELPER_OUTPUT}"
fi
