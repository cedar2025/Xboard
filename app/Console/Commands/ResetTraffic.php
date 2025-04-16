<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetTraffic extends Command
{
    protected $builder;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:traffic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '流量清空';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->builder = User::where('expired_at', '!=', NULL)
            ->where('expired_at', '>', time());
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', -1);
        $resetMethods = Plan::select(
            DB::raw("GROUP_CONCAT(`id`) as plan_ids"),
            DB::raw("reset_traffic_method as method")
        )
            ->groupBy('reset_traffic_method')
            ->get()
            ->toArray();
        foreach ($resetMethods as $resetMethod) {
            $planIds = explode(',', $resetMethod['plan_ids']);
            switch (true) {
                case ($resetMethod['method'] === NULL): {
                        $resetTrafficMethod = admin_setting('reset_traffic_method', 0);
                        $builder = with(clone ($this->builder))->whereIn('plan_id', $planIds);
                        switch ((int) $resetTrafficMethod) {
                            // month first day
                            case 0:
                                $this->resetByMonthFirstDay($builder);
                                break;
                            // expire day
                            case 1:
                                $this->resetByExpireDay($builder);
                                break;
                            // no action
                            case 2:
                                break;
                            // year first day
                            case 3:
                                $this->resetByYearFirstDay($builder);
                            // year expire day
                            case 4:
                                $this->resetByExpireYear($builder);
                        }
                        break;
                    }
                case ($resetMethod['method'] === 0): {
                        $builder = with(clone ($this->builder))->whereIn('plan_id', $planIds);
                        $this->resetByMonthFirstDay($builder);
                        break;
                    }
                case ($resetMethod['method'] === 1): {
                        $builder = with(clone ($this->builder))->whereIn('plan_id', $planIds);
                        $this->resetByExpireDay($builder);
                        break;
                    }
                case ($resetMethod['method'] === 2): {
                        break;
                    }
                case ($resetMethod['method'] === 3): {
                        $builder = with(clone ($this->builder))->whereIn('plan_id', $planIds);
                        $this->resetByYearFirstDay($builder);
                        break;
                    }
                case ($resetMethod['method'] === 4): {
                        $builder = with(clone ($this->builder))->whereIn('plan_id', $planIds);
                        $this->resetByExpireYear($builder);
                        break;
                    }
            }
        }
    }

    private function resetByExpireYear($builder): void
    {

        $users = $builder->with('plan')->get();
        $usersToUpdate = [];
        foreach ($users as $user) {
            $expireDay = date('m-d', $user->expired_at);
            $today = date('m-d');
            if ($expireDay === $today) {
                $usersToUpdate[] = [
                    'id' => $user->id,
                    'transfer_enable' => $user->plan->transfer_enable
                ];
            }
        }

        foreach ($usersToUpdate as $userData) {
            User::where('id', $userData['id'])->update([
                'transfer_enable' => (intval($userData['transfer_enable']) * 1073741824),
                'u' => 0,
                'd' => 0
            ]);
        }
    }

    private function resetByYearFirstDay($builder): void
    {
        $users = $builder->with('plan')->get();
        $usersToUpdate = [];
        foreach ($users as $user) {
            if ((string) date('md') === '0101') {
                $usersToUpdate[] = [
                    'id' => $user->id,
                    'transfer_enable' => $user->plan->transfer_enable
                ];
            }
        }

        foreach ($usersToUpdate as $userData) {
            User::where('id', $userData['id'])->update([
                'transfer_enable' => (intval($userData['transfer_enable']) * 1073741824),
                'u' => 0,
                'd' => 0
            ]);
        }
    }

    private function resetByMonthFirstDay($builder): void
    {
        $users = $builder->with('plan')->get();
        $usersToUpdate = [];
        foreach ($users as $user) {
            if ((string) date('d') === '01') {
                $usersToUpdate[] = [
                    'id' => $user->id,
                    'transfer_enable' => $user->plan->transfer_enable
                ];
            }
        }

        foreach ($usersToUpdate as $userData) {
            User::where('id', $userData['id'])->update([
                'transfer_enable' => (intval($userData['transfer_enable']) * 1073741824),
                'u' => 0,
                'd' => 0
            ]);
        }
    }
    private function resetByExpireDay($builder): void
    {
        $lastDay = date('d', strtotime('last day of +0 months'));
        $today = date('d');
        $users = $builder->with('plan')->get();
        $usersToUpdate = [];

        foreach ($users as $user) {
            $expireMonth = date('m', $user->expired_at);
            $currentMonth = date('m');
            if ($expireMonth == $currentMonth) {
                continue;
            }
            
            $expireDay = date('d', $user->expired_at);
            if ($expireDay === $today || ($today === $lastDay && $expireDay >= $today)) {
                $usersToUpdate[] = [
                    'id' => $user->id,
                    'transfer_enable' => $user->plan->transfer_enable
                ];
            }
        }

        foreach ($usersToUpdate as $userData) {
            User::where('id', $userData['id'])->update([
                'transfer_enable' => (intval($userData['transfer_enable']) * 1073741824),
                'u' => 0,
                'd' => 0
            ]);
        }
    }
}
