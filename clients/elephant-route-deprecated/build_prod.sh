#!/bin/bash
set -e

# Configuration
PROD_URL="https://www.elephant223.com/"
VERSION_FILE="android_release_version.txt"
BUILD_DIR="build/app/outputs/flutter-apk"
SOURCE_APK="$BUILD_DIR/app-release.apk"

increment_minor_version() {
    local current_version="$1"
    local major="${current_version%%.*}"
    local minor="${current_version##*.}"

    if ! [[ "$major" =~ ^[0-9]+$ && "$minor" =~ ^[0-9]+$ ]]; then
        echo "❌ Invalid version in $VERSION_FILE: $current_version" >&2
        exit 1
    fi

    echo "$major.$((minor + 1))"
}

if [ -f "$VERSION_FILE" ]; then
    LAST_VERSION="$(tr -d '[:space:]' < "$VERSION_FILE")"
    RELEASE_VERSION="$(increment_minor_version "$LAST_VERSION")"
else
    RELEASE_VERSION="1.0"
fi

OUTPUT_NAME="app-release-prd-V$RELEASE_VERSION.apk"
TARGET_APK="$BUILD_DIR/$OUTPUT_NAME"

echo "🚀 Starting Production Build..."
echo "📍 API Base URL: $PROD_URL"
echo "🏷️ APK Version: V$RELEASE_VERSION"
echo "📦 Output Filename: $OUTPUT_NAME"
echo "🧩 Android ABI: arm64-v8a"

# Clean build
echo "🧹 Cleaning previous build..."
flutter clean

# Build APK
echo "🔨 Building APK..."
ELEPHANT_ANDROID_ABIS="arm64-v8a" flutter build apk --release \
    --target-platform android-arm64 \
    --dart-define=BASE_URL="$PROD_URL"

# Rename
if [ -f "$SOURCE_APK" ]; then
    echo "📝 Renaming artifact..."
    mv "$SOURCE_APK" "$TARGET_APK"
    echo "$RELEASE_VERSION" > "$VERSION_FILE"
    echo "✅ Build Successful!"
    echo "📂 Output: $(pwd)/$TARGET_APK"
    echo "🧾 Recorded latest APK version: V$RELEASE_VERSION"
else
    echo "❌ Build Failed: Output APK not found."
    exit 1
fi
