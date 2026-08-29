<?php

namespace Tests\Feature\Passport;

use App\Models\User;
use App\Services\Auth\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

// IP tracking for anti-abuse (#1013): register_ip persisted on signup,
// last_login_ip updated on every login.
class IpTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        admin_setting([
            'email_verify' => 0,
            'email_whitelist_enable' => 0,
            'email_gmail_limit_enable' => 0,
            'stop_register' => 0,
            'invite_force' => 0,
            'captcha_enable' => 0,
            'register_limit_by_ip_enable' => 0,
            'password_limit_enable' => 0,
        ]);
    }

    private function registerRequest(string $email, string $ip = '1.2.3.4'): Request
    {
        $request = Request::create('/api/v1/passport/auth/register', 'POST', [
            'email' => $email,
            'password' => 'password123',
        ]);
        $request->server->set('REMOTE_ADDR', $ip);
        $request->setUserResolver(fn () => null);
        return $request;
    }

    public function test_register_records_register_ip(): void
    {
        $service = app(\App\Services\Auth\RegisterService::class);

        [$ok, $user] = $service->register($this->registerRequest('ip-test@example.com', '9.9.9.9'));

        $this->assertTrue((bool) $ok);
        $this->assertSame('9.9.9.9', $user->refresh()->register_ip);
    }

    public function test_login_records_last_login_ip(): void
    {
        $user = User::create([
            'email' => 'login-ip@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'uuid' => Str::uuid()->toString(),
            'token' => Str::random(32),
        ]);

        [$ok, $found] = app(LoginService::class)->login('login-ip@example.com', 'password123', '5.6.7.8');

        $this->assertTrue((bool) $ok);
        $this->assertSame('5.6.7.8', $found->refresh()->last_login_ip);
    }
}
