<?php

namespace App\Http\Controllers\V1\Staff;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserSendMail;
use App\Http\Requests\Staff\UserUpdate;
use App\Jobs\SendEmailJob;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getUserInfoById(Request $request)
    {
        if (empty($request->input('id'))) {
            return $this->fail([422,'用户ID不能为空']);
        }
        $user = User::where('is_admin', 0)
            ->where('id', $request->input('id'))
            ->where('is_staff', 0)
            ->first();
        if (!$user) return $this->fail([400202,'用户不存在']);
        return $this->success($user);
    }

    public function update(UserUpdate $request)
    {
        $params = $request->validated();
        $user = User::find($request->input('id'));
        if (!$user) {
            return $this->fail([400202,'用户不存在']);
        }
        if (User::where('email', $params['email'])->first() && $user->email !== $params['email']) {
            return $this->fail([400201,'邮箱已被使用']);
        }
        if (isset($params['password'])) {
            $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
            $params['password_algo'] = NULL;
        } else {
            unset($params['password']);
        }
        if (isset($params['plan_id'])) {
            $plan = Plan::find($params['plan_id']);
            if (!$plan) {
                return $this->fail([400202,'订阅不存在']);
            }
            $params['group_id'] = $plan->group_id;
        }

        try {
            $user->update($params);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500,'更新失败']);
        }
        return $this->success(true);
    }

    public function sendMail(UserSendMail $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->filter($request, $builder);
        $users = $builder->get();
        foreach ($users as $user) {
            SendEmailJob::dispatch([
                'email' => $user->email,
                'subject' => $request->input('subject'),
                'template_name' => 'notify',
                'template_value' => [
                    'name' => admin_setting('app_name', 'XBoard'),
                    'url' => admin_setting('app_url'),
                    'content' => $request->input('content')
                ]
            ]);
        }

        return $this->success(true);
    }

    public function ban(Request $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->filter($request, $builder);
        try {
            $builder->update([
                'banned' => 1
            ]);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500,'处理上失败']);
        }

        return $this->success(true);
    }
}
