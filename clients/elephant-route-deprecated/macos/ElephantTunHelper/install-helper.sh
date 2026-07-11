#!/bin/sh
set -eu

LABEL="com.elphantroute.elephantNetwork.tunhelper"
APP_PATH="/Applications/大象网络.app"
RESOURCE_DIR="${APP_PATH}/Contents/Resources/ElephantTunHelper"
SOURCE_HELPER="${APP_PATH}/Contents/MacOS/ElephantTunHelper"
SOURCE_PLIST="${RESOURCE_DIR}/${LABEL}.legacy.plist"
HELPER_DEST="/Library/PrivilegedHelperTools/ElephantTunHelper"
PLIST_DEST="/Library/LaunchDaemons/${LABEL}.plist"
METADATA_DIR="/Library/Application Support/ElephantRoute"
METADATA_PATH="${METADATA_DIR}/helper-install.json"
LOG_DIR="/Library/Logs/ElephantRoute"

fail() {
  printf '%s\n' "$1" >&2
  exit 1
}

require_root() {
  [ "$(id -u)" -eq 0 ] || fail "Administrator privileges are required"
}

validate_sources() {
  [ ! -L "${APP_PATH}" ] || fail "Application path must not be a symbolic link"
  [ -d "${APP_PATH}" ] || fail "Install the app in /Applications first"
  [ -x "${SOURCE_HELPER}" ] || fail "Bundled helper is missing"
  [ ! -L "${SOURCE_HELPER}" ] || fail "Bundled helper must not be a symbolic link"
  [ -f "${SOURCE_PLIST}" ] || fail "Bundled LaunchDaemon plist is missing"
  [ ! -L "${SOURCE_PLIST}" ] || fail "Bundled plist must not be a symbolic link"

  /usr/bin/codesign --verify --strict "${SOURCE_HELPER}" \
    || fail "Bundled helper signature is invalid"
  /usr/bin/codesign -d --verbose=4 "${SOURCE_HELPER}" 2>&1 \
    | /usr/bin/grep -q "Identifier=${LABEL}" \
    || fail "Bundled helper identifier is invalid"
  /usr/bin/file "${SOURCE_HELPER}" | /usr/bin/grep -q "arm64" \
    || fail "Bundled helper is not arm64"
  /usr/bin/plutil -lint "${SOURCE_PLIST}" >/dev/null \
    || fail "Bundled LaunchDaemon plist is invalid"
}

bootout_existing() {
  /bin/launchctl bootout "system/${LABEL}" >/dev/null 2>&1 || true
  /bin/launchctl bootout system "${PLIST_DEST}" >/dev/null 2>&1 || true
}

install_helper() {
  validate_sources
  bootout_existing

  /bin/mkdir -p /Library/PrivilegedHelperTools /Library/LaunchDaemons \
    "${METADATA_DIR}" "${LOG_DIR}"
  /usr/sbin/chown root:wheel /Library/PrivilegedHelperTools \
    /Library/LaunchDaemons "${METADATA_DIR}" "${LOG_DIR}"
  /bin/chmod 0755 /Library/PrivilegedHelperTools /Library/LaunchDaemons \
    "${METADATA_DIR}" "${LOG_DIR}"

  helper_tmp="$(/usr/bin/mktemp "${HELPER_DEST}.new.XXXXXX")"
  plist_tmp="$(/usr/bin/mktemp "${PLIST_DEST}.new.XXXXXX")"
  helper_backup="${HELPER_DEST}.backup.$$"
  plist_backup="${PLIST_DEST}.backup.$$"

  cleanup() {
    /bin/rm -f "${helper_tmp}" "${plist_tmp}" \
      "${helper_backup}" "${plist_backup}"
  }
  trap cleanup EXIT HUP INT TERM

  /usr/bin/install -o root -g wheel -m 0755 "${SOURCE_HELPER}" "${helper_tmp}"
  /usr/bin/install -o root -g wheel -m 0644 "${SOURCE_PLIST}" "${plist_tmp}"
  /usr/bin/plutil -lint "${plist_tmp}" >/dev/null

  [ ! -e "${HELPER_DEST}" ] || /bin/cp -p "${HELPER_DEST}" "${helper_backup}"
  [ ! -e "${PLIST_DEST}" ] || /bin/cp -p "${PLIST_DEST}" "${plist_backup}"
  /bin/mv -f "${helper_tmp}" "${HELPER_DEST}"
  /bin/mv -f "${plist_tmp}" "${PLIST_DEST}"

  if ! /bin/launchctl bootstrap system "${PLIST_DEST}"; then
    /bin/rm -f "${HELPER_DEST}" "${PLIST_DEST}"
    if [ -e "${helper_backup}" ] && [ -e "${plist_backup}" ]; then
      /bin/mv "${helper_backup}" "${HELPER_DEST}"
      /bin/mv "${plist_backup}" "${PLIST_DEST}"
      /bin/launchctl bootstrap system "${PLIST_DEST}" >/dev/null 2>&1 || true
    fi
    fail "Failed to bootstrap ElephantTunHelper"
  fi

  /bin/launchctl enable "system/${LABEL}" >/dev/null 2>&1 || true
  /bin/launchctl kickstart -k "system/${LABEL}" >/dev/null 2>&1 || true

  helper_hash="$(/usr/bin/shasum -a 256 "${HELPER_DEST}" | /usr/bin/awk '{print $1}')"
  printf '{"label":"%s","helper_sha256":"%s"}\n' \
    "${LABEL}" "${helper_hash}" > "${METADATA_PATH}"
  /usr/sbin/chown root:wheel "${METADATA_PATH}"
  /bin/chmod 0644 "${METADATA_PATH}"
  cleanup
  trap - EXIT HUP INT TERM
}

uninstall_helper() {
  bootout_existing
  /usr/bin/pkill -f \
    'Library/Application Support/com.elphantroute.elephantNetwork/sing-box/sing-box-darwin' \
    >/dev/null 2>&1 || true
  /sbin/route -n delete -net 0.0.0.0/1 >/dev/null 2>&1 || true
  /sbin/route -n delete -net 128.0.0.0/1 >/dev/null 2>&1 || true
  /sbin/route -n delete -net 1.0.0.0/8 >/dev/null 2>&1 || true
  /sbin/route -n delete -net 198.18.0.0/15 >/dev/null 2>&1 || true
  /bin/rm -f "${HELPER_DEST}" "${PLIST_DEST}" "${METADATA_PATH}"
  /bin/rmdir "${METADATA_DIR}" >/dev/null 2>&1 || true
}

require_root
case "${1:-}" in
  install)
    install_helper
    ;;
  uninstall)
    uninstall_helper
    ;;
  *)
    fail "Usage: install-helper.sh install|uninstall"
    ;;
esac
