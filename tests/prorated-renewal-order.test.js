const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '..');
const read = (relativePath) => fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');

test('same-plan active renewals calculate capped prorated credit from remaining time and traffic', () => {
  const orderService = read('app/Services/OrderService.php');

  assert.match(orderService, /function isProratedRenewalCandidate\(User \$user, Order \$order\): bool/);
  assert.match(orderService, /function applyRenewalSurplusCredit\(User \$user, Order \$order\): void/);
  assert.match(orderService, /function applyCappedSurplusCredit\(Order \$order\): void/);
  assert.match(orderService, /min\(\$cycleRatio,\s*\$trafficRatio\)/);
  assert.match(orderService, /\$cappedSurplus\s*=\s*\(int\)\s*min\(\$order->surplus_amount,\s*\$order->total_amount\)/);
  assert.match(orderService, /\$order->refund_amount\s*=\s*0/);
});

test('same-plan active renewals use prorated credit and do not depend on change-order settings', () => {
  const orderService = read('app/Services/OrderService.php');

  assert.match(
    orderService,
    /else if \(\$this->isProratedRenewalCandidate\(\$user,\s*\$order\)\) \{[\s\S]{0,260}\$order->type = Order::TYPE_RENEWAL[\s\S]{0,260}\$this->applyRenewalSurplusCredit\(\$user,\s*\$order\)[\s\S]{0,260}\$this->applyCappedSurplusCredit\(\$order\)/
  );
  assert.doesNotMatch(
    orderService,
    /applyRenewalSurplusCredit[\s\S]{0,900}admin_setting\('change_order_event_id'/
  );
  assert.doesNotMatch(
    orderService,
    /applyRenewalSurplusCredit[\s\S]{0,900}admin_setting\('surplus_enable'/
  );
});

test('prorated renewals restart the billing period, reset traffic once, and skip legacy renewal event', () => {
  const orderService = read('app/Services/OrderService.php');

  assert.match(orderService, /function shouldRestartPeriodOnRenewal\(Order \$order\): bool/);
  assert.match(
    orderService,
    /if \(\$this->shouldRestartPeriodOnRenewal\(\$order\)\) \{[\s\S]{0,160}\$this->user->expired_at = time\(\)/
  );
  assert.match(
    orderService,
    /if \(\$this->shouldRestartPeriodOnRenewal\(\$order\)[\s\S]{0,220}TrafficResetService::class\)->performReset\(\$this->user,\s*TrafficResetLog::SOURCE_ORDER\)/
  );
  assert.match(
    orderService,
    /if \(\$this->shouldSkipOpenEvent\(\$order\)\) \{[\s\S]{0,120}\$eventId = 0/
  );
});

test('admin locale copy marks renewal event as legacy compatibility behavior', () => {
  const zhCn = read('public/assets/admin/locales/zh-CN.js');
  const enUs = read('public/assets/admin/locales/en-US.js');

  assert.match(zhCn, /历史兼容/);
  assert.match(zhCn, /同套餐提前续费/);
  assert.match(enUs, /legacy compatibility/i);
  assert.match(enUs, /same-plan early renewals/i);
});

test('admin subscribe settings no longer render the renewal event selector', () => {
  const adminBundle = read('public/assets/admin/assets/index.js');

  assert.match(adminBundle, /renew_order_event_id/);
  assert.doesNotMatch(adminBundle, /name:"renew_order_event_id",render/);
  assert.match(adminBundle, /name:"new_order_event_id",render/);
  assert.match(adminBundle, /name:"change_order_event_id",render/);
});
