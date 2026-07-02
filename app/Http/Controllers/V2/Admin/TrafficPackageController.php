<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TrafficPackageSave;
use App\Models\Order;
use App\Models\TrafficPackage;
use App\Models\UserTrafficPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrafficPackageController extends Controller
{
    public function fetch(Request $request)
    {
        $packages = TrafficPackage::orderBy('sort', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        return $this->success($packages);
    }

    public function save(TrafficPackageSave $request)
    {
        $params = $request->validated();

        if ($request->input('id')) {
            $package = TrafficPackage::find($request->input('id'));
            if (!$package) {
                return $this->fail([400202, '该流量包不存在']);
            }

            try {
                $package->update($params);
                return $this->success(true);
            } catch (\Exception $e) {
                Log::error($e);
                return $this->fail([500, '保存失败']);
            }
        }

        if (!TrafficPackage::create($params)) {
            return $this->fail([500, '创建失败']);
        }

        return $this->success(true);
    }

    public function drop(Request $request)
    {
        $package = TrafficPackage::find($request->input('id'));
        if (!$package) {
            return $this->fail([400202, '该流量包不存在']);
        }

        if (Order::where('traffic_package_id', $package->id)->exists()) {
            return $this->fail([400201, '该流量包下存在订单无法删除']);
        }

        if (UserTrafficPackage::where('traffic_package_id', $package->id)->exists()) {
            return $this->fail([400201, '该流量包下存在用户流量无法删除']);
        }

        return $this->success($package->delete());
    }

    public function update(Request $request)
    {
        $updateData = $request->only([
            'show',
            'sell'
        ]);

        $package = TrafficPackage::find($request->input('id'));
        if (!$package) {
            return $this->fail([400202, '该流量包不存在']);
        }

        try {
            $package->update($updateData);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '保存失败']);
        }

        return $this->success(true);
    }

    public function sort(Request $request)
    {
        $params = $request->validate([
            'ids' => 'required|array'
        ]);

        try {
            DB::beginTransaction();
            foreach ($params['ids'] as $index => $id) {
                $package = TrafficPackage::find($id);
                if (!$package || !$package->update(['sort' => $index + 1])) {
                    throw new \Exception();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->fail([500, '保存失败']);
        }

        return $this->success(true);
    }
}
