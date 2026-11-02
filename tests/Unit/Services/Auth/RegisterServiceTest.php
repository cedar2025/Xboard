<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\RegisterService;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
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

    public function test_validate_register_uses_canonical_email_for_verification_code(): void
    {
        $email = "Jose\u{0301}@EXAMPLE.COM";
        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', User::normalizeEmail($email)), 123456, 300);

        [$success, $result] = $this->service->validateRegister($this->makeRequest([
            'email' => $email,
            'email_code' => '123456',
        ]));

        $this->assertTrue($success);
        $this->assertNull($result);
    }

    public function test_register_handles_a_concurrent_duplicate_email_insert(): void
    {
        $email = 'race@example.com';
        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', $email), 123456, 300);

        $this->mock(UserService::class)
            ->shouldReceive('createUser')
            ->once()
            ->andReturnUsing(function (array $data) use ($email) {
                User::query()->create([
                    'email' => $email,
                    'password' => password_hash('password123', PASSWORD_DEFAULT),
                    'uuid' => Helper::guid(true),
                    'token' => Helper::guid(true),
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);

                $user = new User();
                $user->email = $data['email'];
                $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
                $user->uuid = Helper::guid(true);
                $user->token = Helper::guid(true);
                $user->created_at = time();
                $user->updated_at = time();

                return $user;
            });

        [$success, $result] = $this->service->register($this->makeRequest([
            'email' => $email,
            'email_code' => '123456',
        ]));

        $this->assertFalse($success);
        $this->assertSame(400201, $result[0]);
        $this->assertSame(1, User::byEmail($email)->count());
    }

    private function makeRequest(array $overrides = []): Request
    {
        return Request::create('/api/v1/passport/auth/register', 'POST', array_merge([
            'email' => 'user@example.com',
            'password' => 'password123',
        ], $overrides));
    }
}
