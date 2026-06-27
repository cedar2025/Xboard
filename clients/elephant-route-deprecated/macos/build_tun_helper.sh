#!/bin/sh
set -eu

HELPER_NAME="ElephantTunHelper"
HELPER_LABEL="com.elphantroute.elephantNetwork.tunhelper"
HELPER_DIR="${PROJECT_DIR}/ElephantTunHelper"
APP_CONTENTS="${TARGET_BUILD_DIR}/${CONTENTS_FOLDER_PATH}"
HELPER_OUTPUT="${APP_CONTENTS}/MacOS/${HELPER_NAME}"
BUILD_ARCH="${NATIVE_ARCH_ACTUAL:-${CURRENT_ARCH:-$(uname -m)}}"
if [ "${BUILD_ARCH}" = "undefined_arch" ]; then
  BUILD_ARCH="$(uname -m)"
fi

mkdir -p "${APP_CONTENTS}/MacOS"

xcrun swiftc \
  -target "${BUILD_ARCH}-apple-macos13.0" \
  -O \
  "${HELPER_DIR}/main.swift" \
  -o "${HELPER_OUTPUT}"

chmod 755 "${HELPER_OUTPUT}"

if [ -n "${EXPANDED_CODE_SIGN_IDENTITY:-}" ] && [ "${EXPANDED_CODE_SIGN_IDENTITY}" != "-" ]; then
  codesign --force --timestamp=none --options runtime --sign "${EXPANDED_CODE_SIGN_IDENTITY}" "${HELPER_OUTPUT}"
else
  codesign --force --timestamp=none --sign - "${HELPER_OUTPUT}"
fi
