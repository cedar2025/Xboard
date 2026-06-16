const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('domain check endpoint is public under guest routes', () => {
  const route = readRepoFile('app/Http/Routes/V1/GuestRoute.php');

  assert.match(route, /use App\\Http\\Controllers\\V1\\Guest\\DomainCheckController;/);
  assert.match(route, /->match\(\['get', 'head', 'options'\], '\/domain\/check', \[DomainCheckController::class, 'check'\]\)/);
});

test('domain check response exposes only reachability metadata', () => {
  const controller = readRepoFile('app/Http/Controllers/V1/Guest/DomainCheckController.php');

  assert.match(controller, /'ok'\s*=>\s*true/);
  assert.match(controller, /'host'\s*=>\s*\$request->getHost\(\)/);
  assert.match(controller, /'timestamp'\s*=>\s*now\(\)->timestamp/);
  assert.match(controller, /'request_id'\s*=>\s*\(string\) Str::uuid\(\)/);
  assert.match(controller, /'Cache-Control'\s*=>\s*'no-store, no-cache, must-revalidate, max-age=0'/);
  assert.match(controller, /'Access-Control-Allow-Origin'\s*=>\s*'\*'/);
  assert.match(controller, /'Access-Control-Allow-Methods'\s*=>\s*'GET, HEAD, OPTIONS'/);
  assert.match(controller, /'Access-Control-Allow-Headers'\s*=>\s*'Content-Type, Authorization, X-Requested-With'/);
  assert.doesNotMatch(controller, /schedule|horizon|logs|admin_setting\(/);
});
