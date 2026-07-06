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

test('app download returns a clear error when the stored file is missing', () => {
  const storage = readRepoFile('app/Services/AppArtifactStorage.php');
  const guestController = readRepoFile('app/Http/Controllers/V1/Guest/AppDownloadController.php');

  assert.match(storage, /public function exists\(AppArtifact \$artifact\): bool/);
  assert.match(guestController, /use Illuminate\\Support\\Facades\\Log;/);
  assert.match(guestController, /\$downloadPath\s*=\s*\$storage->absolutePath\(\$artifact\);/);
  assert.match(guestController, /!\$storage->exists\(\$artifact\)/);
  assert.match(guestController, /App download artifact file missing/);
  assert.match(guestController, /安装包文件不存在，请联系管理员修复文件绑定/);

  const missingFileCheck = guestController.indexOf('$storage->exists($artifact)');
  const downloadLogCreate = guestController.indexOf('AppDownloadLog::create');
  const responseDownload = guestController.indexOf('response()->download');
  assert.ok(missingFileCheck !== -1, 'missing file check should exist');
  assert.ok(downloadLogCreate !== -1, 'download log creation should exist');
  assert.ok(responseDownload !== -1, 'download response should exist');
  assert.ok(missingFileCheck < downloadLogCreate, 'missing file check should happen before logging a download');
  assert.ok(missingFileCheck < responseDownload, 'missing file check should happen before streaming the file');
});
