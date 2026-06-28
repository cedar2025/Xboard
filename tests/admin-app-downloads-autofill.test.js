const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

function extractFunction(source, name) {
  const signature = `function ${name}(`;
  const start = source.indexOf(signature);
  assert.notEqual(start, -1, `${name} should exist`);

  const bodyStart = source.indexOf('{', start);
  assert.notEqual(bodyStart, -1, `${name} should have a body`);

  let depth = 0;
  for (let index = bodyStart; index < source.length; index += 1) {
    const char = source[index];
    if (char === '{') depth += 1;
    if (char === '}') depth -= 1;
    if (depth === 0) {
      return source.slice(start, index + 1);
    }
  }

  throw new Error(`${name} body was not closed`);
}

function loadAdminAppDownloadHelpers() {
  const page = readRepoFile('resources/views/admin_app_downloads.blade.php');
  const context = {};

  vm.createContext(context);
  vm.runInContext([
    extractFunction(page, 'stripKnownExtension'),
    extractFunction(page, 'detectPlatform'),
    extractFunction(page, 'inferVersion'),
    extractFunction(page, 'slugifyAppKey'),
    extractFunction(page, 'inferAppKey'),
    extractFunction(page, 'titleCase'),
    extractFunction(page, 'inferAppName'),
    extractFunction(page, 'hasLegacyDecimalSpacing'),
    extractFunction(page, 'displayAppName'),
    'this.detectPlatform = detectPlatform;'
      + 'this.inferVersion = inferVersion;'
      + 'this.inferAppKey = inferAppKey;'
      + 'this.inferAppName = inferAppName;'
      + 'this.hasLegacyDecimalSpacing = hasLegacyDecimalSpacing;'
      + 'this.displayAppName = displayAppName;'
  ].join('\n'), context);

  return context;
}

test('admin app download autofill splits dotted app version labels from names', () => {
  const { inferAppName, inferVersion } = loadAdminAppDownloadHelpers();

  assert.equal(
    inferAppName('大象网络官方App Prd V1.0.apk'),
    '大象网络官方App Prd'
  );
  assert.equal(
    inferVersion('大象网络官方App Prd V1.0.apk'),
    '1.0'
  );
});

test('admin app download autofill still removes ordinary dotted package versions', () => {
  const { inferAppName } = loadAdminAppDownloadHelpers();

  assert.equal(
    inferAppName('Clash-Verge-2.5.1-x64.dmg'),
    'Clash Verge'
  );
});

test('admin app download autofill prefers stable official android app key', () => {
  const { detectPlatform, inferAppKey, inferAppName } = loadAdminAppDownloadHelpers();
  const filename = 'elephant-route-android-release-arm64-v1.1.apk';
  const platform = detectPlatform(filename);

  assert.equal(platform, 'android');
  assert.equal(inferAppName(filename), 'Elephant Route');
  assert.equal(inferAppKey(filename, inferAppName(filename), platform), 'elephant-route-android');
});

test('admin app download page keeps stored app names when the version is split separately', () => {
  const { hasLegacyDecimalSpacing, displayAppName } = loadAdminAppDownloadHelpers();

  assert.equal(
    hasLegacyDecimalSpacing('大象网络官方App Prd V1 0', '大象网络官方App Prd'),
    false
  );
  assert.equal(
    displayAppName({
      app: { name: '大象网络官方App Prd V1 0' },
      artifact: { original_name: '大象网络官方App Prd V1.0.apk' }
    }),
    '大象网络官方App Prd V1 0'
  );
});

test('admin app download publish form exposes app identity and version fields', () => {
  const page = readRepoFile('resources/views/admin_app_downloads.blade.php');

  assert.match(page, /<label>应用名称<input name="app_name"/);
  assert.match(page, /<label>应用标识<input name="app_key"/);
  assert.match(page, /同一个软件的后续版本必须保持一致/);
  assert.match(page, /<label>版本号<input name="version"/);
  assert.doesNotMatch(page, /<input type="hidden" name="app_key"/);
  assert.doesNotMatch(page, /<input type="hidden" name="version"/);
});

test('admin app package save uses app key as primary software identity', () => {
  const controller = readRepoFile('app/Http/Controllers/V2/Admin/AppPackageController.php');

  assert.match(controller, /if \(\$existingByKey\) \{[\s\S]*\$data\['id'\]\s*=\s*\$existingByKey->id;[\s\S]*\}/);
  assert.match(controller, /if \(empty\(\$data\['id'\]\) && empty\(\$data\['app_key'\]\)\)/);
  assert.doesNotMatch(controller, /应用标识已存在，请更换应用名称或标识/);
});
