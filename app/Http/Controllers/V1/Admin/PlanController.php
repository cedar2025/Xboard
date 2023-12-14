<?php

namespace App\Http\Controllers\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanSave;
use App\Http\Requests\Admin\PlanSort;
use App\Http\Requests\Admin\PlanUpdate;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function fetch(Request $request)
    {
        $counts = PlanService::countActiveUsers();
        $plans = Plan::orderBy('sort', 'ASC')->get();
        foreach ($plans as $k => $v) {
            $plans[$k]->count = 0;
            foreach ($counts as $kk => $vv) {
                if ($plans[$k]->id === $counts[$kk]->plan_id) $plans[$k]->count = $counts[$kk]->count;
            }
        }
        return $this->success($plans);
    }

    public function save(PlanSave $request)
    {
        $params = $request->validated();
        if ($request->input('id')) {
            $plan = Plan::find($request->input('id'));
            if (!$plan) {
                return $this->fail([400202 ,'该订阅不存在']);
            }
            DB::beginTransaction();
            // update user group id and transfer
            try {
                if ($request->input('force_update')) {
                    User::where('plan_id', $plan->id)->update([
                        'group_id' => $params['group_id'],
                        'transfer_enable' => $params['transfer_enable'] * 1073741824,
                        'speed_limit' => $params['speed_limit']
                    ]);
                }
                $plan->update($params);
                DB::commit();
                return $this->success(true);
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error($e);
                return $this->fail([500 ,'保存失败']);
            }
        }
        if (!Plan::create($params)) {
            return $this->fail([500 ,'创建失败']);
        }
        return $this->success(true);
    }

    public function drop(Request $request)
    {
        if (Order::where('plan_id', $request->input('id'))->first()) {
            return $this->fail([400201 ,'该订阅下存在订单无法删除']);
        }
        if (User::where('plan_id', $request->input('id'))->first()) {
            return $this->fail([400201 ,'该订阅下存在用户无法删除']);
        }
        if ($request->input('id')) {
            $plan = Plan::find($request->input('id'));
            if (!$plan) {
                return $this->fail([400202 ,'该订阅不存在']);
            }
        }
        return $this->success($plan->delete());
    }

    public function update(PlanUpdate $request)
    {
        $updateData = $request->only([
            'show',
            'renew'
        ]);

        $plan = Plan::find($request->input('id'));
        if (!$plan) {
            return $this->fail([400202 ,'该订阅不存在']);
        }

        try {
            $plan->update($updateData);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500 ,'保存失败']);
        }

        return $this->success();
    }

    public function sort(PlanSort $request)
    {
        
        try{
            DB::beginTransaction();
            foreach ($request->input('plan_ids') as $k => $v) {
                if (!Plan::find($v)->update(['sort' => $k + 1])) {
                    throw new \Exception();
                }
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            \Log::error($e);
            return $this->fail([500 ,'保存失败']);
        }
        return $this->success(true);
    }
}
