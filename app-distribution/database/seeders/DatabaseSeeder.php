<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\DistributionApp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'apps.manage' => '管理 App',
            'versions.manage' => '管理版本',
            'logs.view' => '查看日志',
            'audits.view' => '查看审计',
        ];

        foreach ($permissions as $slug => $name) {
            AdminPermission::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $owner = AdminRole::firstOrCreate(['slug' => 'owner'], ['name' => 'Owner']);
        $admin = AdminRole::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $viewer = AdminRole::firstOrCreate(['slug' => 'viewer'], ['name' => 'Viewer']);

        $owner->permissions()->sync(AdminPermission::pluck('id')->all());
        $admin->permissions()->sync(AdminPermission::whereIn('slug', ['apps.manage', 'versions.manage', 'logs.view'])->pluck('id')->all());
        $viewer->permissions()->sync(AdminPermission::whereIn('slug', ['logs.view'])->pluck('id')->all());

        Admin::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Owner',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe123!')),
                'role' => 'owner',
                'is_active' => true,
            ]
        );

        DistributionApp::firstOrCreate(
            ['app_key' => 'elephant-route-desktop'],
            [
                'name' => 'ElephantRoute Desktop',
                'description' => 'macOS and Windows desktop client',
                'is_active' => true,
            ]
        );
    }
}
