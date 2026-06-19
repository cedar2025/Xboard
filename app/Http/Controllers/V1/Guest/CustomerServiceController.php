<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class CustomerServiceController extends Controller
{
    public function subscription(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::with('plan:id,name,reset_traffic_method')
            ->select([
                'id',
                'email',
                'plan_id',
                'expired_at',
                'u',
                'd',
                'transfer_enable',
                'banned',
                'device_limit',
                'speed_limit',
                'next_reset_at',
            ])
            ->where('email', $request->input('email'))
            ->first();

        if (!$user) {
            return $this->fail([404, 'User does not exist']);
        }

        $userService = app(UserService::class);
        $trafficSummary = $userService->getTrafficSummary($user);
        $uploadTraffic = (int) ($user->u ?? 0);
        $downloadTraffic = (int) ($user->d ?? 0);
        $usedTraffic = $uploadTraffic + $downloadTraffic;
        $transferEnable = $trafficSummary['effective_transfer_enable'];
        $remainingTraffic = $trafficSummary['effective_remaining_traffic'];

        return $this->success([
            'email' => $user->email,
            'plan_id' => $user->plan_id,
            'plan_name' => $user->plan?->name,
            'status' => $this->getSubscriptionStatus($user),
            'expired_at' => $user->expired_at,
            'upload_traffic' => $uploadTraffic,
            'download_traffic' => $downloadTraffic,
            'used_traffic' => $usedTraffic,
            'transfer_enable' => $transferEnable,
            'remaining_traffic' => $remainingTraffic,
            'device_limit' => $user->device_limit,
            'speed_limit' => $user->speed_limit,
            'next_reset_at' => $user->next_reset_at,
            'reset_day' => $user->plan ? $userService->getResetDay($user) : null,
            'plan_transfer_enable' => $trafficSummary['plan_transfer_enable'],
            'plan_used_traffic' => $trafficSummary['plan_used_traffic'],
            'plan_remaining_traffic' => $trafficSummary['plan_remaining_traffic'],
            'traffic_package_total' => $trafficSummary['traffic_package_total'],
            'traffic_package_remaining' => $trafficSummary['traffic_package_remaining'],
            'effective_transfer_enable' => $trafficSummary['effective_transfer_enable'],
            'effective_remaining_traffic' => $trafficSummary['effective_remaining_traffic'],
        ]);
    }

    private function getSubscriptionStatus(User $user): string
    {
        if ($user->banned) {
            return 'banned';
        }

        if (!$user->plan_id || !$user->plan) {
            return 'no_plan';
        }

        if ($user->expired_at !== null && $user->expired_at <= time()) {
            if (app(UserService::class)->isAvailable($user)) {
                return 'package_active';
            }
            return 'expired';
        }

        return 'active';
    }
}
