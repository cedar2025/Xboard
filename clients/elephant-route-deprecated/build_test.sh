#!/bin/bash
set -e

# Configuration
# Note: For test environment, you might want to specify a different BASE_URL if needed.
# defaulting to local/dev URL logic if not specified.
# TEST_URL="http://test-api.example.com" 

APP_LABEL="大象网络-测试"
OUTPUT_NAME="app-release-test.apk"
BUILD_DIR="build/app/outputs/flutter-apk"
SOURCE_APK="$BUILD_DIR/app-release.apk"
TARGET_APK="$BUILD_DIR/$OUTPUT_NAME"

echo "🧪 Starting Test Build..."
echo "🏷️  App Label: $APP_LABEL"
echo "📦 Output Filename: $OUTPUT_NAME"

# Clean build
echo "🧹 Cleaning previous build..."
flutter clean

# Build APK
# Exporting APP_LABEL environment variable for Gradle to pick up
export APP_LABEL="$APP_LABEL"

echo "🔨 Building APK..."
# If you have a specific test URL, add --dart-define=BASE_URL="$TEST_URL" here
flutter build apk --release

# Rename
if [ -f "$SOURCE_APK" ]; then
    echo "📝 Renaming artifact..."
    mv "$SOURCE_APK" "$TARGET_APK"
    echo "✅ Test Build Successful!"
    echo "📂 Output: $(pwd)/$TARGET_APK"
else
    echo "❌ Build Failed: Output APK not found."
    exit 1
fi
