<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function fetch(Request $request)
    {
        $user = User::find($request->user['id']);
        if ($request->input('id')) {
            $plan = Plan::where('id', $request->input('id'))->first();
            if (!$plan) {
                return $this->fail([400, __('Subscription plan does not exist')]);
            }
            if ((!$plan->show && !$plan->renew) || (!$plan->show && $user->plan_id !== $plan->id)) {
                return $this->fail([400, __('Subscription plan does not exist')]);
            }
            return $this->success($plan);
        }

        $counts = PlanService::countActiveUsers();
        $plans = Plan::where('show', 1)
            ->orderBy('sort', 'ASC')
            ->get();
        foreach ($plans as $k => $v) {
            if ($plans[$k]->capacity_limit === NULL) continue;
            if (!isset($counts[$plans[$k]->id])) continue;
            $plans[$k]->capacity_limit = $plans[$k]->capacity_limit - $counts[$plans[$k]->id]->count;
        }
        return $this->success($plans);
    }
}
