<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class MigrateFromV2b extends Command
{
    protected $signature = 'migrateFromV2b {version?}';
    protected $description = '供不同版本V2b迁移到本项目的脚本';

    public function handle()
    {
        $version = $this->argument('version');
        if($version === 'config'){
            $this->MigrateV2ConfigToV2Settings();
            return;
        }

        // Define your SQL commands based on versions
        $sqlCommands = [
            'dev231027' => [
                // SQL commands for version Dev 2023/10/27
                'ALTER TABLE v2_order ADD COLUMN surplus_order_ids TEXT NULL;',
                'ALTER TABLE v2_plan DROP COLUMN daily_unit_price, DROP COLUMN transfer_unit_price;',
                'ALTER TABLE v2_server_hysteria DROP COLUMN ignore_client_bandwidth, DROP COLUMN obfs_type;'
            ],            
            '1.7.4' => [
                'CREATE TABLE `v2_server_vless` ( 
                    `id` INT AUTO_INCREMENT PRIMARY KEY, 
                    `group_id` TEXT NOT NULL, 
                    `route_id` TEXT NULL, 
                    `name` VARCHAR(255) NOT NULL,
                    `parent_id` INT NULL, 
                    `host` VARCHAR(255) NOT NULL, 
                    `port` INT NOT NULL, 
                    `server_port` INT NOT NULL, 
                    `tls` BOOLEAN NOT NULL, 
                    `tls_settings` TEXT NULL, 
                    `flow` VARCHAR(64) NULL, 
                    `network` VARCHAR(11) NOT NULL, 
                    `network_settings` TEXT NULL, 
                    `tags` TEXT NULL, 
                    `rate` VARCHAR(11) NOT NULL, 
                    `show` BOOLEAN DEFAULT 0, 
                    `sort` INT NULL, 
                    `created_at` INT NOT NULL, 
                    `updated_at` INT NOT NULL
                );'
            ],
            '1.7.3' => [
                'ALTER TABLE `v2_stat_order` RENAME TO `v2_stat`;',
                "ALTER TABLE `v2_stat` CHANGE COLUMN order_amount order_total INT COMMENT '订单合计';",
                "ALTER TABLE `v2_stat` CHANGE COLUMN commission_amount commission_total INT COMMENT '佣金合计';",
                "ALTER TABLE `v2_stat`
                    ADD COLUMN paid_count INT NULL,
                    ADD COLUMN paid_total INT NULL,
                    ADD COLUMN register_count INT NULL,
                    ADD COLUMN invite_count INT NULL,
                    ADD COLUMN transfer_used_total VARCHAR(32) NULL;
                ",  
                "CREATE TABLE `v2_log` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `title` TEXT NOT NULL,
                    `level` VARCHAR(11) NULL,
                    `host` VARCHAR(255) NULL,
                    `uri` VARCHAR(255) NOT NULL,
                    `method` VARCHAR(11) NOT NULL,
                    `data` TEXT NULL,
                    `ip` VARCHAR(128) NULL,
                    `context` TEXT NULL,
                    `created_at` INT NOT NULL,
                    `updated_at` INT NOT NULL
                );",
                'CREATE TABLE `v2_server_hysteria` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `group_id` VARCHAR(255) NOT NULL,
                    `route_id` VARCHAR(255) NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `parent_id` INT NULL,
                    `host` VARCHAR(255) NOT NULL,
                    `port` VARCHAR(11) NOT NULL,
                    `server_port` INT NOT NULL,
                    `tags` VARCHAR(255) NULL,
                    `rate` VARCHAR(11) NOT NULL,
                    `show` BOOLEAN DEFAULT FALSE,
                    `sort` INT NULL,
                    `up_mbps` INT NOT NULL,
                    `down_mbps` INT NOT NULL,
                    `server_name` VARCHAR(64) NULL,
                    `insecure` BOOLEAN DEFAULT FALSE,
                    `created_at` INT NOT NULL,
                    `updated_at` INT NOT NULL
                );',
                "CREATE TABLE `v2_server_vless` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY, 
                    `group_id` TEXT NOT NULL, 
                    `route_id` TEXT NULL, 
                    `name` VARCHAR(255) NOT NULL, 
                    `parent_id` INT NULL, 
                    `host` VARCHAR(255) NOT NULL, 
                    `port` INT NOT NULL, 
                    `server_port` INT NOT NULL, 
                    `tls` BOOLEAN NOT NULL, 
                    `tls_settings` TEXT NULL, 
                    `flow` VARCHAR(64) NULL, 
                    `network` VARCHAR(11) NOT NULL, 
                    `network_settings` TEXT NULL, 
                    `tags` TEXT NULL, 
                    `rate` VARCHAR(11) NOT NULL, 
                    `show` BOOLEAN DEFAULT FALSE, 
                    `sort` INT NULL, 
                    `created_at` INT NOT NULL, 
                    `updated_at` INT NOT NULL
                );",
            ],
            'wyx2685' => [
                "ALTER TABLE `v2_plan` DROP COLUMN `device_limit`;",
                "ALTER TABLE `v2_server_hysteria` DROP COLUMN `version`, DROP COLUMN `obfs`, DROP COLUMN `obfs_password`;",
                "ALTER TABLE `v2_server_trojan` DROP COLUMN `network`, DROP COLUMN `network_settings`;",
                "ALTER TABLE `v2_user` DROP COLUMN `device_limit`;"
            ]
        ];

        if (!$version) {
            $version = $this->choice('请选择你迁移前的V2board版本:', array_keys($sqlCommands));
        }

        if (array_key_exists($version, $sqlCommands)) {
            
            try {
                foreach ($sqlCommands[$version] as $sqlCommand) {
                    // Execute SQL command
                    \DB::statement($sqlCommand);
                }
                
                $this->info('1️⃣、数据库差异矫正成功');

                // 初始化数据库迁移
                $this->call('db:seed', ['--class' => 'OriginV2bMigrationsTableSeeder']);
                $this->info('2️⃣、数据库迁移记录初始化成功');

                $this->call('xboard:update');
                $this->info('3️⃣、更新成功');

                $this->info("🎉：成功从 $version 迁移到Xboard");
            } catch (\Exception $e) {
                // An error occurred, rollback the transaction
                $this->error('迁移失败'. $e->getMessage() );
            }


        } else {
            $this->error("你所输入的版本未找到");
        }
    }

    public function MigrateV2ConfigToV2Settings()
    {
        \Artisan::call('config:clear');
        $configValue = config('v2board') ?? [];

        foreach ($configValue as $k => $v) {
            // 检查记录是否已存在
            $existingSetting = Setting::where('name', $k)->first();
            
            // 如果记录不存在，则插入
            if ($existingSetting) {
                $this->warn("配置 ${k} 在数据库已经存在， 忽略");
                continue;
            }
            Setting::create([
                'name' => $k,
                'value' => is_array($v)? json_encode($v) : $v,
            ]);
            $this->info("配置 ${k} 迁移成功");
        }
        \Artisan::call('config:cache');

        $this->info('所有配置迁移完成');
    }
}
