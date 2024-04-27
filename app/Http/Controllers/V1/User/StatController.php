<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrafficLogResource;
use App\Models\StatUser;
use App\Services\StatisticalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatController extends Controller
{
    public function getTrafficLog(Request $request)
    {
        $startDate = now()->startOfMonth();
        $records = StatUser::query()
            ->where('user_id', $request->user['id'])
            ->where('record_at', '>=', $startDate)
            ->orderBy('record_at', 'DESC')
            ->get();

        // 追加当天流量
        $recordAt = strtotime(date('Y-m-d'));
        $statService = new StatisticalService();
        $statService->setStartAt($recordAt);
        $todayTraffics = $statService->getStatUserByUserID($request->user['id']);
        if (count($todayTraffics) > 0) {
            $todayTraffics = collect($todayTraffics)->map(function ($todayTraffic) {
                $todayTraffic['server_rate'] = number_format($todayTraffic['server_rate'], 2);
                return $todayTraffic;
            });
            $records = $todayTraffics->merge($records);
        }
        $data = TrafficLogResource::collection(collect($records));
        return $this->success($data);
    }
}
