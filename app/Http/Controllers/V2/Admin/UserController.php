<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserGenerate;
use App\Http\Requests\Admin\UserSendMail;
use App\Http\Requests\Admin\UserUpdate;
use App\Jobs\SendEmailJob;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserTrafficPackage;
use App\Services\AuthService;
use App\Services\UserService;
use App\Traits\QueryOperators;
use App\Utils\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    use QueryOperators;

    private ?int $trafficSummaryTimestamp = null;

    public function resetSecret(Request $request)
    {
        $user = User::find($request->input('id'));
        if (!$user)
            return $this->fail([400202, '用户不存在']);
        $user->token = Helper::guid();
        $user->uuid = Helper::guid(true);
        return $this->success($user->save());
    }

    /**
     * Apply filters and sorts to the query builder
     *
     * @param Request $request
     * @param Builder $builder
     * @return void
     */
    private function applyFiltersAndSorts(Request $request, Builder $builder): void
    {
        $this->applyFilters($request, $builder);
        $this->applySorting($request, $builder);
    }

    /**
     * Apply filters to the query builder
     *
     * @param Request $request
     * @param Builder $builder
     * @return void
     */
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

    /**
     * Build the filter query based on field and value
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function buildFilterQuery(Builder $query, string $field, mixed $value): void
    {
        // 处理关联查询
        if (str_contains($field, '.')) {
            [$relation, $relationField] = explode('.', $field);
            $query->whereHas($relation, function ($q) use ($relationField, $value) {
                if (is_array($value)) {
                    $q->whereIn($relationField, $value);
                } else if (is_string($value) && str_contains($value, ':')) {
                    [$operator, $filterValue] = explode(':', $value, 2);
                    $this->applyQueryCondition($q, $relationField, $operator, $filterValue);
                } else {
                    $q->where($relationField, 'like', "%{$value}%");
                }
            });
            return;
        }

        // 处理数组值的 'in' 操作
        if (is_array($value)) {
            $query->whereIn($field === 'group_ids' ? 'group_id' : $field, $value);
            return;
        }

        // 处理基于运算符的过滤
        if (!is_string($value) || !str_contains($value, ':')) {
            $query->where($field, 'like', "%{$value}%");
            return;
        }

        [$operator, $filterValue] = explode(':', $value, 2);

        // 转换数字字符串为适当的类型
        if (is_numeric($filterValue)) {
            $filterValue = strpos($filterValue, '.') !== false
                ? (float) $filterValue
                : (int) $filterValue;
        }

        $trafficFields = $this->getTrafficFieldExpressions();
        $queryField = isset($trafficFields[$field])
            ? DB::raw($trafficFields[$field])
            : $field;

        $this->applyQueryCondition($query, $queryField, $operator, $filterValue);
    }

    /**
     * Apply sorting to the query builder
     *
     * @param Request $request
     * @param Builder $builder
     * @return void
     */
    private function applySorting(Request $request, Builder $builder): void
    {
        if (!$request->has('sort')) {
            return;
        }

        collect($request->input('sort'))->each(function ($sort) use ($builder) {
            $field = $sort['id'];
            $direction = $sort['desc'] ? 'DESC' : 'ASC';
            $trafficFields = $this->getTrafficFieldExpressions();

            if (isset($trafficFields[$field])) {
                $queryField = $trafficFields[$field];
                $builder->orderByRaw("{$queryField} {$direction}");
                return;
            }

            $builder->orderBy($field, $direction);
        });
    }

    /**
     * SQL expressions shared by list output, filters, and sorting.
     *
     * @return array<string, string>
     */
    private function getTrafficFieldExpressions(): array
    {
        $activePlanCondition = $this->getActivePlanCondition();
        $planTransferEnable = "CASE WHEN {$activePlanCondition} THEN COALESCE(v2_user.transfer_enable, 0) ELSE 0 END";
        $planUsedTraffic = "CASE WHEN {$activePlanCondition} THEN COALESCE(v2_user.u, 0) + COALESCE(v2_user.d, 0) ELSE 0 END";
        $trafficPackageTotal = 'COALESCE(traffic_package_summary.traffic_package_total, 0)';
        $trafficPackageUsed = 'COALESCE(traffic_package_summary.traffic_package_used, 0)';
        $trafficPackageRemaining = 'COALESCE(traffic_package_summary.traffic_package_remaining, 0)';

        return [
            'plan_transfer_enable' => $planTransferEnable,
            'traffic_package_total' => $trafficPackageTotal,
            'traffic_package_used' => $trafficPackageUsed,
            'traffic_package_remaining' => $trafficPackageRemaining,
            'total_used' => "({$planUsedTraffic}) + ({$trafficPackageUsed})",
        ];
    }

    private function getActivePlanCondition(): string
    {
        $timestamp = $this->trafficSummaryTimestamp ??= time();

        return '(v2_user.banned = 0'
            . ' AND v2_user.plan_id IS NOT NULL'
            . ' AND COALESCE(v2_user.transfer_enable, 0) > 0'
            . " AND (v2_user.expired_at IS NULL OR v2_user.expired_at > {$timestamp}))";
    }

    /**
     * Fetch paginated user list with filters and sorting
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function fetch(Request $request)
    {
        $current = $request->input('current', 1);
        $pageSize = $request->input('pageSize', 10);

        $trafficPackageSummary = DB::table('v2_user_traffic_packages')
            ->select('user_id')
            ->selectRaw('SUM(total_bytes) AS traffic_package_total')
            ->selectRaw('SUM(total_bytes - remaining_bytes) AS traffic_package_used')
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND remaining_bytes > 0 THEN remaining_bytes ELSE 0 END) AS traffic_package_remaining',
                [UserTrafficPackage::STATUS_ACTIVE]
            )
            ->groupBy('user_id');

        $latestActiveTrafficPackageName = DB::table('v2_user_traffic_packages as latest_package')
            ->leftJoin('v2_traffic_packages as traffic_package', 'traffic_package.id', '=', 'latest_package.traffic_package_id')
            ->leftJoin('v2_plan as legacy_plan', 'legacy_plan.id', '=', 'latest_package.plan_id')
            ->selectRaw('COALESCE(traffic_package.name, legacy_plan.name, ?) ', [__('Traffic Package')])
            ->whereColumn('latest_package.user_id', 'v2_user.id')
            ->where('latest_package.status', UserTrafficPackage::STATUS_ACTIVE)
            ->where('latest_package.remaining_bytes', '>', 0)
            ->orderByDesc('latest_package.id')
            ->limit(1);

        $trafficFields = $this->getTrafficFieldExpressions();
        $activePlanCondition = $this->getActivePlanCondition();

        $userModel = User::with(['plan:id,name', 'invite_user:id,email', 'group:id,name'])
            ->leftJoinSub($trafficPackageSummary, 'traffic_package_summary', function ($join) {
                $join->on('traffic_package_summary.user_id', '=', 'v2_user.id');
            })
            ->select('v2_user.*')
            ->selectRaw("{$trafficFields['plan_transfer_enable']} AS plan_transfer_enable")
            ->selectRaw("{$trafficFields['traffic_package_total']} AS traffic_package_total")
            ->selectRaw("{$trafficFields['traffic_package_used']} AS traffic_package_used")
            ->selectRaw("{$trafficFields['traffic_package_remaining']} AS traffic_package_remaining")
            ->selectRaw("{$trafficFields['total_used']} AS total_used")
            ->selectRaw("CASE WHEN {$activePlanCondition} THEN 1 ELSE 0 END AS has_active_plan")
            ->addSelect(['latest_traffic_package_name' => $latestActiveTrafficPackageName]);

        $this->applyFiltersAndSorts($request, $userModel);

        $users = $userModel->orderBy('v2_user.id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $current);

        $users->getCollection()->transform(function ($user): array {
            return self::transformUserData($user);
        });

        return $this->paginate($users);
    }

    /**
     * Transform user data for response
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public static function transformUserData(User $user): array
    {
        $hasActivePlan = (bool) $user->has_active_plan;
        $hasActiveTrafficPackage = (int) $user->traffic_package_remaining > 0;
        $activeProductName = $hasActivePlan
            ? $user->plan?->name
            : ($hasActiveTrafficPackage ? $user->latest_traffic_package_name : null);

        $data = $user->toArray();
        $data['balance'] = $data['balance'] / 100;
        $data['commission_balance'] = $data['commission_balance'] / 100;
        $data['subscribe_url'] = Helper::getSubscribeUrl($data['token']);
        $data['plan_transfer_enable'] = (int) $data['plan_transfer_enable'];
        $data['traffic_package_total'] = (int) $data['traffic_package_total'];
        $data['traffic_package_used'] = (int) $data['traffic_package_used'];
        $data['traffic_package_remaining'] = (int) $data['traffic_package_remaining'];
        $data['total_used'] = (int) $data['total_used'];
        $data['has_active_plan'] = $hasActivePlan;
        $data['active_product_name'] = $activeProductName;
        unset($data['latest_traffic_package_name']);

        return $data;
    }

    public function getUserInfoById(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => '用户ID不能为空'
        ]);
        $user = User::find($request->input('id'))->load('invite_user');
        return $this->success($user);
    }

    public function update(UserUpdate $request)
    {
        $params = $request->validated();

        $user = User::find($request->input('id'));
        if (!$user) {
            return $this->fail([400202, '用户不存在']);
        }
        if (isset($params['email'])) {
            if (User::where('email', $params['email'])->first() && $user->email !== $params['email']) {
                return $this->fail([400201, '邮箱已被使用']);
            }
        }
        // 处理密码
        if (isset($params['password'])) {
            $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
            $params['password_algo'] = NULL;
        } else {
            unset($params['password']);
        }
        // 处理订阅计划
        if (isset($params['plan_id'])) {
            $plan = Plan::find($params['plan_id']);
            if (!$plan) {
                return $this->fail([400202, '订阅计划不存在']);
            }
            $params['group_id'] = $plan->group_id;
        }
        // 处理邀请用户
        if ($request->input('invite_user_email') && $inviteUser = User::where('email', $request->input('invite_user_email'))->first()) {
            $params['invite_user_id'] = $inviteUser->id;
        } else {
            $params['invite_user_id'] = null;
        }

        if (isset($params['banned']) && (int) $params['banned'] === 1) {
            $authService = new AuthService($user);
            $authService->removeAllSessions();
        }
        if (isset($params['balance'])) {
            $params['balance'] = $params['balance'] * 100;
        }
        if (isset($params['commission_balance'])) {
            $params['commission_balance'] = $params['commission_balance'] * 100;
        }

        try {
            $user->update($params);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '保存失败']);
        }
        return $this->success(true);
    }

    /**
     * 导出用户数据为CSV格式
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function dumpCSV(Request $request)
    {
        ini_set('memory_limit', '-1');
        gc_enable(); // 启用垃圾回收

        // 优化查询：使用with预加载plan关系，避免N+1问题
        $query = User::with('plan:id,name')
            ->orderBy('id', 'asc')
            ->select([
                'email',
                'balance',
                'commission_balance',
                'transfer_enable',
                'u',
                'd',
                'expired_at',
                'token',
                'plan_id'
            ]);

        $this->applyFiltersAndSorts($request, $query);

        $filename = 'users_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            // 打开输出流
            $output = fopen('php://output', 'w');

            // 添加BOM标记，确保Excel正确显示中文
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // 写入CSV头部
            fputcsv($output, [
                '邮箱',
                '余额',
                '推广佣金',
                '总流量',
                '剩余流量',
                '套餐到期时间',
                '订阅计划',
                '订阅地址'
            ]);

            // 分批处理数据以减少内存使用
            $query->chunk(500, function ($users) use ($output) {
                foreach ($users as $user) {
                    try {
                        $row = [
                            $user->email,
                            number_format($user->balance / 100, 2),
                            number_format($user->commission_balance / 100, 2),
                            Helper::trafficConvert($user->transfer_enable),
                            Helper::trafficConvert($user->transfer_enable - ($user->u + $user->d)),
                            $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : '长期有效',
                            $user->plan ? $user->plan->name : '无订阅',
                            Helper::getSubscribeUrl($user->token)
                        ];
                        fputcsv($output, $row);
                    } catch (\Exception $e) {
                        Log::error('CSV导出错误: ' . $e->getMessage(), [
                            'user_id' => $user->id,
                            'email' => $user->email
                        ]);
                        continue; // 继续处理下一条记录
                    }
                }

                // 清理内存
                gc_collect_cycles();
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    public function generate(UserGenerate $request)
    {
        if ($request->input('email_prefix')) {
            $email = $request->input('email_prefix') . '@' . $request->input('email_suffix');

            if (User::where('email', $email)->exists()) {
                return $this->fail([400201, '邮箱已存在于系统中']);
            }

            $userService = app(UserService::class);
            $user = $userService->createUser([
                'email' => $email,
                'password' => $request->input('password') ?? $email,
                'plan_id' => $request->input('plan_id'),
                'expired_at' => $request->input('expired_at'),
            ]);

            if (!$user->save()) {
                return $this->fail([500, '生成失败']);
            }
            return $this->success(true);
        }

        if ($request->input('generate_count')) {
            return $this->multiGenerate($request);
        }
    }

    private function multiGenerate(Request $request)
    {
        $userService = app(UserService::class);
        $usersData = [];

        for ($i = 0; $i < $request->input('generate_count'); $i++) {
            $email = Helper::randomChar(6) . '@' . $request->input('email_suffix');
            $usersData[] = [
                'email' => $email,
                'password' => $request->input('password') ?? $email,
                'plan_id' => $request->input('plan_id'),
                'expired_at' => $request->input('expired_at'),
            ];
        }



        try {
            DB::beginTransaction();
            $users = [];
            foreach ($usersData as $userData) {
                $user = $userService->createUser($userData);
                $user->save();
                $users[] = $user;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail([500, '生成失败']);
        }

        // 判断是否导出 CSV
        if ($request->input('download_csv')) {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="users.csv"',
            ];
            $callback = function () use ($users, $request) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['账号', '密码', '过期时间', 'UUID', '创建时间', '订阅地址']);
                foreach ($users as $user) {
                    $user = $user->refresh();
                    $expireDate = $user['expired_at'] === NULL ? '长期有效' : date('Y-m-d H:i:s', $user['expired_at']);
                    $createDate = date('Y-m-d H:i:s', $user['created_at']);
                    $password = $request->input('password') ?? $user['email'];
                    $subscribeUrl = Helper::getSubscribeUrl($user['token']);
                    fputcsv($handle, [$user['email'], $password, $expireDate, $user['uuid'], $createDate, $subscribeUrl]);
                }
                fclose($handle);
            };
            return response()->streamDownload($callback, 'users.csv', $headers);
        }

        // 默认返回 JSON
        $data = collect($users)->map(function ($user) use ($request) {
            return [
                'email' => $user['email'],
                'password' => $request->input('password') ?? $user['email'],
                'expired_at' => $user['expired_at'] === NULL ? '长期有效' : date('Y-m-d H:i:s', $user['expired_at']),
                'uuid' => $user['uuid'],
                'created_at' => date('Y-m-d H:i:s', $user['created_at']),
                'subscribe_url' => Helper::getSubscribeUrl($user['token']),
            ];
        });
        return response()->json([
            'code' => 0,
            'message' => '批量生成成功',
            'data' => $data,
        ]);
    }

    public function sendMail(UserSendMail $request)
    {
        ini_set('memory_limit', '-1');
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->applyFiltersAndSorts($request, $builder);

        $subject = $request->input('subject');
        $content = $request->input('content');
        $templateValue = [
            'name' => admin_setting('app_name', 'XBoard'),
            'url' => admin_setting('app_url'),
            'content' => $content
        ];

        $chunkSize = 1000;

        $builder->chunk($chunkSize, function ($users) use ($subject, $templateValue, &$totalProcessed) {
            foreach ($users as $user) {
                dispatch(new SendEmailJob([
                    'email' => $user->email,
                    'subject' => $subject,
                    'template_name' => 'notify',
                    'template_value' => $templateValue
                ], 'send_email_mass'));
            }
        });

        return $this->success(true);
    }

    public function ban(Request $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->applyFilters($request, $builder);
        try {
            $builder->update([
                'banned' => 1
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '处理失败']);
        }

        return $this->success(true);
    }

    /**
     * 删除用户及其关联数据
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:App\Models\User,id'
        ], [
            'id.required' => '用户ID不能为空',
            'id.exists' => '用户不存在'
        ]);
        $user = User::find($request->input('id'));
        try {
            DB::beginTransaction();
            $user->orders()->delete();
            $user->codes()->delete();
            $user->stat()->delete();
            $user->tickets()->delete();
            $user->delete();
            DB::commit();
            return $this->success(true);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->fail([500, '删除失败']);
        }
    }
}
