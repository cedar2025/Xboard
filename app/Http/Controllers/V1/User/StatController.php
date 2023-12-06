<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\StatUser;
use App\Services\StatisticalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatController extends Controller
{
    public function getTrafficLog(Request $request)
    {
        $records = StatUser::select([
            'u',
            'd',
            'record_at',
            'user_id',
            'server_rate'
        ])
            ->where('user_id', $request->user['id'])
            ->where('record_at', '>=', strtotime(date('Y-m-1')))
            ->orderBy('record_at', 'DESC')
            ->get();

         // 追加当天流量
         $recordAt = strtotime(date('Y-m-d'));
         $statService = new StatisticalService();
         $statService->setStartAt($recordAt);
         $statService->setUserStats();
         $todayTraffics = $statService->getStatUserByUserID($request->user['id']);
         if (count($todayTraffics) > 0) {
             foreach ($todayTraffics as $todayTraffic){
                 $todayTraffic['server_rate'] = number_format($todayTraffic['server_rate'], 2);
                 $records->prepend($todayTraffic);
             } 
         };

        return $this->success($records);
    }
}
