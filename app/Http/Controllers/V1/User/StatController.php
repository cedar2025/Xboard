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
        $startDate = strtotime(now()->startOfMonth());
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
            $records = collect($todayTraffics)->merge($records);
            $records = $records->map(function ($record) {
                $record['server_rate'] = number_format($record['server_rate'], 2);
                return $record;
            });
        }
        $data = TrafficLogResource::collection(collect($records));
        return $this->success($data);
    }
}
