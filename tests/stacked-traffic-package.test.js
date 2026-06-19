const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '..');
const read = (relativePath) => fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');

test('traffic package schema, model, and service store independent package balances', () => {
  const migrationFiles = fs.readdirSync(path.join(repoRoot, 'database/migrations'));
  const migrationName = migrationFiles.find((file) => file.includes('create_user_traffic_packages_table'));
  assert.ok(migrationName, 'traffic package migration exists');

  const migration = read(`database/migrations/${migrationName}`);
  assert.match(migration, /Schema::create\('v2_user_traffic_packages'/);
  [
    "'user_id'",
    "'order_id'",
    "'plan_id'",
    "'total_bytes'",
    "'remaining_bytes'",
    "'status'",
    "'depleted_at'",
  ].forEach((field) => assert.match(migration, new RegExp(field)));
  assert.match(migration, /index\(\['user_id',\s*'status',\s*'remaining_bytes'\]/);

  const model = read('app/Models/UserTrafficPackage.php');
  assert.match(model, /protected \$table = 'v2_user_traffic_packages'/);
  assert.match(model, /const STATUS_ACTIVE = 'active'/);
  assert.match(model, /const STATUS_DEPLETED = 'depleted'/);

  const service = read('app/Services/TrafficPackageService.php');
  assert.match(service, /function createFromOrder\(Order \$order, User \$user, Plan \$plan\)/);
  assert.match(service, /\$totalBytes\s*=\s*\(int\)\s*\(\$plan->transfer_enable \* self::BYTES_PER_GB\)/);
  assert.match(service, /'total_bytes'\s*=>\s*\$totalBytes/);
  assert.match(service, /function consume\(int \$userId, int \$uploadBytes, int \$downloadBytes\): array/);
  assert.match(service, /orderBy\('id'\)/);
  assert.match(service, /'package_upload'/);
  assert.match(service, /'plan_upload'/);
});

test('onetime traffic package purchase does not replace current subscription', () => {
  const planService = read('app/Services/PlanService.php');
  assert.match(planService, /validateOnetimeTrafficPackagePurchase\(User \$user\)/);
  assert.match(planService, /expired_at\s*===\s*NULL/);
  assert.match(planService, /expired_at\s*<=\s*time\(\)/);

  const orderService = read('app/Services/OrderService.php');
  assert.match(orderService, /Plan::PERIOD_ONETIME => \$this->buyTrafficPackage\(\$order, \$plan\)/);
  assert.doesNotMatch(orderService, /Plan::PERIOD_ONETIME => \$this->buyByOneTime\(\$plan\)/);
  assert.match(orderService, /private function buyTrafficPackage\(Order \$order, Plan \$plan\)/);
  assert.match(orderService, /TrafficPackageService::class\)->createFromOrder\(\$order, \$this->user, \$plan\)/);
  assert.doesNotMatch(orderService, /private function buyByOneTime/);
});

test('traffic package plans are only visible to users with active time subscriptions', () => {
  const planService = read('app/Services/PlanService.php');
  assert.match(planService, /function isTrafficPackagePlan\(Plan \$plan\): bool/);
  assert.match(planService, /Plan::PERIOD_ONETIME/);
  assert.match(planService, /Plan::PERIOD_MONTHLY/);
  assert.match(planService, /function hasActiveTimeSubscription\(User \$user\): bool/);
  assert.match(planService, /\$user->expired_at !== NULL/);
  assert.match(planService, /\$user->expired_at > time\(\)/);
  assert.doesNotMatch(planService, /hasActiveTimeSubscription\(User \$user\): bool[\s\S]*hasActivePackageBalance/);
  assert.match(planService, /function getAvailablePlansForUser\(User \$user\): Collection/);
  assert.match(planService, /!\$this->isTrafficPackagePlan\(\$plan\) \|\| \$this->hasActiveTimeSubscription\(\$user\)/);

  const userPlanController = read('app/Http/Controllers/V1/User/PlanController.php');
  assert.match(userPlanController, /getAvailablePlansForUser\(\$user\)/);
  assert.match(userPlanController, /isPlanAvailableForUser\(\$plan, \$user\)/);

  const guestPlanController = read('app/Http/Controllers/V1/Guest/PlanController.php');
  assert.match(guestPlanController, /getAvailablePlans\(\)/);

  const orderController = read('app/Http/Controllers/V1/User/OrderController.php');
  assert.match(orderController, /validatePurchase\(\$user, \$request->input\('period'\)\)/);
});

test('traffic fetch consumes package balance before charging subscription traffic', () => {
  const job = read('app/Jobs/TrafficFetchJob.php');
  assert.match(job, /TrafficPackageService/);
  assert.match(job, /consume\(\(int\) \$uid, \$uploadBytes, \$downloadBytes\)/);
  assert.match(job, /'u'\s*=>\s*\$consumption\['plan_upload'\]/);
  assert.match(job, /'d'\s*=>\s*\$consumption\['plan_download'\]/);
  assert.doesNotMatch(job, /'u'\s*=>\s*\$v\[0\] \* \$this->server\['rate'\]/);
});

test('availability and subscription display account for traffic packages separately', () => {
  const userService = read('app/Services/UserService.php');
  assert.match(userService, /TrafficPackageService/);
  assert.match(userService, /hasActivePackageBalance\(\$user->id\)/);
  assert.match(userService, /orWhereExists\(function \(\$query\)/);
  assert.match(userService, /whereColumn\('v2_user_traffic_packages.user_id', 'v2_user.id'\)/);
  assert.match(userService, /getTrafficSummary\(User \$user\): array/);
  assert.match(userService, /getTrafficSummary\(\$user\)/);

  const trafficPackageService = read('app/Services/TrafficPackageService.php');
  [
    "'plan_transfer_enable'",
    "'plan_used_traffic'",
    "'plan_remaining_traffic'",
    "'traffic_package_total'",
    "'traffic_package_remaining'",
    "'effective_transfer_enable'",
    "'effective_remaining_traffic'",
  ].forEach((field) => assert.match(trafficPackageService, new RegExp(field)));

  const serverService = read('app/Services/ServerService.php');
  assert.match(serverService, /leftJoin\('v2_user_traffic_packages as traffic_packages'/);
  assert.match(serverService, /COALESCE\(SUM\(traffic_packages.remaining_bytes\), 0\)/);
  assert.match(serverService, /orHavingRaw\('COALESCE\(SUM\(traffic_packages.remaining_bytes\), 0\) > 0'\)/);

  const customerService = read('app/Http/Controllers/V1/Guest/CustomerServiceController.php');
  assert.match(customerService, /getTrafficSummary\(\$user\)/);
  assert.match(customerService, /'traffic_package_remaining'/);

  const userController = read('app/Http/Controllers/V1/User/UserController.php');
  assert.match(userController, /public function getSubscribe\(Request \$request\)[\s\S]*->select\(\[\s*'id'/);
  assert.match(userController, /public function getSubscribe\(Request \$request\)[\s\S]*getTrafficSummary\(\$user\)/);

  const protocolFiles = [
    'app/Protocols/Clash.php',
    'app/Protocols/ClashMeta.php',
    'app/Protocols/SingBox.php',
    'app/Protocols/Stash.php',
    'app/Protocols/QuantumultX.php',
    'app/Protocols/Loon.php',
  ];
  protocolFiles.forEach((relativePath) => {
    const source = read(relativePath);
    assert.match(source, /effective_transfer_enable/);
  });
});
