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
    extractFunction(page, 'titleCase'),
    extractFunction(page, 'inferAppName'),
    extractFunction(page, 'hasLegacyDecimalSpacing'),
    extractFunction(page, 'displayAppName'),
    'this.inferAppName = inferAppName;'
      + 'this.hasLegacyDecimalSpacing = hasLegacyDecimalSpacing;'
      + 'this.displayAppName = displayAppName;'
  ].join('\n'), context);

  return context;
}

test('admin app download autofill preserves dotted app version labels in names', () => {
  const { inferAppName } = loadAdminAppDownloadHelpers();

  assert.equal(
    inferAppName('大象网络官方App Prd V1.0.apk'),
    '大象网络官方App Prd V1.0'
  );
});

test('admin app download autofill still removes ordinary dotted package versions', () => {
  const { inferAppName } = loadAdminAppDownloadHelpers();

  assert.equal(
    inferAppName('Clash-Verge-2.5.1-x64.dmg'),
    'Clash Verge'
  );
});

test('admin app download page repairs legacy app names that only lost decimal points', () => {
  const { hasLegacyDecimalSpacing, displayAppName } = loadAdminAppDownloadHelpers();

  assert.equal(
    hasLegacyDecimalSpacing('大象网络官方App Prd V1 0', '大象网络官方App Prd V1.0'),
    true
  );
  assert.equal(
    displayAppName({
      app: { name: '大象网络官方App Prd V1 0' },
      artifact: { original_name: '大象网络官方App Prd V1.0.apk' }
    }),
    '大象网络官方App Prd V1.0'
  );
});
