<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponGenerate;
use App\Http\Requests\Admin\CouponSave;
use App\Models\Coupon;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    private function applyFiltersAndSorts(Request $request, $builder)
    {
        if ($request->has('filter')) {
            collect($request->input('filter'))->each(function ($filter) use ($builder) {
                $key = $filter['id'];
                $value = $filter['value'];
                $builder->where(function ($query) use ($key, $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, 'like', "%{$value}%");
                    }
                });
            });
        }

        if ($request->has('sort')) {
            collect($request->input('sort'))->each(function ($sort) use ($builder) {
                $key = $sort['id'];
                $value = $sort['desc'] ? 'DESC' : 'ASC';
                $builder->orderBy($key, $value);
            });
        }
    }
    public function fetch(Request $request)
    {
        $current = $request->input('current', 1);
        $pageSize = $request->input('pageSize', 10);
        $builder = Coupon::query();
        $this->applyFiltersAndSorts($request, $builder);
        $coupons = $builder
            ->orderBy('created_at', 'desc')
            ->paginate($pageSize, ["*"], 'page', $current);
        return $this->paginate($coupons);
    }

    public function update(Request $request)
    {
        $params = $request->validate([
            'id' => 'required|numeric',
            'show' => 'nullable|boolean'
        ], [
            'id.required' => '优惠券ID不能为空',
            'id.numeric' => '优惠券ID必须为数字'
        ]);
        try {
            DB::beginTransaction();
            $coupon = Coupon::find($request->input('id'));
            if (!$coupon) {
                throw new ApiException(400201, '优惠券不存在');
            }
            $coupon->update($params);
            DB::commit();
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500, '保存失败']);
        }
    }

    public function show(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => '优惠券ID不能为空',
            'id.numeric' => '优惠券ID必须为数字'
        ]);
        $coupon = Coupon::find($request->input('id'));
        if (!$coupon) {
            return $this->fail([400202, '优惠券不存在']);
        }
        $coupon->show = !$coupon->show;
        if (!$coupon->save()) {
            return $this->fail([500, '保存失败']);
        }
        return $this->success(true);
    }

    public function generate(CouponGenerate $request)
    {
        if ($request->input('generate_count')) {
            $this->multiGenerate($request);
            return;
        }

        $params = $request->validated();
        if (!$request->input('id')) {
            if (!isset($params['code'])) {
                $params['code'] = Helper::randomChar(8);
            }
            if (!Coupon::create($params)) {
                return $this->fail([500, '创建失败']);
            }
        } else {
            try {
                Coupon::find($request->input('id'))->update($params);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500, '保存失败']);
            }
        }

        return $this->success(true);
    }

    private function multiGenerate(CouponGenerate $request)
    {
        $coupons = [];
        $coupon = $request->validated();
        $coupon['created_at'] = $coupon['updated_at'] = time();
        $coupon['show'] = 1;
        unset($coupon['generate_count']);
        for ($i = 0; $i < $request->input('generate_count'); $i++) {
            $coupon['code'] = Helper::randomChar(8);
            array_push($coupons, $coupon);
        }
        try {
            DB::beginTransaction();
            if (
                !Coupon::insert(array_map(function ($item) use ($coupon) {
                    // format data
                    if (isset($item['limit_plan_ids']) && is_array($item['limit_plan_ids'])) {
                        $item['limit_plan_ids'] = json_encode($coupon['limit_plan_ids']);
                    }
                    if (isset($item['limit_period']) && is_array($item['limit_period'])) {
                        $item['limit_period'] = json_encode($coupon['limit_period']);
                    }
                    return $item;
                }, $coupons))
            ) {
                throw new \Exception();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail([500, '生成失败']);
        }

        $data = "名称,类型,金额或比例,开始时间,结束时间,可用次数,可用于订阅,券码,生成时间\r\n";
        foreach ($coupons as $coupon) {
            $type = ['', '金额', '比例'][$coupon['type']];
            $value = ['', ($coupon['value'] / 100), $coupon['value']][$coupon['type']];
            $startTime = date('Y-m-d H:i:s', $coupon['started_at']);
            $endTime = date('Y-m-d H:i:s', $coupon['ended_at']);
            $limitUse = $coupon['limit_use'] ?? '不限制';
            $createTime = date('Y-m-d H:i:s', $coupon['created_at']);
            $limitPlanIds = isset($coupon['limit_plan_ids']) ? implode("/", $coupon['limit_plan_ids']) : '不限制';
            $data .= "{$coupon['name']},{$type},{$value},{$startTime},{$endTime},{$limitUse},{$limitPlanIds},{$coupon['code']},{$createTime}\r\n";
        }
        echo $data;
    }

    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => '优惠券ID不能为空',
            'id.numeric' => '优惠券ID必须为数字'
        ]);
        $coupon = Coupon::find($request->input('id'));
        if (!$coupon) {
            return $this->fail([400202, '优惠券不存在']);
        }
        if (!$coupon->delete()) {
            return $this->fail([500, '删除失败']);
        }

        return $this->success(true);
    }
}
