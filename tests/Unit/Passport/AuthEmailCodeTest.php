<?php

namespace Tests\Unit\Passport;

use App\Http\Controllers\V1\Passport\AuthController;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class AuthEmailCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
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

    public function test_email_code_check_rejects_missing_cached_code(): void
    {
        $this->assertFalse($this->isValidEmailCode('victim@example.com', '123456'));
    }

    public function test_email_code_check_rejects_boolean_code(): void
    {
        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', 'victim@example.com'), 123456, 300);

        $this->assertFalse($this->isValidEmailCode('victim@example.com', false));
    }

    public function test_email_code_check_accepts_matching_cached_code(): void
    {
        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', 'user@example.com'), 123456, 300);

        $this->assertTrue($this->isValidEmailCode('user@example.com', '123456'));
    }

    private function isValidEmailCode(string $email, $emailCode): bool
    {
        $method = new ReflectionMethod(AuthController::class, 'isValidEmailCode');
        $method->setAccessible(true);

        return $method->invoke(new AuthController(), $email, $emailCode);
    }
}
