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
