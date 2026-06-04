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

        $uploadTraffic = (int) ($user->u ?? 0);
        $downloadTraffic = (int) ($user->d ?? 0);
        $usedTraffic = $uploadTraffic + $downloadTraffic;
        $transferEnable = (int) ($user->transfer_enable ?? 0);
        $remainingTraffic = max(0, $transferEnable - $usedTraffic);

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
            'reset_day' => $user->plan ? app(UserService::class)->getResetDay($user) : null,
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
            return 'expired';
        }

        return 'active';
    }
}
