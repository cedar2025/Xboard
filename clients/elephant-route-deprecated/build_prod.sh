#!/bin/bash
set -e

# Configuration
PROD_URL="https://www.elephant223.com/"
OUTPUT_NAME="app-release-prd.apk"
BUILD_DIR="build/app/outputs/flutter-apk"
SOURCE_APK="$BUILD_DIR/app-release.apk"
TARGET_APK="$BUILD_DIR/$OUTPUT_NAME"

echo "🚀 Starting Production Build..."
echo "📍 API Base URL: $PROD_URL"
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
    echo "✅ Build Successful!"
    echo "📂 Output: $(pwd)/$TARGET_APK"
else
    echo "❌ Build Failed: Output APK not found."
    exit 1
fi
