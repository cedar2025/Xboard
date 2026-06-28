const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('guest app update endpoint scopes updates by app key and signed artifact URL', () => {
  const controller = readRepoFile('app/Http/Controllers/V1/Guest/AppUpdateController.php');

  assert.match(controller, /use App\\Models\\DistributionApp;/);
  assert.match(controller, /use Illuminate\\Support\\Facades\\URL;/);
  assert.match(controller, /'app_key'\s*=>\s*'nullable\|string\|max:64'/);
  assert.match(controller, /DistributionApp::where\('app_key',\s*\$appKey\)/);
  assert.match(controller, /->where\('is_active',\s*true\)/);
  assert.match(controller, /->where\('app_id',\s*\$app->id\)/);
  assert.match(controller, /whereHas\('artifact'\)/);
  assert.match(controller, /URL::temporarySignedRoute\(\s*'app-downloads\.download'/);
  assert.match(controller, /'artifact'\s*=>\s*\$latest->artifact->id/);
  assert.match(controller, /false\s*\);/);
});

test('android client uses official app identity and current update endpoint', () => {
  const constants = readRepoFile('clients/elephant-route-deprecated/lib/utils/constants.dart');
  const service = readRepoFile('clients/elephant-route-deprecated/lib/core/api/services/app_update_service.dart');

  assert.match(constants, /androidAppDistributionAppKey/);
  assert.match(constants, /defaultValue:\s*'elephant-route-android'/);
  assert.match(constants, /static const String appUpdate = '\/api\/v1\/app\/update'/);
  assert.match(service, /Platform\.isAndroid/);
  assert.match(service, /return 'android';/);
  assert.match(service, /bool get _isSupportedPlatform =>[\s\S]*Platform\.isAndroid/);
  assert.match(service, /'app_key': _appKey/);
  assert.match(service, /ApiConstants\.androidAppDistributionAppKey/);
});

test('android update download verifies sha256 and delegates APK install natively', () => {
  const service = readRepoFile('clients/elephant-route-deprecated/lib/core/api/services/app_update_service.dart');
  const provider = readRepoFile('clients/elephant-route-deprecated/lib/providers/app_update_provider.dart');
  const dialog = readRepoFile('clients/elephant-route-deprecated/lib/widgets/app_update_dialog.dart');

  assert.match(service, /package:crypto\/crypto\.dart/);
  assert.match(service, /Future<File> downloadUpdate\(/);
  assert.match(service, /sha256\.convert/);
  assert.match(service, /Future<void> installDownloadedApk\(/);
  assert.match(service, /MethodChannel\('com\.elephant\.network\/update'\)/);
  assert.match(provider, /Future<bool> downloadAndInstallUpdate\(/);
  assert.match(provider, /downloadProgress/);
  assert.match(dialog, /downloadAndInstallUpdate\(\)/);
  assert.match(dialog, /正在下载/);
  assert.doesNotMatch(dialog, /openDownloadPage\(\)/);
});

test('android native layer can expose ABI and install downloaded APK files', () => {
  const manifest = readRepoFile('clients/elephant-route-deprecated/android/app/src/main/AndroidManifest.xml');
  const mainActivity = readRepoFile('clients/elephant-route-deprecated/android/app/src/main/kotlin/com/elephantroute/MainActivity.kt');
  const paths = readRepoFile('clients/elephant-route-deprecated/android/app/src/main/res/xml/update_file_paths.xml');

  assert.match(manifest, /android\.permission\.REQUEST_INSTALL_PACKAGES/);
  assert.match(manifest, /androidx\.core\.content\.FileProvider/);
  assert.match(manifest, /\$\{applicationId\}\.fileprovider/);
  assert.match(manifest, /@xml\/update_file_paths/);
  assert.match(paths, /<cache-path/);
  assert.match(mainActivity, /UPDATE_CHANNEL = "com\.elephant\.network\/update"/);
  assert.match(mainActivity, /"primaryAbi"/);
  assert.match(mainActivity, /Build\.SUPPORTED_ABIS/);
  assert.match(mainActivity, /"installApk"/);
  assert.match(mainActivity, /FileProvider\.getUriForFile/);
  assert.match(mainActivity, /application\/vnd\.android\.package-archive/);
  assert.match(mainActivity, /ACTION_MANAGE_UNKNOWN_APP_SOURCES/);
});
