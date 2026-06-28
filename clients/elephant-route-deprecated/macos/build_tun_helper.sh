#!/bin/sh
set -eu

HELPER_NAME="ElephantTunHelper"
HELPER_LABEL="com.elphantroute.elephantNetwork.tunhelper"
HELPER_DIR="${PROJECT_DIR}/ElephantTunHelper"
APP_CONTENTS="${TARGET_BUILD_DIR}/${CONTENTS_FOLDER_PATH}"
HELPER_OUTPUT="${APP_CONTENTS}/MacOS/${HELPER_NAME}"
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

if [ -n "${EXPANDED_CODE_SIGN_IDENTITY:-}" ] && [ "${EXPANDED_CODE_SIGN_IDENTITY}" != "-" ]; then
  codesign --force --timestamp=none --options runtime --sign "${EXPANDED_CODE_SIGN_IDENTITY}" "${HELPER_OUTPUT}"
else
  codesign --force --timestamp=none --sign - "${HELPER_OUTPUT}"
fi
