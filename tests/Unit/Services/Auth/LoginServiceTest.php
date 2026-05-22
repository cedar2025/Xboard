<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\LoginService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LoginServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoginService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->service = app(LoginService::class);
    }

    public function test_reset_password_rejects_missing_cached_email_code(): void
    {
        $user = $this->createUser('victim@example.com', 'old-password');

        [$success, $result] = $this->service->resetPassword($user->email, '', 'new-password');

        $this->assertFalse($success);
        $this->assertSame(400, $result[0]);

        $user->refresh();
        $this->assertTrue(password_verify('old-password', $user->password));
    }

    public function test_reset_password_accepts_matching_cached_email_code(): void
    {
        $user = $this->createUser('user@example.com', 'old-password');
        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', $user->email), 123456, 300);

        [$success, $result] = $this->service->resetPassword($user->email, '123456', 'new-password');

        $this->assertTrue($success);
        $this->assertTrue($result);

        $user->refresh();
        $this->assertTrue(password_verify('new-password', $user->password));
        $this->assertNull(Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $user->email)));
    }

    public function test_forget_password_validation_rejects_boolean_email_code(): void
    {
        $validator = Validator::make([
            'email' => 'victim@example.com',
            'password' => 'new-password',
            'email_code' => false,
        ], [
            'email' => 'required|email:strict',
            'password' => 'required|min:8',
            'email_code' => 'required|digits:6',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email_code', $validator->errors()->toArray());
    }

    private function createUser(string $email, string $password): User
    {
        return User::query()->create([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'uuid' => Helper::guid(true),
            'token' => Helper::guid(true),
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }
}