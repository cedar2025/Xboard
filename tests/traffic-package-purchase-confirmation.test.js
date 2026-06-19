const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

const frontendBundles = [
  'public/assets/umi.js',
  'theme/Xboard/assets/umi.js',
  'theme/ElephantRoute/assets/umi.js',
  'public/theme/ElephantRoute/assets/umi.js',
];

test('one-time traffic package purchase does not show subscription replacement warning', () => {
  for (const bundlePath of frontendBundles) {
    if (!fs.existsSync(path.join(repoRoot, bundlePath))) {
      continue;
    }

    const bundle = readRepoFile(bundlePath);
    assert.match(
      bundle,
      /R=\(\)=>b\.value!=="onetime_price"&&n\.plan_id&&n\.plan_id!=i\.value&&\(n\.expired_at===null\|\|n\.expired_at>=Math\.floor\(Date\.now\(\)\/1e3\)\)/,
      `${bundlePath} should skip subscription replacement warning for onetime traffic packages`
    );
    assert.match(
      bundle,
      /请注意，变更订阅会导致当前订阅被覆盖。/,
      `${bundlePath} should keep the replacement warning for normal plan changes`
    );
  }
});
