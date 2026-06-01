<?php

namespace Tests\Unit\Utils;

use App\Utils\Helper;
use Tests\TestCase;

/**
 * Unit tests for Helper::multiPasswordVerify covering all 5 verification branches
 * and edge cases introduced by MySQL CHAR(10) space-padding of password_algo and password_salt.
 */
class HelperPasswordVerifyTest extends TestCase
{
    public function test_md5_verifies_correct_password(): void
    {
        $this->assertTrue(
            Helper::multiPasswordVerify('md5', null, 'testpw', md5('testpw'))
        );
    }

    public function test_sha256_verifies_correct_password(): void
    {
        $this->assertTrue(
            Helper::multiPasswordVerify('sha256', null, 'testpw', hash('sha256', 'testpw'))
        );
    }

    public function test_md5salt_verifies_correct_password_with_salt(): void
    {
        $salt = 'slt';
        $this->assertTrue(
            Helper::multiPasswordVerify('md5salt', $salt, 'testpw', md5('testpw' . $salt))
        );
    }

    public function test_sha256salt_verifies_correct_password_with_salt(): void
    {
        $salt = 'slt';
        $this->assertTrue(
            Helper::multiPasswordVerify('sha256salt', $salt, 'testpw', hash('sha256', 'testpw' . $salt))
        );
    }

    public function test_bcrypt_default_fallback_verifies_correct_password(): void
    {
        $hash = password_hash('testpw', PASSWORD_DEFAULT);
        $this->assertTrue(
            Helper::multiPasswordVerify(null, null, 'testpw', $hash)
        );
    }

    public function test_padded_algo_string_is_trimmed_before_switch(): void
    {
        $this->assertTrue(
            Helper::multiPasswordVerify('sha256    ', null, 'testpw', hash('sha256', 'testpw'))
        );
    }

    public function test_padded_salt_string_is_trimmed_before_hash(): void
    {
        $salt = 'slt';
        $this->assertTrue(
            Helper::multiPasswordVerify('sha256salt', 'slt       ', 'testpw', hash('sha256', 'testpw' . $salt))
        );
    }

    public function test_null_algo_falls_through_to_bcrypt(): void
    {
        $hash = password_hash('testpw', PASSWORD_DEFAULT);
        $this->assertTrue(
            Helper::multiPasswordVerify(null, null, 'testpw', $hash)
        );
    }

    public function test_empty_string_algo_falls_through_to_bcrypt(): void
    {
        $hash = password_hash('testpw', PASSWORD_DEFAULT);
        $this->assertTrue(
            Helper::multiPasswordVerify('', null, 'testpw', $hash)
        );
    }

    public function test_whitespace_only_algo_falls_through_to_bcrypt(): void
    {
        $hash = password_hash('testpw', PASSWORD_DEFAULT);
        $this->assertTrue(
            Helper::multiPasswordVerify('   ', null, 'testpw', $hash)
        );
    }

    public function test_wrong_password_returns_false(): void
    {
        $this->assertFalse(
            Helper::multiPasswordVerify('sha256', null, 'wrongpw', hash('sha256', 'correctpw'))
        );
    }

    public function test_unknown_algo_falls_through_to_bcrypt(): void
    {
        $hash = password_hash('testpw', PASSWORD_DEFAULT);
        $this->assertTrue(
            Helper::multiPasswordVerify('argon2', null, 'testpw', $hash)
        );
    }
}
