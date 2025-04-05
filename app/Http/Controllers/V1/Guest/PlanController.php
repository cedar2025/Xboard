<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\PlanService;
use Auth;
use Illuminate\Http\Request;

class PlanController extends Controller
{

    protected $planService;
    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }
    public function fetch(Request $request)
    {
        $language = $request->input('language', Plan::DEFAULT_LANGUAGE);
        $plans = $this->planService->getAvailablePlans($language);
        return $this->success(PlanResource::collection($plans));
    }
}
