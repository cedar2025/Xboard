<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserTrafficPackage;

class TrafficPackageService
{
    private const BYTES_PER_GB = 1073741824;

    public function createFromOrder(Order $order, User $user, Plan $plan): UserTrafficPackage
    {
        $totalBytes = (int) ($plan->transfer_enable * self::BYTES_PER_GB);

        return UserTrafficPackage::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'plan_id' => $plan->id,
            'total_bytes' => $totalBytes,
            'remaining_bytes' => $totalBytes,
            'status' => UserTrafficPackage::STATUS_ACTIVE,
        ]);
    }

    public function hasActivePackageBalance(int $userId): bool
    {
        return UserTrafficPackage::where('user_id', $userId)
            ->where('status', UserTrafficPackage::STATUS_ACTIVE)
            ->where('remaining_bytes', '>', 0)
            ->exists();
    }

    public function getRemainingBytes(int $userId): int
    {
        return (int) UserTrafficPackage::where('user_id', $userId)
            ->where('status', UserTrafficPackage::STATUS_ACTIVE)
            ->where('remaining_bytes', '>', 0)
            ->sum('remaining_bytes');
    }

    public function getActiveTotalBytes(int $userId): int
    {
        return (int) UserTrafficPackage::where('user_id', $userId)
            ->where('status', UserTrafficPackage::STATUS_ACTIVE)
            ->where('remaining_bytes', '>', 0)
            ->sum('total_bytes');
    }

    public function consume(int $userId, int $uploadBytes, int $downloadBytes): array
    {
        $remainingUpload = max(0, $uploadBytes);
        $remainingDownload = max(0, $downloadBytes);
        $packageUpload = 0;
        $packageDownload = 0;

        $packages = UserTrafficPackage::where('user_id', $userId)
            ->where('status', UserTrafficPackage::STATUS_ACTIVE)
            ->where('remaining_bytes', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($packages as $package) {
            if ($remainingUpload <= 0 && $remainingDownload <= 0) {
                break;
            }

            $available = (int) $package->remaining_bytes;
            $usedUpload = min($available, $remainingUpload);
            $available -= $usedUpload;
            $remainingUpload -= $usedUpload;
            $packageUpload += $usedUpload;

            $usedDownload = min($available, $remainingDownload);
            $available -= $usedDownload;
            $remainingDownload -= $usedDownload;
            $packageDownload += $usedDownload;

            $package->remaining_bytes = $available;
            if ($available <= 0) {
                $package->status = UserTrafficPackage::STATUS_DEPLETED;
                $package->depleted_at = time();
            }
            $package->save();
        }

        return [
            'package_upload' => $packageUpload,
            'package_download' => $packageDownload,
            'plan_upload' => $remainingUpload,
            'plan_download' => $remainingDownload,
        ];
    }

    public function getTrafficSummary(User $user): array
    {
        $planTransferEnable = (int) ($user->transfer_enable ?? 0);
        $planUsedTraffic = (int) (($user->u ?? 0) + ($user->d ?? 0));
        $planRemainingTraffic = max(0, $planTransferEnable - $planUsedTraffic);
        $trafficPackageTotal = $user->id ? $this->getActiveTotalBytes($user->id) : 0;
        $trafficPackageRemaining = $user->id ? $this->getRemainingBytes($user->id) : 0;

        return [
            'plan_transfer_enable' => $planTransferEnable,
            'plan_used_traffic' => $planUsedTraffic,
            'plan_remaining_traffic' => $planRemainingTraffic,
            'traffic_package_total' => $trafficPackageTotal,
            'traffic_package_remaining' => $trafficPackageRemaining,
            'effective_transfer_enable' => $planTransferEnable + $trafficPackageRemaining,
            'effective_remaining_traffic' => $planRemainingTraffic + $trafficPackageRemaining,
        ];
    }
}
