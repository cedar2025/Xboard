<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServerShadowsocksSave;
use App\Http\Requests\Admin\ServerShadowsocksUpdate;
use App\Models\ServerShadowsocks;
use Illuminate\Http\Request;

class ShadowsocksController extends Controller
{
    public function save(ServerShadowsocksSave $request)
    {
        $params = $request->validated();
        if ($request->input('id')) {
            $server = ServerShadowsocks::find($request->input('id'));
            if (!$server) {
                return $this->fail([400202, '服务器不存在']);
            }
            try {
                $server->update($params);
                return $this->success(true);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500,'保存失败']);
            }
        }

        try{
            ServerShadowsocks::create($params);
            return $this->success(true);
        }catch(\Exception $e){
            \Log::error($e);
            return $this->fail([500,'创建失败']);
        }

        
    }

    public function drop(Request $request)
    {
        if ($request->input('id')) {
            $server = ServerShadowsocks::find($request->input('id'));
            if (!$server) {
                return $this->fail([400202, '节点不存在']);
            }
        }
        return $this->success($server->delete());
    }

    public function update(ServerShadowsocksUpdate $request)
    {
        $params = $request->only([
            'show',
        ]);

        $server = ServerShadowsocks::find($request->input('id'));

        if (!$server) {
            return $this->fail([400202, '该服务器不存在']);
        }
        try {
            $server->update($params);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500,'保存失败']);
        }

        return $this->success(true);
    }

    public function copy(Request $request)
    {
        $server = ServerShadowsocks::find($request->input('id'));
        $server->show = 0;
        if (!$server) {
            return $this->fail([400202,'服务器不存在']);
        }
        ServerShadowsocks::create($server->toArray());
        return $this->success(true);
    }
}
