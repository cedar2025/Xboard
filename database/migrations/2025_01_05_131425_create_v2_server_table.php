<?php

use App\Utils\Helper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('v2_server', function (Blueprint $table) {
            $table->id('id');
            $table->string('type')->comment('Server Type');
            $table->string('code')->nullable()->comment('Server Spectific Key');
            $table->unsignedInteger('parent_id')->nullable()->comment('Parent Server ID');
            $table->json('group_ids')->nullable()->comment('Group ID');
            $table->json('route_ids')->nullable()->comment('Route ID');
            $table->string('name')->comment('Server Name');
            $table->decimal('rate', 8, 2)->comment('Traffic Rate');
            $table->json('tags')->nullable()->comment('Server Tags');
            $table->string('host')->comment('Server Host');
            $table->string('port')->comment('Client Port');
            $table->integer('server_port')->comment('Server Port');
            $table->json('protocol_settings')->nullable();
            $table->boolean('show')->default(false)->comment('Show in List');
            $table->integer('sort')->nullable()->unsigned()->index();
            $table->timestamps();
            $table->unique(['type', 'code']);
        });

        // Migrate Trojan servers
        $trojanServers = DB::table('v2_server_trojan')->get();
        foreach ($trojanServers as $server) {
            DB::table('v2_server')->insert([
                'type' => 'trojan',
                'code' => (string) $server->id,
                'parent_id' => $server->parent_id,
                'group_ids' => $server->group_id ?: "[]",
                'route_ids' => $server->route_id ?: "[]",
                'name' => $server->name,
                'rate' => $server->rate,
                'tags' => $server->tags ?: "[]",
                'host' => $server->host,
                'port' => $server->port,
                'server_port' => $server->server_port,
                'protocol_settings' => json_encode([
                    'allow_insecure' => $server->allow_insecure,
                    'server_name' => $server->server_name,
                    'network' => $server->network,
                    'network_settings' => $server->networkSettings
                ]),
                'show' => $server->show,
                'sort' => $server->sort,
                'created_at' => date('Y-m-d H:i:s', $server->created_at),
                'updated_at' => date('Y-m-d H:i:s', $server->updated_at)
            ]);
        }

        // Migrate VMess servers
        $vmessServers = DB::table('v2_server_vmess')->get();
        foreach ($vmessServers as $server) {
            DB::table('v2_server')->insert([
                'type' => 'vmess',
                'code' => (string) $server->id,
                'parent_id' => $server->parent_id,
                'group_ids' => $server->group_id ?: "[]",
                'route_ids' => $server->route_id ?: "[]",
                'name' => $server->name,
                'rate' => $server->rate,
                'tags' => $server->tags ?: "[]",
                'host' => $server->host,
                'port' => $server->port,
                'server_port' => $server->server_port,
                'protocol_settings' => json_encode([
                    'tls' => $server->tls,
                    'network' => $server->network,
                    'rules' => json_decode($server->rules),
                    'network_settings' => json_decode($server->networkSettings),
                    'tls_settings' => json_decode($server->tlsSettings),
                ]),
                'show' => $server->show,
                'sort' => $server->sort,
                'created_at' => date('Y-m-d H:i:s', $server->created_at),
                'updated_at' => date('Y-m-d H:i:s', $server->updated_at)
            ]);
        }

        // Migrate VLESS servers
        $vlessServers = DB::table('v2_server_vless')->get();
        foreach ($vlessServers as $server) {
            $tlsSettings = optional(json_decode($server->tls_settings));
            DB::table('v2_server')->insert([
                'type' => 'vless',
                'code' => (string) $server->id,
                'parent_id' => $server->parent_id,
                'group_ids' => $server->group_id ?: "[]",
                'route_ids' => $server->route_id ?: "[]",
                'name' => $server->name,
                'rate' => $server->rate,
                'tags' => $server->tags ?: "[]",
                'host' => $server->host,
                'port' => $server->port,
                'server_port' => $server->server_port,
                'protocol_settings' => json_encode([
                    'tls' => $server->tls,
                    'tls_settings' => $tlsSettings,
                    'flow' => $server->flow,
                    'network' => $server->network,
                    'network_settings' => json_decode($server->network_settings),
                    'reality_settings' => ($tlsSettings && $tlsSettings->public_key && $tlsSettings->short_id && $tlsSettings->server_name) ? [
                        'public_key' => $tlsSettings->public_key,
                        'short_id' => $tlsSettings->short_id,
                        'server_name' => $tlsSettings->server_name,
                        'server_port' => $tlsSettings->server_port,
                        'private_key' => $tlsSettings->private_key,
                    ] : null
                ]),
                'show' => $server->show,
                'sort' => $server->sort,
                'created_at' => date('Y-m-d H:i:s', $server->created_at),
                'updated_at' => date('Y-m-d H:i:s', $server->updated_at)
            ]);
        }

        // Migrate Shadowsocks servers
        $ssServers = DB::table('v2_server_shadowsocks')->get();
        foreach ($ssServers as $server) {
            DB::table('v2_server')->insert([
                'type' => 'shadowsocks',
                'code' => (string) $server->id,
                'parent_id' => $server->parent_id,
                'group_ids' => $server->group_id ?: "[]",
                'route_ids' => $server->route_id ?: "[]",
                'name' => $server->name,
                'rate' => $server->rate,
                'tags' => $server->tags ?: "[]",
                'host' => $server->host,
                'port' => $server->port,
                'server_port' => $server->server_port,
                'protocol_settings' => json_encode([
                    'cipher' => $server->cipher,
                    'obfs' => $server->obfs,
                    'obfs_settings' => json_decode($server->obfs_settings)
                ]),
                'show' => (bool) $server->show,
                'sort' => $server->sort,
                'created_at' => date('Y-m-d H:i:s', $server->created_at),
                'updated_at' => date('Y-m-d H:i:s', $server->updated_at)
            ]);
        }

        // Migrate Hysteria servers
        $hysteriaServers = DB::table(table: 'v2_server_hysteria')->get();
        foreach ($hysteriaServers as $server) {
            DB::table('v2_server')->insert([
                'type' => 'hysteria',
                'code' => (string) $server->id,
                'parent_id' => $server->parent_id,
                'group_ids' => $server->group_id ?: "[]",
                'route_ids' => $server->route_id ?: "[]",
                'name' => $server->name,
                'rate' => $server->rate,
                'tags' => $server->tags ?: "[]",
                'host' => $server->host,
                'port' => $server->port,
                'server_port' => $server->server_port,
                'protocol_settings' => json_encode([
                    'version' => $server->version,
                    'bandwidth' => [
                        'up' => $server->up_mbps,
                        'down' => $server->down_mbps,
                    ],
                    'obfs' => [
                        'open' => $server->is_obfs,
                        'type' => 'salamander',
                        'password' => Helper::getServerKey($server->created_at, 16),
                    ],
                    'tls' => [
                        'server_name' => $server->server_name,
                        'allow_insecure' => $server->insecure
                    ]
                ]),
                'show' => $server->show,
                'sort' => $server->sort,
                'created_at' => date('Y-m-d H:i:s', $server->created_at),
                'updated_at' => date('Y-m-d H:i:s', $server->updated_at)
            ]);
        }

        // Update parent_id for all servers
        $this->updateParentIds();

        // Drop old tables
        Schema::dropIfExists('v2_server_trojan');
        Schema::dropIfExists('v2_server_vmess');
        Schema::dropIfExists('v2_server_vless');
        Schema::dropIfExists('v2_server_shadowsocks');
        Schema::dropIfExists('v2_server_hysteria');
    }

    /**
     * Update parent_id references for all servers
     */
    private function updateParentIds(): void
    {
        // Get all servers that have a parent_id
        $servers = DB::table('v2_server')
            ->whereNotNull('parent_id')
            ->get();

        // Update each server's parent_id to reference the new table's id
        foreach ($servers as $server) {
            $parentId = DB::table('v2_server')
                ->where('type', $server->type)
                ->where('code', $server->parent_id)
                ->value('id');

            if ($parentId) {
                DB::table('v2_server')
                    ->where('id', $server->id)
                    ->update(['parent_id' => $parentId]);
            }
        }
    }

    /**
     * Restore parent_id references when rolling back
     */
    private function restoreParentIds(string $type, string $table): void
    {
        // Get all servers of the specified type that have a parent_id
        $servers = DB::table($table)
            ->whereNotNull('parent_id')
            ->get();

        // Update each server's parent_id to reference back to the original id
        foreach ($servers as $server) {
            $originalParentId = DB::table('v2_server')
                ->where('type', $type)
                ->where('id', $server->parent_id)
                ->value('code');

            if ($originalParentId) {
                DB::table($table)
                    ->where('id', $server->id)
                    ->update(['parent_id' => $originalParentId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate old tables
        Schema::create('v2_server_trojan', function (Blueprint $table) {
            $table->integer('id', true)->comment('节点ID');
            $table->string('group_id')->comment('节点组');
            $table->string('route_id')->nullable();
            $table->string('ips')->nullable();
            $table->string('excludes')->nullable();
            $table->integer('parent_id')->nullable()->comment('父节点');
            $table->string('tags')->nullable()->comment('节点标签');
            $table->string('name')->comment('节点名称');
            $table->string('rate', 11)->comment('倍率');
            $table->string('host')->comment('主机名');
            $table->string('port', 11)->comment('连接端口');
            $table->integer('server_port')->comment('服务端口');
            $table->boolean('allow_insecure')->default(false)->comment('是否允许不安全');
            $table->string('server_name')->nullable();
            $table->string('network')->nullable();
            $table->text('networkSettings')->nullable();
            $table->boolean('show')->default(false)->comment('是否显示');
            $table->integer('sort')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_server_vmess', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('group_id');
            $table->string('route_id')->nullable();
            $table->string('ips')->nullable();
            $table->string('excludes')->nullable();
            $table->string('name');
            $table->integer('parent_id')->nullable();
            $table->string('host');
            $table->string('port', 11);
            $table->integer('server_port');
            $table->tinyInteger('tls')->default(0);
            $table->string('tags')->nullable();
            $table->string('rate', 11);
            $table->string('network', 11);
            $table->text('rules')->nullable();
            $table->text('networkSettings')->nullable();
            $table->text('tlsSettings')->nullable();
            $table->boolean('show')->default(false);
            $table->integer('sort')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_server_vless', function (Blueprint $table) {
            $table->integer('id', true);
            $table->text('group_id');
            $table->text('route_id')->nullable();
            $table->string('ips')->nullable();
            $table->string('excludes')->nullable();
            $table->string('name');
            $table->integer('parent_id')->nullable();
            $table->string('host');
            $table->integer('port');
            $table->integer('server_port');
            $table->boolean('tls');
            $table->text('tls_settings')->nullable();
            $table->string('flow', 64)->nullable();
            $table->string('network', 11);
            $table->text('network_settings')->nullable();
            $table->text('tags')->nullable();
            $table->string('rate', 11);
            $table->boolean('show')->default(false);
            $table->integer('sort')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_server_shadowsocks', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('group_id');
            $table->string('route_id')->nullable();
            $table->string('ips')->nullable();
            $table->string('excludes')->nullable();
            $table->integer('parent_id')->nullable();
            $table->string('tags')->nullable();
            $table->string('name');
            $table->string('rate', 11);
            $table->string('host');
            $table->string('port', 11);
            $table->integer('server_port');
            $table->string('cipher');
            $table->char('obfs', 11)->nullable();
            $table->string('obfs_settings')->nullable();
            $table->tinyInteger('show')->default(0);
            $table->integer('sort')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_server_hysteria', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('group_id');
            $table->string('route_id')->nullable();
            $table->string('ips')->nullable();
            $table->string('excludes')->nullable();
            $table->string('name');
            $table->integer('parent_id')->nullable();
            $table->string('host');
            $table->string('port', 11);
            $table->integer('server_port');
            $table->string('tags')->nullable();
            $table->string('rate', 11);
            $table->boolean('show')->default(false);
            $table->integer('sort')->nullable();
            $table->tinyInteger('version', false, true)->default(1)->comment('hysteria版本,Version:1\2');
            $table->boolean('is_obfs')->default(true)->comment('是否开启obfs');
            $table->string('alpn')->nullable();
            $table->integer('up_mbps');
            $table->integer('down_mbps');
            $table->string('server_name', 64)->nullable();
            $table->boolean('insecure')->default(false);
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        // Migrate data back to old tables
        $servers = DB::table('v2_server')->get();
        foreach ($servers as $server) {
            $settings = json_decode($server->protocol_settings, true);
            $timestamp = strtotime($server->created_at);
            $updated = strtotime($server->updated_at);
            switch ($server->type) {
                case 'trojan':
                    DB::table('v2_server_trojan')->insert([
                        'id' => (int) $server->code,
                        'group_id' => $server->group_ids,
                        'route_id' => $server->route_ids,
                        'parent_id' => $server->parent_id,
                        'tags' => $server->tags,
                        'name' => $server->name,
                        'rate' => (string) $server->rate,
                        'host' => $server->host,
                        'port' => $server->port,
                        'server_port' => $server->server_port,
                        'allow_insecure' => $settings['allow_insecure'],
                        'server_name' => $settings['server_name'],
                        'network' => $settings['network'] ?? null,
                        'networkSettings' => $settings['network_settings'] ?? null,
                        'show' => $server->show,
                        'sort' => $server->sort,
                        'created_at' => $timestamp,
                        'updated_at' => $updated
                    ]);
                    break;
                case 'vmess':
                    DB::table('v2_server_vmess')->insert([
                        'id' => (int) $server->code,
                        'group_id' => $server->group_ids,
                        'route_id' => $server->route_ids,
                        'name' => $server->name,
                        'parent_id' => $server->parent_id,
                        'host' => $server->host,
                        'port' => $server->port,
                        'server_port' => $server->server_port,
                        'tls' => $settings['tls'],
                        'tags' => $server->tags,
                        'rate' => (string) $server->rate,
                        'network' => $settings['network'],
                        'rules' => json_encode($settings['rules']),
                        'networkSettings' => json_encode($settings['network_settings']),
                        'tlsSettings' => json_encode($settings['tls_settings']),
                        'show' => $server->show,
                        'sort' => $server->sort,
                        'created_at' => $timestamp,
                        'updated_at' => $updated
                    ]);
                    break;
                case 'vless':
                    // 处理 reality settings
                    $tlsSettings = $settings['tls_settings'] ?? new \stdClass();
                    if (isset($settings['reality_settings'])) {
                        $tlsSettings = array_merge((array) $tlsSettings, [
                            'public_key' => $settings['reality_settings']['public_key'],
                            'short_id' => $settings['reality_settings']['short_id'],
                            'server_name' => explode(':', $settings['reality_settings']['dest'])[0],
                            'server_port' => explode(':', $settings['reality_settings']['dest'])[1] ?? null,
                            'private_key' => $settings['reality_settings']['private_key']
                        ]);
                    }

                    DB::table('v2_server_vless')->insert([
                        'id' => (int) $server->code,
                        'group_id' => $server->group_ids,
                        'route_id' => $server->route_ids,
                        'name' => $server->name,
                        'parent_id' => $server->parent_id,
                        'host' => $server->host,
                        'port' => $server->port,
                        'server_port' => $server->server_port,
                        'tls' => $settings['tls'],
                        'tls_settings' => json_encode($tlsSettings),
                        'flow' => $settings['flow'],
                        'network' => $settings['network'],
                        'network_settings' => json_encode($settings['network_settings']),
                        'tags' => $server->tags,
                        'rate' => (string) $server->rate,
                        'show' => $server->show,
                        'sort' => $server->sort,
                        'created_at' => $timestamp,
                        'updated_at' => $updated
                    ]);
                    break;
                case 'shadowsocks':
                    DB::table('v2_server_shadowsocks')->insert([
                        'id' => (int) $server->code,
                        'group_id' => $server->group_ids,
                        'route_id' => $server->route_ids,
                        'parent_id' => $server->parent_id,
                        'tags' => $server->tags,
                        'name' => $server->name,
                        'rate' => (string) $server->rate,
                        'host' => $server->host,
                        'port' => $server->port,
                        'server_port' => $server->server_port,
                        'cipher' => $settings['cipher'],
                        'obfs' => $settings['obfs'],
                        'obfs_settings' => json_encode($settings['obfs_settings']),
                        'show' => (int) $server->show,
                        'sort' => $server->sort,
                        'created_at' => $timestamp,
                        'updated_at' => $updated
                    ]);
                    break;
                case 'hysteria':
                    DB::table('v2_server_hysteria')->insert([
                        'id' => (int) $server->code,
                        'group_id' => $server->group_ids,
                        'route_id' => $server->route_ids,
                        'name' => $server->name,
                        'parent_id' => $server->parent_id,
                        'host' => $server->host,
                        'port' => $server->port,
                        'server_port' => $server->server_port,
                        'tags' => $server->tags,
                        'rate' => (string) $server->rate,
                        'show' => $server->show,
                        'sort' => $server->sort,
                        'up_mbps' => $settings['bandwidth']['up'],
                        'down_mbps' => $settings['bandwidth']['down'],
                        'server_name' => $settings['tls']['server_name'],
                        'insecure' => $settings['tls']['allow_insecure'],
                        'created_at' => $timestamp,
                        'updated_at' => $updated
                    ]);
                    break;
            }
        }

        // Restore parent_id references for each server type
        $this->restoreParentIds('trojan', 'v2_server_trojan');
        $this->restoreParentIds('vmess', 'v2_server_vmess');
        $this->restoreParentIds('vless', 'v2_server_vless');
        $this->restoreParentIds('shadowsocks', 'v2_server_shadowsocks');
        $this->restoreParentIds('hysteria', 'v2_server_hysteria');

        // Drop new table
        Schema::dropIfExists('v2_server');
    }
};
