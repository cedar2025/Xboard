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
        $startDate = now()->startOfMonth()->timestamp;
        
        // Aggregate per-node data into per-day entries for backward compatibility
        $records = StatUser::query()
            ->select([
                'user_id',
                'server_rate',
                'record_at',
                'record_type',
                DB::raw('SUM(u) as u'),
                DB::raw('SUM(d) as d'),
            ])
            ->where('user_id', $request->user()->id)
            ->where('record_at', '>=', $startDate)
            ->groupBy(['user_id', 'server_rate', 'record_at', 'record_type'])
            ->orderBy('record_at', 'DESC')
            ->get()
            ->map(function ($item) {
                $item->u = (int) $item->u;
                $item->d = (int) $item->d;
                return $item;
            });

        $data = TrafficLogResource::collection($records);
        return $this->success($data);
    }
}
