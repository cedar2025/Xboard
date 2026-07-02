<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrafficPackageResource;
use App\Services\TrafficPackageService;
use Illuminate\Http\Request;

class TrafficPackageController extends Controller
{
    public function fetch(Request $request, TrafficPackageService $trafficPackageService)
    {
        return $this->success(TrafficPackageResource::collection($trafficPackageService->getAvailablePackages()));
    }
}
