<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function check(Request $request)
    {
        if (empty($request->input('code'))) {
            return $this->fail([422,__('Coupon cannot be empty')]);
        }
        $couponService = new CouponService($request->input('code'));
        $couponService->setPlanId($request->input('plan_id'));
        $couponService->setUserId($request->user['id']);
        $couponService->check();
        return $this->success($couponService->getCoupon());
    }
}
