<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\ServerRoute;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function fetch(Request $request)
    {
        $routes = ServerRoute::get();
        // TODO: remove on 1.8.0
        foreach ($routes as $k => $route) {
            $array = json_decode($route->match, true);
            if (is_array($array)) $routes[$k]['match'] = $array;
        }
        // TODO: remove on 1.8.0
        return [
            'data' => $routes
        ];
    }

    public function save(Request $request)
    {
        $params = $request->validate([
            'remarks' => 'required',
            'match' => 'required|array',
            'action' => 'required|in:block,dns',
            'action_value' => 'nullable'
        ], [
            'remarks.required' => '备注不能为空',
            'match.required' => '匹配值不能为空',
            'action.required' => '动作类型不能为空',
            'action.in' => '动作类型参数有误'
        ]);
        $params['match'] = array_filter($params['match']);
        // TODO: remove on 1.8.0
        $params['match'] = json_encode($params['match']);
        // TODO: remove on 1.8.0
        if ($request->input('id')) {
            try {
                $route = ServerRoute::find($request->input('id'));
                $route->update($params);
                return $this->success(true);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500,'保存失败']);
            }
        }
        try{
            ServerRoute::create($params);
            return $this->success(true);
        }catch(\Exception $e){
            \Log::error($e);
            return $this->fail([500,'创建失败']);
        }
    }

    public function drop(Request $request)
    {
        $route = ServerRoute::find($request->input('id'));
        if (!$route) throw new ApiException('路由不存在');
        if (!$route->delete()) throw new ApiException('删除失败');
        return [
            'data' => true
        ];
    }
}
