const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

function extractBetween(source, startMarker, endMarker) {
  const start = source.indexOf(startMarker);
  assert.notEqual(start, -1, `${startMarker} exists`);

  const end = source.indexOf(endMarker, start);
  assert.notEqual(end, -1, `${endMarker} exists after ${startMarker}`);

  return source.slice(start, end);
}

function assertOrderIncomeUsesPaidAt(source, variableName) {
  const variableIndex = source.indexOf(`$${variableName} = Order::where(`);
  assert.notEqual(variableIndex, -1, `${variableName} income query exists`);

  const query = source.slice(variableIndex, source.indexOf('->sum(', variableIndex));
  assert.match(query, /Order::where\('paid_at',\s*'>='/, `${variableName} starts from paid_at`);
  assert.match(query, /->where\('paid_at',\s*'<'/, `${variableName} ends before paid_at boundary`);
  assert.doesNotMatch(query, /Order::where\('created_at'/, `${variableName} does not start from created_at`);
}

test('admin realtime income statistics use paid_at instead of order creation time', () => {
  const controller = readRepoFile('app/Http/Controllers/V2/Admin/StatController.php');

  const getOverride = extractBetween(controller, 'public function getOverride', 'public function getOrder');
  const getStats = extractBetween(controller, 'public function getStats', 'public function getTrafficRank');

  for (const fieldName of ['month_income', 'day_income', 'last_month_income']) {
    const fieldIndex = getOverride.indexOf(`'${fieldName}' => Order::where(`);
    assert.notEqual(fieldIndex, -1, `${fieldName} query exists`);

    const query = getOverride.slice(fieldIndex, getOverride.indexOf('->sum(', fieldIndex));
    assert.match(query, /Order::where\('paid_at',\s*'>='/, `${fieldName} starts from paid_at`);
    assert.match(query, /->where\('paid_at',\s*'<'/, `${fieldName} ends before paid_at boundary`);
    assert.doesNotMatch(query, /Order::where\('created_at'/, `${fieldName} does not start from created_at`);
  }

  for (const variableName of [
    'todayIncome',
    'yesterdayIncome',
    'currentMonthIncome',
    'lastMonthIncome',
    'twoMonthsAgoIncome',
  ]) {
    assertOrderIncomeUsesPaidAt(getStats, variableName);
  }
});

test('non-income dashboard creation metrics keep using created_at', () => {
  const controller = readRepoFile('app/Http/Controllers/V2/Admin/StatController.php');

  assert.match(controller, /'month_register_total'\s*=>\s*User::where\('created_at'/);
  assert.match(controller, /\$currentMonthNewUsers\s*=\s*User::where\('created_at'/);
  assert.match(controller, /\$lastMonthNewUsers\s*=\s*User::where\('created_at'/);
});
