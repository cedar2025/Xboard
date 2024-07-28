<?php

namespace App\Console\Commands;

use App\Jobs\AutoGenerateRenewalOrdersJob;
use App\Services\UserService;
use Exception;
use Illuminate\Console\Command;

class GenerateRenewalOrders extends Command
{
    protected $signature = 'generate:renewalOrders';
    protected $description = '自动为用户生成续费订单';

    public function handle()
    {
        $this->info('开始生成续费订单');

        try {
            $userService = new UserService();
            $users = $userService->listUsersHaveSubscription();

            foreach ($users as $user) {
                try {
                    AutoGenerateRenewalOrdersJob::dispatch($user);
                } catch (Exception $e) {
                    $userId = $user->id;
                    $userEmail = $user->email;
                    $this->error("用户 $userId($userEmail) 续费订单生成失败：{$e->getMessage()}");
                }
            }

            $this->info('续费订单检测任务完成');
        } catch (Exception $e) {
            $this->error('获取用户列表失败：' . $e->getMessage());
            return;
        }
    }
}
