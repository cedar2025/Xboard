const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '..');
const read = (relativePath) => fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');

test('admin system log list caps pagination and returns trimmed heavy fields', () => {
  const controller = read('app/Http/Controllers/V2/Admin/SystemController.php');

  assert.match(controller, /private const MAX_LOG_PAGE_SIZE = 50;/);
  assert.match(controller, /min\(self::MAX_LOG_PAGE_SIZE,\s*max\(10,\s*\(int\) \$request->input\('page_size', 20\)\)\)/);
  assert.match(controller, /DB::raw\('LEFT\(`data`, ' \. self::LOG_FIELD_PREVIEW_LENGTH \. '\) as data'\)/);
  assert.match(controller, /DB::raw\('LEFT\(`context`, ' \. self::LOG_FIELD_PREVIEW_LENGTH \. '\) as context'\)/);
  assert.doesNotMatch(controller, /\$res = \$builder->forPage\(\$current,\s*\$pageSize\)\s*->get\(\)/);
  assert.match(controller, /'page_size'\s*=>\s*\$pageSize/);
});

test('admin horizon failed jobs uses repository pagination and trims heavy payloads', () => {
  const controller = read('app/Http/Controllers/V2/Admin/SystemController.php');

  assert.match(controller, /private const MAX_FAILED_JOB_PAGE_SIZE = 50;/);
  assert.match(controller, /\$jobRepository->getFailed\(\$offset - 1\)/);
  assert.doesNotMatch(controller, /collect\(\$jobRepository->getFailed\(\)\)[\s\S]{0,160}->sortByDesc\('failed_at'\)[\s\S]{0,160}->slice\(\$offset, \$pageSize\)/);
  assert.match(controller, /'payload'\s*=>\s*\$this->truncateText\(\$job->payload \?\? null, self::FAILED_JOB_PAYLOAD_PREVIEW_LENGTH\)/);
  assert.match(controller, /'exception'\s*=>\s*\$this->truncateText\(\$job->exception \?\? null\)/);
});
