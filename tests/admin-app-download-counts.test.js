const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('admin app package versions include per-artifact download counts', () => {
  const artifactModel = readRepoFile('app/Models/AppArtifact.php');
  const controller = readRepoFile('app/Http/Controllers/V2/Admin/AppPackageController.php');

  assert.match(artifactModel, /use Illuminate\\Database\\Eloquent\\Relations\\HasMany;/);
  assert.match(artifactModel, /public function downloadLogs\(\): HasMany/);
  assert.match(artifactModel, /return \$this->hasMany\(AppDownloadLog::class, 'app_artifact_id'\);/);
  assert.match(controller, /'artifact'\s*=>\s*fn\s*\(\s*\$query\s*\)\s*=>\s*\$query->withCount\('downloadLogs'\)/);
});

test('admin app download table renders download count after package column', () => {
  const page = readRepoFile('resources/views/admin_app_downloads.blade.php');

  const packageHeaderIndex = page.indexOf('<th>安装包</th>');
  const countHeaderIndex = page.indexOf('<th>下载次数</th>');
  const statusHeaderIndex = page.indexOf('<th>状态</th>');
  assert.ok(packageHeaderIndex !== -1, 'package header should exist');
  assert.ok(countHeaderIndex !== -1, 'download count header should exist');
  assert.ok(statusHeaderIndex !== -1, 'status header should exist');
  assert.ok(packageHeaderIndex < countHeaderIndex, 'download count should follow package header');
  assert.ok(countHeaderIndex < statusHeaderIndex, 'download count should precede status header');

  assert.match(page, /downloadCount\s*=\s*artifact\s*\?\s*Number\(artifact\.download_logs_count\s*\|\|\s*0\)\s*:\s*0/);
  assert.match(page, /row\.children\[3\]\.textContent\s*=\s*downloadCount\.toLocaleString\("zh-CN"\)/);
});
