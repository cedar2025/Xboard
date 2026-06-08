const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('admin ticket table rows delegate clicks to the existing view-detail action', () => {
  const blade = readRepoFile('resources/views/admin.blade.php');

  assert.match(blade, /xboard-admin-ticket-row-click/);
  assert.match(blade, /function isTicketRoute\(\)/);
  assert.match(blade, /#\\\/\?user\\\/ticket/);
  assert.match(blade, /function isInteractiveTarget\(target\)/);
  assert.match(blade, /button, a, input, textarea, select/);
  assert.match(blade, /function findTicketViewButton\(row\)/);
  assert.match(blade, /VIEW_DETAIL_TITLES/);
  assert.match(blade, /查看详情/);
  assert.match(blade, /View Details/);
  assert.match(blade, /상세 보기/);
  assert.match(blade, /document\.addEventListener\(["']click["']/);
  assert.match(blade, /event\.preventDefault\(\)/);
  assert.match(blade, /viewButton\.click\(\)/);
});
