const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('subscription URLs fall back to the configured site URL before Laravel APP_URL', () => {
  const helper = readRepoFile('app/Utils/Helper.php');

  assert.match(helper, /admin_setting\('app_url'/);

  const subscribeUrlIndex = helper.indexOf("admin_setting('subscribe_url'");
  const appUrlIndex = helper.indexOf("admin_setting('app_url'");
  const laravelUrlIndex = helper.indexOf('url($path)');

  assert.notEqual(subscribeUrlIndex, -1, 'subscribe_url setting is checked');
  assert.notEqual(appUrlIndex, -1, 'app_url setting is checked');
  assert.notEqual(laravelUrlIndex, -1, 'Laravel url fallback remains available');
  assert.ok(subscribeUrlIndex < appUrlIndex, 'dedicated subscribe_url keeps priority');
  assert.ok(appUrlIndex < laravelUrlIndex, 'app_url is used before Laravel APP_URL fallback');
  assert.match(helper, /rtrim\(\$appUrl,\s*'\/'\)\s*\.\s*\$path/);
});
