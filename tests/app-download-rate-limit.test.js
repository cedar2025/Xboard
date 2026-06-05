const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('app download prepare route uses named rate limiter', () => {
  const route = readRepoFile('app/Http/Routes/V1/UserRoute.php');

  assert.match(route, /\/app-downloads\/\{artifact\}\/prepare/);
  assert.match(route, /->middleware\('throttle:app-download-prepare'\)/);
  assert.doesNotMatch(route, /->middleware\('throttle:10,1'\)/);
});

test('app download links use relative signed URLs to avoid insecure redirects', () => {
  const userController = readRepoFile('app/Http/Controllers/V1/User/AppDownloadController.php');
  const guestRoute = readRepoFile('app/Http/Routes/V1/GuestRoute.php');

  assert.match(userController, /temporarySignedRoute\([\s\S]*false\s*\)/);
  assert.match(guestRoute, /'signed:relative'/);
  assert.doesNotMatch(guestRoute, /->middleware\(\['signed', 'throttle:30,1'\]\)/);
});

test('app download prepare limiter is scoped by user and artifact', () => {
  const provider = readRepoFile('app/Providers/RouteServiceProvider.php');

  assert.match(provider, /RateLimiter::for\('app-download-prepare'/);
  assert.match(provider, /Limit::perMinute\(60\)/);
  assert.match(provider, /request->user\(\)\?->id/);
  assert.match(provider, /request->route\('artifact'\)/);
  assert.match(provider, /下载请求过于频繁，请稍后重试。/);
});

test('download page handles prepare 429 without resetting turnstile', () => {
  const page = readRepoFile('public/download/index.html');

  assert.match(page, /response\.status === 429/);
  assert.match(page, /下载请求过于频繁，请稍后重试。/);

  const branchStart = page.indexOf('response.status === 429');
  const nextFailureBranch = page.indexOf('if (!response.ok', branchStart);
  assert.notEqual(branchStart, -1);
  assert.notEqual(nextFailureBranch, -1);

  const rateLimitBranch = page.slice(branchStart, nextFailureBranch);
  assert.match(rateLimitBranch, /setVerificationStatus/);
  assert.match(rateLimitBranch, /return/);
  assert.doesNotMatch(rateLimitBranch, /turnstile\.reset/);
});

test('android apk downloads use package archive mime even when stored mime is zip', () => {
  const storage = readRepoFile('app/Services/AppArtifactStorage.php');
  const guestController = readRepoFile('app/Http/Controllers/V1/Guest/AppDownloadController.php');

  assert.match(storage, /application\/vnd\.android\.package-archive/);
  assert.match(storage, /downloadMimeType\(AppArtifact \$artifact\)/);
  assert.match(storage, /strtolower\(\$artifact->extension/);
  assert.match(guestController, /\$storage->downloadMimeType\(\$artifact\)/);
  assert.doesNotMatch(guestController, /\['Content-Type' => \$artifact->mime_type \?: 'application\/octet-stream'\]/);
});
