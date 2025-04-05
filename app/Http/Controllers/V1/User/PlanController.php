<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    protected PlanService $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }
    public function fetch(Request $request)
    {
        $user = User::find($request->user()->id);
        if ($request->input('id')) {
            $plan = Plan::where('id', $request->input('id'))->first();
            if (!$plan) {
                return $this->fail([400, __('Subscription plan does not exist')]);
            }
            if (!$this->planService->isPlanAvailableForUser($plan, $user)) {
                return $this->fail([400, __('Subscription plan does not exist')]);
            }
            return $this->success(PlanResource::make($plan));
        }

        $plans = $this->planService->getAvailablePlans();
        return $this->success(PlanResource::collection($plans));
    }
}
