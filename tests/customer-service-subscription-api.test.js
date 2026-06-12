const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('customer service subscription route is guest-only with dedicated API key middleware and throttling', () => {
  const route = readRepoFile('app/Http/Routes/V1/GuestRoute.php');

  assert.match(route, /customer-service\/subscription/);
  assert.match(route, /CustomerServiceController::class,\s*'subscription'/);
  assert.match(route, /->middleware\(\['customer_service',\s*'throttle:120,1'\]\)/);
  assert.doesNotMatch(route, /prefix'\s*=>\s*'user'[\s\S]*customer-service\/subscription/);
  assert.doesNotMatch(route, /prefix'\s*=>\s*admin_setting[\s\S]*customer-service\/subscription/);
});

test('customer service API key middleware uses configured key and constant-time comparison', () => {
  const middleware = readRepoFile('app/Http/Middleware/CustomerServiceApiKey.php');
  const kernel = readRepoFile('app/Http/Kernel.php');

  assert.match(kernel, /'customer_service'\s*=>\s*\\App\\Http\\Middleware\\CustomerServiceApiKey::class/);
  assert.match(middleware, /X-Customer-Service-Key/);
  assert.match(middleware, /admin_setting\('customer_service_api_key',\s*env\('CUSTOMER_SERVICE_API_KEY'\)\)/);
  assert.match(middleware, /hash_equals\(\(string\)\s*\$expectedKey,\s*\(string\)\s*\$providedKey\)/);
  assert.match(middleware, /return response\(\)->json\(\[\s*'message'\s*=>\s*'Customer service API key is not configured'/);
  assert.match(middleware, /return response\(\)->json\(\[\s*'message'\s*=>\s*'Invalid customer service API key'/);
});

test('customer service subscription response exposes only minimal plan and traffic fields', () => {
  const controller = readRepoFile('app/Http/Controllers/V1/Guest/CustomerServiceController.php');

  [
    "'email'",
    "'plan_id'",
    "'plan_name'",
    "'status'",
    "'expired_at'",
    "'upload_traffic'",
    "'download_traffic'",
    "'used_traffic'",
    "'transfer_enable'",
    "'remaining_traffic'",
    "'device_limit'",
    "'speed_limit'",
    "'next_reset_at'",
    "'reset_day'",
  ].forEach((field) => assert.match(controller, new RegExp(field)));

  assert.match(controller, /\$usedTraffic\s*=\s*\$uploadTraffic\s*\+\s*\$downloadTraffic/);
  assert.match(controller, /\$remainingTraffic\s*=\s*max\(0,\s*\$transferEnable\s*-\s*\$usedTraffic\)/);
  assert.match(controller, /where\('email',\s*\$request->input\('email'\)\)/);
  assert.match(controller, /'email'\s*=>\s*'required\|email'/);

  assert.doesNotMatch(controller, /'token'\s*=>/);
  assert.doesNotMatch(controller, /'uuid'\s*=>/);
  assert.doesNotMatch(controller, /'subscribe_url'\s*=>/);
  assert.doesNotMatch(controller, /'balance'\s*=>/);
  assert.doesNotMatch(controller, /'commission_balance'\s*=>/);
});

test('customer service API key can be saved in safe config with a strong minimum length', () => {
  const configSave = readRepoFile('app/Http/Requests/Admin/ConfigSave.php');
  const configController = readRepoFile('app/Http/Controllers/V2/Admin/ConfigController.php');
  const envExample = readRepoFile('.env.example');

  assert.match(configSave, /'customer_service_api_key'\s*=>\s*'nullable\|min:32'/);
  assert.match(configController, /'customer_service_api_key'\s*=>\s*admin_setting\('customer_service_api_key',\s*env\('CUSTOMER_SERVICE_API_KEY'\)\)/);
  assert.match(envExample, /CUSTOMER_SERVICE_API_KEY=/);
});

test('Dify support only exposes the user embed context endpoint', () => {
  const userRoute = readRepoFile('app/Http/Routes/V1/UserRoute.php');
  const guestRoute = readRepoFile('app/Http/Routes/V1/GuestRoute.php');

  assert.match(userRoute, /support\/dify-context/);
  assert.match(userRoute, /SupportController::class,\s*'difyContext'/);
  assert.doesNotMatch(guestRoute, /dify\/support-context/);
  assert.doesNotMatch(guestRoute, /DifySupportController/);

  assert.doesNotMatch(guestRoute, /prefix'\s*=>\s*'user'[\s\S]*dify\/support-context/);
  assert.doesNotMatch(userRoute, /customer_service/);
});

test('Dify user context endpoint returns embed configuration without signed lookup token', () => {
  const controller = readRepoFile('app/Http/Controllers/V1/User/SupportController.php');

  assert.match(controller, /LnMUVn4RJRDzxGFm/);
  assert.match(controller, /https:\/\/ai\.443ds443\.com/);
  assert.match(controller, /embed\.min\.js/);
  assert.match(controller, /'user_id'\s*=>\s*\(string\)\s*\$request->user\(\)->id/);
  assert.match(controller, /'user_display_name'/);

  assert.doesNotMatch(controller, /DifyContextTokenService/);
  assert.doesNotMatch(controller, /context_token/);
  assert.equal(fs.existsSync(path.join(repoRoot, 'app/Services/Support/DifyContextTokenService.php')), false);
});

test('Dify support tool endpoint and signed context token service are removed', () => {
  const guestRoute = readRepoFile('app/Http/Routes/V1/GuestRoute.php');

  assert.doesNotMatch(guestRoute, /dify\/support-context/);
  assert.doesNotMatch(guestRoute, /DifySupportController/);
  assert.equal(fs.existsSync(path.join(repoRoot, 'app/Http/Controllers/V1/Guest/DifySupportController.php')), false);
  assert.equal(fs.existsSync(path.join(repoRoot, 'app/Services/Support/DifyContextTokenService.php')), false);
  assert.equal(fs.existsSync(path.join(repoRoot, 'app/Services/Support/CustomerSubscriptionSnapshotService.php')), false);
});
