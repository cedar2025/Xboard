<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServerTrojanSave;
use App\Http\Requests\Admin\ServerTrojanUpdate;
use App\Models\ServerTrojan;
use Illuminate\Http\Request;

class TrojanController extends Controller
{
    public function save(ServerTrojanSave $request)
    {
        $params = $request->validated();
        if ($request->input('id')) {
            $server = ServerTrojan::find($request->input('id'));
            if (!$server) {
                return $this->fail([400202,'服务器不存在']);
            }
            try {
                $server->update($params);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500, '保存失败']);
            }
            return $this->success(true);
        }

        ServerTrojan::create($params);
        return $this->success(true);
    }

    public function drop(Request $request)
    {
        if ($request->input('id')) {
            $server = ServerTrojan::find($request->input('id'));
            if (!$server) {
                return $this->fail([400202,'节点ID不存在']);
            }
        }
        return $this->success($server->delete());
    }

    public function update(ServerTrojanUpdate $request)
    {
        $params = $request->only([
            'show',
        ]);

        $server = ServerTrojan::find($request->input('id'));

        if (!$server) {
            return $this->fail([400202,'该服务器不存在']);
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
        $server = ServerTrojan::find($request->input('id'));
        $server->show = 0;
        if (!$server) {
            return $this->fail([400202,'服务器不存在']);
        }
        ServerTrojan::create($server->toArray());
        return $this->success(true);
    }
}
