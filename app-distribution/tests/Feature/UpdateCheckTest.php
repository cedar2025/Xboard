<?php

namespace Tests\Feature;

use App\Models\AppArtifact;
use App\Models\AppVersion;
use App\Models\DistributionApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_version_is_returned_as_update(): void
    {
        $app = DistributionApp::create([
            'name' => 'ElephantRoute Desktop',
            'app_key' => 'elephant-route-desktop',
            'is_active' => true,
        ]);

        $version = AppVersion::create([
            'app_id' => $app->id,
            'platform' => 'macos',
            'channel' => 'stable',
            'version' => '1.0.1',
            'build_number' => 2,
            'min_supported_build' => 1,
            'status' => AppVersion::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        AppArtifact::create([
            'app_version_id' => $version->id,
            'disk' => 'artifacts',
            'path' => 'test.dmg',
            'original_name' => 'test.dmg',
            'extension' => 'dmg',
            'mime_type' => 'application/octet-stream',
            'file_size' => 123,
            'sha256' => str_repeat('a', 64),
        ]);

        $response = $this->getJson('/api/v1/update/check?app_key=elephant-route-desktop&platform=macos&channel=stable&version=1.0.0&build=1');

        $response->assertOk()
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.force', false)
            ->assertJsonPath('data.latest.version', '1.0.1');
    }
}
