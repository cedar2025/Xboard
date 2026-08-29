<?php

namespace Tests\Feature\Passport;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassportRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        admin_setting([
            'stop_register' => 0,
            'email_verify' => 0,
            'captcha_enable' => 0,
            'register_limit_by_ip_enable' => 0,
            'password_login_enable' => 1,
        ]);
    }

    public function test_login_is_rate_limited_after_10_attempts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/passport/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/v1/passport/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_send_email_verify_is_rate_limited_after_3_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/passport/comm/sendEmailVerify', [
                'email' => 'nobody@example.com',
            ]);
        }

        $response = $this->postJson('/api/v1/passport/comm/sendEmailVerify', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(429);
    }
}
