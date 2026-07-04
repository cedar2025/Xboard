const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('send email job reports the original mail error to Horizon', () => {
  const job = readRepoFile('app/Jobs/SendEmailJob.php');

  assert.match(job, /use RuntimeException;/);
  assert.match(job, /public \$backoff = \[/);
  assert.match(job, /throw new RuntimeException\(/);
  assert.match(job, /\$mailLog\['error'\]/);
  assert.doesNotMatch(job, /->release\(/);
});

test('send email job uses a global queue rate limiter', () => {
  const job = readRepoFile('app/Jobs/SendEmailJob.php');
  const provider = readRepoFile('app/Providers/RouteServiceProvider.php');

  assert.match(job, /use Illuminate\\Queue\\Middleware\\RateLimitedWithRedis;/);
  assert.match(job, /public function middleware\(\): array/);
  assert.match(job, /new RateLimitedWithRedis\('send-email'\)/);
  assert.match(job, /->releaseAfter\(1\)/);

  assert.match(provider, /RateLimiter::for\('send-email'/);
  assert.match(provider, /Limit::perSecond\(8\)/);
  assert.match(provider, /->by\('smtp'\)/);
});

test('horizon runs email queues in a low-concurrency supervisor', () => {
  const horizon = readRepoFile('config/horizon.php');

  assert.match(horizon, /'XboardEmail'/);
  assert.match(horizon, /'queue'\s*=>\s*\[\s*'send_email',\s*'send_email_mass'\s*\]/);
  assert.match(horizon, /'maxProcesses'\s*=>\s*2/);

  const mainSupervisor = horizon.match(/'Xboard'\s*=>\s*\[[\s\S]*?\n\s{12}\],/);
  assert.ok(mainSupervisor, 'expected main Xboard supervisor config');
  assert.doesNotMatch(mainSupervisor[0], /'send_email'/);
  assert.doesNotMatch(mainSupervisor[0], /'send_email_mass'/);
});
