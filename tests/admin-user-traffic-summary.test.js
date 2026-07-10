const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '..');
const read = (relativePath) => fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');

test('admin user list aggregates plan and traffic package usage with active-plan semantics', () => {
  const controller = read('app/Http/Controllers/V2/Admin/UserController.php');

  assert.match(controller, /DB::table\('v2_user_traffic_packages'\)/);
  assert.match(controller, /SUM\(total_bytes\) AS traffic_package_total/);
  assert.match(controller, /SUM\(total_bytes - remaining_bytes\) AS traffic_package_used/);
  assert.match(controller, /AS traffic_package_remaining/);
  assert.match(controller, /leftJoinSub\(\$trafficPackageSummary/);
  assert.match(controller, /CASE WHEN[\s\S]*expired_at IS NULL[\s\S]*expired_at > \{\$timestamp\}/);
  assert.match(controller, /AS plan_transfer_enable/);
  assert.match(controller, /AS total_used/);
  assert.match(controller, /latest_traffic_package_name/);
  assert.match(controller, /'has_active_plan'/);
  assert.match(controller, /'active_product_name'/);
});

test('admin user traffic sorting and filtering use displayed aggregate values', () => {
  const controller = read('app/Http/Controllers/V2/Admin/UserController.php');

  assert.match(controller, /'plan_transfer_enable'\s*=>/);
  assert.match(controller, /'traffic_package_total'\s*=>/);
  assert.match(controller, /'total_used'\s*=>/);
  assert.match(controller, /applyQueryCondition\(\$query, \$queryField/);
  assert.match(controller, /orderByRaw\("\{\$queryField\} \{\$direction\}"/);
});

test('shared user transformer remains compatible with non-list queries such as tickets', () => {
  const controller = read('app/Http/Controllers/V2/Admin/UserController.php');

  assert.match(controller, /\$attributes = \$user->getAttributes\(\)/);
  assert.match(controller, /array_key_exists\('has_active_plan', \$attributes\)/);
  assert.match(controller, /\$attributes\['traffic_package_remaining'\] \?\? 0/);
  assert.match(controller, /\$data\['plan_transfer_enable'\] \?\?/);
  assert.match(controller, /\$data\['total_used'\] \?\?/);
});

test('admin user table splits traffic totals and hides expired plan names', () => {
  const asset = read('public/assets/admin/assets/index.js');
  const subscriptionColumnStart = asset.indexOf('accessorKey:"plan_id",header:');
  const groupColumnStart = asset.indexOf('{accessorKey:"group_id",header:', subscriptionColumnStart);
  const subscriptionColumn = asset.slice(subscriptionColumnStart, groupColumnStart);

  assert.ok(subscriptionColumnStart >= 0 && groupColumnStart > subscriptionColumnStart);
  assert.match(subscriptionColumn, /active_product_name/);
  assert.doesNotMatch(subscriptionColumn, /plan\?\.name/);
  assert.match(asset, /accessorKey:"plan_transfer_enable"/);
  assert.match(asset, /columns\.plan_traffic/);
  assert.match(asset, /accessorKey:"traffic_package_total"/);
  assert.match(asset, /columns\.traffic_package_traffic/);
  assert.match(asset, /d=\(i\.original\?\.plan_transfer_enable\|\|0\)\+\(i\.original\?\.traffic_package_total\|\|0\)/);
  assert.match(asset, /value:"plan_transfer_enable",type:"number",unit:"GB"/);
  assert.match(asset, /value:"traffic_package_total",type:"number",unit:"GB"/);
});

test('admin locales label plan and traffic package columns', () => {
  const expectedLabels = {
    'public/assets/admin/locales/zh-CN.js': ['"plan_traffic": "套餐流量"', '"traffic_package_traffic": "流量包流量"'],
    'public/assets/admin/locales/en-US.js': ['"plan_traffic": "Plan Traffic"', '"traffic_package_traffic": "Traffic Package Traffic"'],
    'public/assets/admin/locales/ko-KR.js': ['"plan_traffic": "요금제 트래픽"', '"traffic_package_traffic": "트래픽 패키지"'],
  };

  for (const [relativePath, labels] of Object.entries(expectedLabels)) {
    const locale = read(relativePath);
    for (const label of labels) {
      assert.match(locale, new RegExp(label));
    }
  }
});
