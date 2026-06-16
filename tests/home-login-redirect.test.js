const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('home and legacy welcome routes redirect to the SPA login page', () => {
  const routes = readRepoFile('routes/web.php');

  assert.match(routes, /\$redirectToLogin = function \(Request \$request\)/);
  assert.match(routes, /return redirect\('\/app#\/login', 302\);/);
  assert.match(routes, /Route::get\('\/', \$redirectToLogin\);/);
  assert.match(routes, /Route::get\('\/welcome', \$redirectToLogin\);/);
});

test('legacy landing page is no longer served', () => {
  const routes = readRepoFile('routes/web.php');
  const landingRoot = path.join(repoRoot, 'public/landing');

  assert.doesNotMatch(routes, /public_path\('landing\/index\.html'\)/);
  assert.doesNotMatch(routes, /file_get_contents\(\$landingPagePath\)/);
  assert.doesNotMatch(routes, /Landing page not found/);
  assert.equal(fs.existsSync(path.join(repoRoot, 'public/landing/index.html')), false);
  assert.deepEqual(
    fs.readdirSync(landingRoot).filter((fileName) => fileName.endsWith('.html')),
    []
  );
});
