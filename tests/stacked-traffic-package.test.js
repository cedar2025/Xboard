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
  const packageCatalogMigrationName = migrationFiles.find((file) => file.includes('create_traffic_packages_table'));
  assert.ok(packageCatalogMigrationName, 'independent traffic package catalog migration exists');
  const orderLinkMigrationName = migrationFiles.find((file) => file.includes('add_traffic_package_order_links'));
  assert.ok(orderLinkMigrationName, 'order and user package link migration exists');

  const packageCatalogMigration = read(`database/migrations/${packageCatalogMigrationName}`);
  assert.match(packageCatalogMigration, /Schema::create\('v2_traffic_packages'/);
  [
    "'name'",
    "'transfer_enable'",
    "'price'",
    "'group_id'",
    "'speed_limit'",
    "'device_limit'",
    "'show'",
    "'sell'",
    "'sort'",
    "'content'",
  ].forEach((field) => assert.match(packageCatalogMigration, new RegExp(field)));

  const orderLinkMigration = read(`database/migrations/${orderLinkMigrationName}`);
  assert.match(orderLinkMigration, /Schema::table\('v2_order'/);
  assert.match(orderLinkMigration, /'traffic_package_id'/);
  assert.match(orderLinkMigration, /nullable\(\)->change\(\)/);
  assert.match(orderLinkMigration, /Schema::table\('v2_user_traffic_packages'/);

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
  assert.match(model, /trafficPackage\(\): BelongsTo/);

  const packageModel = read('app/Models/TrafficPackage.php');
  assert.match(packageModel, /protected \$table = 'v2_traffic_packages'/);
  assert.match(packageModel, /'transfer_enable'/);
  assert.match(packageModel, /'price'/);
  assert.match(packageModel, /orders\(\): HasMany/);

  const service = read('app/Services/TrafficPackageService.php');
  assert.match(service, /function createFromOrder\(Order \$order, User \$user, TrafficPackage \$trafficPackage\)/);
  assert.match(service, /\$totalBytes\s*=\s*\(int\)\s*\(\$trafficPackage->transfer_enable \* self::BYTES_PER_GB\)/);
  assert.match(service, /'total_bytes'\s*=>\s*\$totalBytes/);
  assert.match(service, /'traffic_package_id'\s*=>\s*\$trafficPackage->id/);
  assert.match(service, /function applyAccessForStandalonePackage\(User \$user, TrafficPackage \$trafficPackage\): void/);
  assert.match(service, /\$user->plan_id\s*===\s*null/);
  assert.doesNotMatch(service, /applyAccessForStandalonePackage[\s\S]*\$user->plan_id\s*=\s*\$trafficPackage->id/);
  assert.doesNotMatch(service, /applyAccessForStandalonePackage[\s\S]*\$user->transfer_enable\s*=/);
  assert.match(service, /function consume\(int \$userId, int \$uploadBytes, int \$downloadBytes\): array/);
  assert.match(service, /orderBy\('id'\)/);
  assert.match(service, /'package_upload'/);
  assert.match(service, /'plan_upload'/);
});

test('independent traffic package orders do not replace current subscription', () => {
  const orderService = read('app/Services/OrderService.php');
  assert.match(orderService, /function createTrafficPackageFromRequest\(/);
  assert.match(orderService, /TrafficPackage \$trafficPackage/);
  assert.match(orderService, /'traffic_package_id'\s*=>\s*\$trafficPackage->id/);
  assert.match(orderService, /'plan_id'\s*=>\s*null/);
  assert.match(orderService, /'period'\s*=>\s*Order::PERIOD_TRAFFIC_PACKAGE/);
  assert.match(orderService, /Order::TYPE_TRAFFIC_PACKAGE/);
  assert.match(orderService, /private function buyTrafficPackage\(Order \$order, TrafficPackage \$trafficPackage\)/);
  assert.match(orderService, /TrafficPackageService::class\)->createFromOrder\(\$order, \$this->user, \$trafficPackage\)/);
  assert.doesNotMatch(orderService, /createTrafficPackageFromRequest[\s\S]{0,1200}getSurplusValue/);
  assert.doesNotMatch(orderService, /createTrafficPackageFromRequest[\s\S]{0,1200}setSpeedLimit\(\$plan->speed_limit\)/);
  assert.match(orderService, /\(int\) \$order->type !== Order::TYPE_TRAFFIC_PACKAGE/);
  assert.doesNotMatch(orderService, /Plan::PERIOD_ONETIME => \$this->buyByOneTime\(\$plan\)/);
  assert.doesNotMatch(orderService, /private function buyByOneTime/);
});

test('traffic packages are fetched and bought independently from subscription plans', () => {
  const planService = read('app/Services/PlanService.php');
  assert.match(planService, /function isTrafficPackagePlan\(Plan \$plan\): bool/);
  assert.match(planService, /Plan::PERIOD_ONETIME/);
  assert.match(planService, /Plan::PERIOD_MONTHLY/);
  assert.match(planService, /function getAvailablePlansForUser\(User \$user\): Collection/);
  assert.match(planService, /!\$this->isTrafficPackagePlan\(\$plan\)/);
  assert.doesNotMatch(planService, /getAvailablePlansForUser\(User \$user\): Collection[\s\S]*hasActiveTimeSubscription/);

  const userPlanController = read('app/Http/Controllers/V1/User/PlanController.php');
  assert.match(userPlanController, /getAvailablePlansForUser\(\$user\)/);
  assert.match(userPlanController, /isPlanAvailableForUser\(\$plan, \$user\)/);

  const guestPlanController = read('app/Http/Controllers/V1/Guest/PlanController.php');
  assert.match(guestPlanController, /getAvailablePlans\(\)/);

  const trafficPackageController = read('app/Http/Controllers/V1/User/TrafficPackageController.php');
  assert.match(trafficPackageController, /TrafficPackageResource::collection/);
  assert.match(trafficPackageController, /getAvailablePackages\(\)/);

  const userRoutes = read('app/Http/Routes/V1/UserRoute.php');
  assert.match(userRoutes, /traffic-package\/fetch/);

  const orderController = read('app/Http/Controllers/V1/User/OrderController.php');
  assert.match(orderController, /traffic_package_id/);
  assert.match(orderController, /createTrafficPackageFromRequest/);
  assert.match(orderController, /validatePurchase\(\$user, \$request->input\('period'\)\)/);
});

test('traffic fetch consumes active subscription traffic before package balance', () => {
  const job = read('app/Jobs/TrafficFetchJob.php');
  const trafficPackageService = read('app/Services/TrafficPackageService.php');

  assert.match(job, /TrafficPackageService/);
  assert.match(job, /consume\(\(int\) \$uid, \$uploadBytes, \$downloadBytes\)/);
  assert.match(job, /'u'\s*=>\s*\$consumption\['plan_upload'\]/);
  assert.match(job, /'d'\s*=>\s*\$consumption\['plan_download'\]/);
  assert.doesNotMatch(job, /'u'\s*=>\s*\$v\[0\] \* \$this->server\['rate'\]/);

  assert.match(trafficPackageService, /User::where\('id', \$userId\)[\s\S]*lockForUpdate\(\)[\s\S]*first\(\)/);
  assert.match(trafficPackageService, /\$planAvailable\s*=\s*\$this->getActivePlanRemainingBytes\(\$user\)/);
  assert.match(trafficPackageService, /\$planUpload\s*=\s*min\(\$planAvailable, \$remainingUpload\)/);
  assert.match(trafficPackageService, /\$planDownload\s*=\s*min\(\$planAvailable, \$remainingDownload\)/);
  assert.match(trafficPackageService, /function getActivePlanRemainingBytes\(User \$user\): int/);
  assert.match(trafficPackageService, /\(int\) \$user->expired_at <= time\(\)/);
  assert.match(trafficPackageService, /\$planUsedTraffic\s*=\s*\(int\) \(\$user->u \+ \$user->d\)/);
  assert.match(trafficPackageService, /\$packages = UserTrafficPackage::where\('user_id', \$userId\)/);
  assert.match(trafficPackageService, /'package_upload'\s*=>\s*\$packageUpload/);
  assert.match(trafficPackageService, /'plan_upload'\s*=>\s*\$planUpload/);
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
