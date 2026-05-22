<?php

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\RegisterService;
use App\Utils\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RegisterServiceTest extends TestCase
{
    use RefreshDatabase;

    private RegisterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        admin_setting([
            'email_verify' => 1,
            'email_whitelist_enable' => 0,
            'email_gmail_limit_enable' => 0,
            'stop_register' => 0,
            'invite_force' => 0,
            'captcha_enable' => 0,
            'register_limit_by_ip_enable' => 0,
        ]);

        $this->service = app(RegisterService::class);
    }

    public function test_validate_register_rejects_missing_cached_email_code(): void
    {
        [$success, $result] = $this->service->validateRegister($this->makeRequest([
            'email_code' => '123456',
        ]));

        $this->assertFalse($success);
        $this->assertSame(400, $result[0]);
    }

    public function test_validate_register_rejects_boolean_email_code(): void
    {
        [$success, $result] = $this->service->validateRegister($this->makeRequest([
            'email_code' => false,
        ]));

        $this->assertFalse($success);
        $this->assertSame(422, $result[0]);
    }

    public function test_validate_register_accepts_matching_cached_email_code(): void
    {
        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', 'user@example.com'), 123456, 300);

        [$success, $result] = $this->service->validateRegister($this->makeRequest([
            'email_code' => '123456',
        ]));

        $this->assertTrue($success);
        $this->assertNull($result);
    }

    private function makeRequest(array $overrides = []): Request
    {
        return Request::create('/api/v1/passport/auth/register', 'POST', array_merge([
            'email' => 'user@example.com',
            'password' => 'password123',
        ], $overrides));
    }
}
