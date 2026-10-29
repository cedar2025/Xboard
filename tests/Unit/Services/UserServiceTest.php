<?php

namespace Tests\Unit\Services;

use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_user_uses_normalized_email_as_default_password(): void
    {
        $user = app(UserService::class)->createUser([
            'email' => 'GeneratedUser@Example.COM',
            'password' => null,
        ]);

        $this->assertSame('generateduser@example.com', $user->email);
        $this->assertTrue(Hash::check('generateduser@example.com', $user->password));
        $this->assertFalse(Hash::check('GeneratedUser@Example.COM', $user->password));
    }
}
