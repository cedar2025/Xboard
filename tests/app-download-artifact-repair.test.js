const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('app download artifact repair command is dry-run by default with apply and scan options', () => {
  const command = readRepoFile('app/Console/Commands/RepairAppDownloadArtifacts.php');

  assert.match(command, /protected \$signature = 'app-downloads:repair-artifacts/);
  assert.match(command, /\{--apply : Update missing artifact records when a unique candidate is found\}/);
  assert.match(command, /\{--scan=\* : Additional absolute directories to scan for existing package files\}/);
  assert.match(command, /\$apply\s*=\s*\(bool\) \$this->option\('apply'\)/);
  assert.match(command, /DRY RUN/);
});

test('app download artifact repair scans configured storage and extra absolute directories', () => {
  const command = readRepoFile('app/Console/Commands/RepairAppDownloadArtifacts.php');

  assert.match(command, /config\('filesystems\.disks\.app_downloads\.root'/);
  assert.match(command, /\$this->option\('scan'\)/);
  assert.match(command, /realpath\(\$scanPath\)/);
  assert.match(command, /is_dir\(\$realPath\)/);
  assert.match(command, /scanPackageFiles/);
});

test('app download artifact repair matches sha256 before strict metadata fallback', () => {
  const command = readRepoFile('app/Console/Commands/RepairAppDownloadArtifacts.php');

  assert.match(command, /findCandidates\(AppArtifact \$artifact, array \$candidates\)/);
  assert.match(command, /hash_equals\(\$artifact->sha256, \$candidate\['sha256'\]\)/);
  assert.match(command, /original_name/);
  assert.match(command, /file_size/);
  assert.match(command, /extension/);

  const shaMatch = command.indexOf("hash_equals($artifact->sha256, $candidate['sha256'])");
  const fallbackMatch = command.indexOf("$candidate['original_name']");
  assert.ok(shaMatch !== -1, 'sha256 match should exist');
  assert.ok(fallbackMatch !== -1, 'metadata fallback should exist');
  assert.ok(shaMatch < fallbackMatch, 'sha256 matching should be attempted before metadata fallback');
});

test('app download artifact repair only applies unique bindable matches', () => {
  const command = readRepoFile('app/Console/Commands/RepairAppDownloadArtifacts.php');

  assert.match(command, /count\(\$matches\) !== 1/);
  assert.match(command, /isCandidateBindable/);
  assert.match(command, /forceFill\(\[/);
  assert.match(command, /'disk'\s*=>\s*self::DISK/);
  assert.match(command, /'path'\s*=>\s*\$match\['relative_path'\]/);
  assert.match(command, /'file_size'\s*=>\s*\$match\['file_size'\]/);
  assert.match(command, /'sha256'\s*=>\s*\$match\['sha256'\]/);
  assert.match(command, /->save\(\)/);
});
