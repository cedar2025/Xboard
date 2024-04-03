<?php

namespace App\Http\Controllers\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function getPaymentMethods()
    {
        $methods = [];
        foreach (glob(base_path('app//Payments') . '/*.php') as $file) {
            array_push($methods, pathinfo($file)['filename']);
        }
        return $this->success($methods);
    }

    public function fetch()
    {
        $payments = Payment::orderBy('sort', 'ASC')->get();
        foreach ($payments as $k => $v) {
            $notifyUrl = url("/api/v1/guest/payment/notify/{$v->payment}/{$v->uuid}");
            if ($v->notify_domain) {
                $parseUrl = parse_url($notifyUrl);
                $notifyUrl = $v->notify_domain . $parseUrl['path'];
            }
            $payments[$k]['notify_url'] = $notifyUrl;
        }
        return $this->success($payments);
    }

    public function getPaymentForm(Request $request)
    {
        $paymentService = new PaymentService($request->input('payment'), $request->input('id'));
        return $this->success($paymentService->form());
    }

    public function show(Request $request)
    {
        $payment = Payment::find($request->input('id'));
        if (!$payment) return $this->fail([400202 ,'支付方式不存在']);
        $payment->enable = !$payment->enable;
        if (!$payment->save()) return $this->fail([500 ,'保存失败']);
        return $this->success(true);
    }

    public function save(Request $request)
    {
        if (!admin_setting('app_url')) {
            return $this->fail([400 ,'请在站点配置中配置站点地址']);
        }
        $params = $request->validate([
            'name' => 'required',
            'icon' => 'nullable',
            'payment' => 'required',
            'config' => 'required',
            'notify_domain' => 'nullable|url',
            'handling_fee_fixed' => 'nullable|integer',
            'handling_fee_percent' => 'nullable|numeric|between:0,100'
        ], [
            'name.required' => '显示名称不能为空',
            'payment.required' => '网关参数不能为空',
            'config.required' => '配置参数不能为空',
            'notify_domain.url' => '自定义通知域名格式有误',
            'handling_fee_fixed.integer' => '固定手续费格式有误',
            'handling_fee_percent.between' => '百分比手续费范围须在0-100之间'
        ]);
        if ($request->input('id')) {
            $payment = Payment::find($request->input('id'));
            if (!$payment) return $this->fail([400202 ,'支付方式不存在']);
            try {
                $payment->update($params);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500 ,'保存失败']);
            }
            return $this->success(true);
        }
        $params['uuid'] = Helper::randomChar(8);
        if (!Payment::create($params)) {
            return $this->fail([500 ,'保存失败']);
        }
        return $this->success(true);
    }

    public function drop(Request $request)
    {
        $payment = Payment::find($request->input('id'));
        if (!$payment) return $this->fail([400202 ,'支付方式不存在']);
        return $this->success($payment->delete());
    }


    public function sort(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ], [
            'ids.required' => '参数有误',
            'ids.array' => '参数有误'
        ]);
        try{
            DB::beginTransaction();
            foreach ($request->input('ids') as $k => $v) {
                if (!Payment::find($v)->update(['sort' => $k + 1])) {
                    throw new \Exception();
                }
            }
            DB::commit();
        }catch(\Exception $e){
            DB::rollBack();
            return $this->fail([500 ,'保存失败']);
        }
        
        return $this->success(true);
    }
}
