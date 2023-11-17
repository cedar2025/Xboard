<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OriginV2bMigrationsTableSeeder extends Seeder
{

    /**
     * 原版V2b数据库迁移初始化
     *
     * @return void
     */
    public function run()
    {
        
        try{    
            \Artisan::call("migrate:install");
        }catch(\Exception $e){

        }
        \DB::table('migrations')->insert(array (
            0 => 
            array (
                'id' => 1,
                'migration' => '2019_08_19_000000_create_failed_jobs_table',
                'batch' => 1,
            ),
            1 => 
            array (
                'id' => 2,
                'migration' => '2023_08_07_205816_create_v2_commission_log_table',
                'batch' => 1,
            ),
            2 => 
            array (
                'id' => 3,
                'migration' => '2023_08_07_205816_create_v2_coupon_table',
                'batch' => 1,
            ),
            3 => 
            array (
                'id' => 4,
                'migration' => '2023_08_07_205816_create_v2_invite_code_table',
                'batch' => 1,
            ),
            4 => 
            array (
                'id' => 5,
                'migration' => '2023_08_07_205816_create_v2_knowledge_table',
                'batch' => 1,
            ),
            5 => 
            array (
                'id' => 6,
                'migration' => '2023_08_07_205816_create_v2_log_table',
                'batch' => 1,
            ),
            6 => 
            array (
                'id' => 7,
                'migration' => '2023_08_07_205816_create_v2_mail_log_table',
                'batch' => 1,
            ),
            7 => 
            array (
                'id' => 8,
                'migration' => '2023_08_07_205816_create_v2_notice_table',
                'batch' => 1,
            ),
            8 => 
            array (
                'id' => 9,
                'migration' => '2023_08_07_205816_create_v2_order_table',
                'batch' => 1,
            ),
            9 => 
            array (
                'id' => 10,
                'migration' => '2023_08_07_205816_create_v2_payment_table',
                'batch' => 1,
            ),
            10 => 
            array (
                'id' => 11,
                'migration' => '2023_08_07_205816_create_v2_plan_table',
                'batch' => 1,
            ),
            11 => 
            array (
                'id' => 12,
                'migration' => '2023_08_07_205816_create_v2_server_group_table',
                'batch' => 1,
            ),
            12 => 
            array (
                'id' => 13,
                'migration' => '2023_08_07_205816_create_v2_server_hysteria_table',
                'batch' => 1,
            ),
            13 => 
            array (
                'id' => 14,
                'migration' => '2023_08_07_205816_create_v2_server_route_table',
                'batch' => 1,
            ),
            14 => 
            array (
                'id' => 15,
                'migration' => '2023_08_07_205816_create_v2_server_shadowsocks_table',
                'batch' => 1,
            ),
            15 => 
            array (
                'id' => 16,
                'migration' => '2023_08_07_205816_create_v2_server_trojan_table',
                'batch' => 1,
            ),
            16 => 
            array (
                'id' => 17,
                'migration' => '2023_08_07_205816_create_v2_server_vless_table',
                'batch' => 1,
            ),
            17 => 
            array (
                'id' => 18,
                'migration' => '2023_08_07_205816_create_v2_server_vmess_table',
                'batch' => 1,
            ),
            18 => 
            array (
                'id' => 19,
                'migration' => '2023_08_07_205816_create_v2_stat_server_table',
                'batch' => 1,
            ),
            19 => 
            array (
                'id' => 20,
                'migration' => '2023_08_07_205816_create_v2_stat_table',
                'batch' => 1,
            ),
            20 => 
            array (
                'id' => 21,
                'migration' => '2023_08_07_205816_create_v2_stat_user_table',
                'batch' => 1,
            ),
            21 => 
            array (
                'id' => 22,
                'migration' => '2023_08_07_205816_create_v2_ticket_message_table',
                'batch' => 1,
            ),
            22 => 
            array (
                'id' => 23,
                'migration' => '2023_08_07_205816_create_v2_ticket_table',
                'batch' => 1,
            ),
            23 => 
            array (
                'id' => 24,
                'migration' => '2023_08_07_205816_create_v2_user_table',
                'batch' => 1,
            )
        ));
    }
}