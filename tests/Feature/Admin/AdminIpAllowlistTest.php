<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIpAllowlistTest extends TestCase
{
    use RefreshDatabase;

    private string $adminPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminPath = '/api/v2/' . hash('crc32b', config('app.key'));
    }

    public function test_blocks_admin_requests_from_non_allowlisted_ip(): void
    {
        config()->set('admin.ip_allowlist', '10.99.99.99');

        $response = $this->getJson($this->adminPath . '/config/fetch');

        $response->assertStatus(403);
    }

    public function test_allows_admin_requests_when_allowlist_empty(): void
    {
        config()->set('admin.ip_allowlist', '');

        // Empty allowlist must not block; the 403 here comes from admin auth,
        // not from the IP allowlist middleware.
        $response = $this->getJson($this->adminPath . '/config/fetch');

        $response->assertStatus(403)->assertJson(['message' => 'Unauthorized']);
    }
}
