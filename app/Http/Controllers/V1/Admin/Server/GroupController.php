<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\ServerGroup;
use App\Models\ServerVmess;
use App\Models\User;
use App\Services\ServerService;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function fetch(Request $request)
    {
        if ($request->input('group_id')) {
            return $this->success([ServerGroup::find($request->input('group_id'))]);
        }
        $serverGroups = ServerGroup::get();
        $servers = ServerService::getAllServers();
        foreach ($serverGroups as $k => $v) {
            $serverGroups[$k]['user_count'] = User::where('group_id', $v['id'])->count();
            $serverGroups[$k]['server_count'] = 0;
            foreach ($servers as $server) {
                if (in_array($v['id'], $server['group_id'])) {
                    $serverGroups[$k]['server_count'] = $serverGroups[$k]['server_count']+1;
                }
            }
        }
        return $this->success($serverGroups);
    }

    public function save(Request $request)
    {
        if (empty($request->input('name'))) {
            return $this->fail([422,'组名不能为空']);
        }

        if ($request->input('id')) {
            $serverGroup = ServerGroup::find($request->input('id'));
        } else {
            $serverGroup = new ServerGroup();
        }

        $serverGroup->name = $request->input('name');
        return $this->success($serverGroup->save());
    }

    public function drop(Request $request)
    {
        if ($request->input('id')) {
            $serverGroup = ServerGroup::find($request->input('id'));
            if (!$serverGroup) {
                return $this->fail([400202,'组不存在']);
            }
        }

        $servers = ServerVmess::all();
        foreach ($servers as $server) {
            if (in_array($request->input('id'), $server->group_id)) {
                return $this->fail([400,'该组已被节点所使用，无法删除']);
            }
        }

        if (Plan::where('group_id', $request->input('id'))->first()) {
            return $this->fail([400, '该组已被订阅所使用，无法删除']);
        }
        if (User::where('group_id', $request->input('id'))->first()) {
            return $this->fail([400, '该组已被用户所使用，无法删除']);
        }
        return $this->success($serverGroup->delete());
    }
}
