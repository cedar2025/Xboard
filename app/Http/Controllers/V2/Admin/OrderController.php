<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderAssign;
use App\Http\Requests\Admin\OrderUpdate;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PlanService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{

    public function detail(Request $request)
    {
        $order = Order::with(['user', 'plan', 'commission_log'])->find($request->input('id'));
        if (!$order)
            return $this->fail([400202, '订单不存在']);
        if ($order->surplus_order_ids) {
            $order['surplus_orders'] = Order::whereIn('id', $order->surplus_order_ids)->get();
        }
        $order['period'] = PlanService::getLegacyPeriod((string) $order->period);
        return $this->success($order);
    }

    public function fetch(Request $request)
    {
        $current = $request->input('current', 1);
        $pageSize = $request->input('pageSize', 10);
        $orderModel = Order::with('plan:id,name');

        if ($request->boolean('is_commission')) {
            $orderModel->whereNotNull('invite_user_id')
                ->whereNotIn('status', [0, 2])
                ->where('commission_balance', '>', 0);
        }

        $this->applyFiltersAndSorts($request, $orderModel);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginatedResults */
        $paginatedResults = $orderModel
            ->latest('created_at')
            ->paginate(
                perPage: $pageSize,
                page: $current
            );

        $paginatedResults->getCollection()->transform(function($order) {
            $orderArray = $order->toArray();
            $orderArray['period'] = PlanService::getLegacyPeriod((string) $order->period);
            return $orderArray;
        });

        return response()->json($paginatedResults);
    }

    private function applyFiltersAndSorts(Request $request, Builder $builder): void
    {
        $this->applyFilters($request, $builder);
        $this->applySorting($request, $builder);
    }

    private function applyFilters(Request $request, Builder $builder): void
    {
        if (!$request->has('filter')) {
            return;
        }

        collect($request->input('filter'))->each(function ($filter) use ($builder) {
            $field = $filter['id'];
            $value = $filter['value'];

            $builder->where(function ($query) use ($field, $value) {
                $this->buildFilterQuery($query, $field, $value);
            });
        });
    }

    private function buildFilterQuery(Builder $query, string $field, mixed $value): void
    {
        // Handle array values for 'in' operations
        if (is_array($value)) {
            $query->whereIn($field, $value);
            return;
        }

        // Handle operator-based filtering
        if (!is_string($value) || !str_contains($value, ':')) {
            $query->where($field, 'like', "%{$value}%");
            return;
        }

        [$operator, $filterValue] = explode(':', $value, 2);

        // Convert numeric strings to appropriate type
        if (is_numeric($filterValue)) {
            $filterValue = strpos($filterValue, '.') !== false
                ? (float) $filterValue
                : (int) $filterValue;
        }

        // Apply operator
        $query->where($field, match (strtolower($operator)) {
            'eq' => '=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'like' => 'like',
            'notlike' => 'not like',
            'null' => static fn($q) => $q->whereNull($field),
            'notnull' => static fn($q) => $q->whereNotNull($field),
            default => 'like'
        }, match (strtolower($operator)) {
            'like', 'notlike' => "%{$filterValue}%",
            'null', 'notnull' => null,
            default => $filterValue
        });
    }

    private function applySorting(Request $request, Builder $builder): void
    {
        if (!$request->has('sort')) {
            return;
        }

        collect($request->input('sort'))->each(function ($sort) use ($builder) {
            $field = $sort['id'];
            $direction = $sort['desc'] ? 'DESC' : 'ASC';
            $builder->orderBy($field, $direction);
        });
    }

    public function paid(Request $request)
    {
        $order = Order::where('trade_no', $request->input('trade_no'))
            ->first();
        if (!$order) {
            return $this->fail([400202, '订单不存在']);
        }
        if ($order->status !== 0)
            return $this->fail([400, '只能对待支付的订单进行操作']);

        $orderService = new OrderService($order);
        if (!$orderService->paid('manual_operation')) {
            return $this->fail([500, '更新失败']);
        }
        return $this->success(true);
    }

    public function cancel(Request $request)
    {
        $order = Order::where('trade_no', $request->input('trade_no'))
            ->first();
        if (!$order) {
            return $this->fail([400202, '订单不存在']);
        }
        if ($order->status !== 0)
            return $this->fail([400, '只能对待支付的订单进行操作']);

        $orderService = new OrderService($order);
        if (!$orderService->cancel()) {
            return $this->fail([400, '更新失败']);
        }
        return $this->success(true);
    }

    public function update(OrderUpdate $request)
    {
        $params = $request->only([
            'commission_status'
        ]);

        $order = Order::where('trade_no', $request->input('trade_no'))
            ->first();
        if (!$order) {
            return $this->fail([400202, '订单不存在']);
        }

        try {
            $order->update($params);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '更新失败']);
        }

        return $this->success(true);
    }

    public function assign(OrderAssign $request)
    {
        $plan = Plan::find($request->input('plan_id'));
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return $this->fail([400202, '该用户不存在']);
        }

        if (!$plan) {
            return $this->fail([400202, '该订阅不存在']);
        }

        $userService = new UserService();
        if ($userService->isNotCompleteOrderByUserId($user->id)) {
            return $this->fail([400, '该用户还有待支付的订单，无法分配']);
        }

        try {
            DB::beginTransaction();
            $order = new Order();
            $orderService = new OrderService($order);
            $order->user_id = $user->id;
            $order->plan_id = $plan->id;
            $period = $request->input('period');
            $order->period = PlanService::getPeriodKey((string) $period);
            $order->trade_no = Helper::guid();
            $order->total_amount = $request->input('total_amount');

            if (PlanService::getPeriodKey((string) $order->period) === Plan::PERIOD_RESET_TRAFFIC) {
                $order->type = Order::TYPE_RESET_TRAFFIC;
            } else if ($user->plan_id !== NULL && $order->plan_id !== $user->plan_id) {
                $order->type = Order::TYPE_UPGRADE;
            } else if ($user->expired_at > time() && $order->plan_id == $user->plan_id) {
                $order->type = Order::TYPE_RENEWAL;
            } else {
                $order->type = Order::TYPE_NEW_PURCHASE;
            }

            $orderService->setInvite($user);

            if (!$order->save()) {
                DB::rollBack();
                return $this->fail([500, '订单创建失败']);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->success($order->trade_no);
    }
}
