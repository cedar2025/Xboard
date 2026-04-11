<?php

namespace App\Http\Controllers\V2\Admin\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServerSave;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Services\ServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManageController extends Controller
{
    public function getNodes(Request $request)
    {
        $servers = ServerService::getAllServers()->map(function ($item) {
            $item['groups'] = ServerGroup::whereIn('id', $item['group_ids'])->get(['name', 'id']);
            $item['parent'] = $item->parent;
            return $item;
        });
        return $this->success($servers);
    }

    public function sort(Request $request)
    {
        ini_set('post_max_size', '1m');
        $params = $request->validate([
            '*.id' => 'numeric',
            '*.order' => 'numeric'
        ]);

        try {
            DB::beginTransaction();
            collect($params)->each(function ($item) {
                if (isset($item['id']) && isset($item['order'])) {
                    Server::where('id', $item['id'])->update(['sort' => $item['order']]);
                }
            });
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->fail([500, '保存失败']);

        }
        return $this->success(true);
    }

    public function save(ServerSave $request)
    {
        $params = $request->validated();
        if ($request->input('id')) {
            $server = Server::find($request->input('id'));
            if (!$server) {
                return $this->fail([400202, '服务器不存在']);
            }
            try {
                $server->update($params);
                return $this->success(true);
            } catch (\Exception $e) {
                Log::error($e);
                return $this->fail([500, '保存失败']);
            }
        }

        try {
            Server::create($params);
            return $this->success(true);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '创建失败']);
        }


    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'show' => 'integer',
        ]);

        $server = Server::find($request->id);
        if (!$server) {
            return $this->fail([400202, '服务器不存在']);
        }
        $server->show = (int) $request->show;
        if (!$server->save()) {
            return $this->fail([500, '保存失败']);
        }
        return $this->success(true);
    }

    /**
     * 删除
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);
        if (Server::where('id', $request->id)->delete() === false) {
            return $this->fail([500, '删除失败']);
        }
        return $this->success(true);
    }

    /**
     * 批量删除节点
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $ids = $request->input('ids');
        if (empty($ids)) {
            return $this->fail([400, '请选择要删除的节点']);
        }

        try {
            $deleted = Server::whereIn('id', $ids)->delete();
            if ($deleted === false) {
                return $this->fail([500, '批量删除失败']);
            }
            return $this->success(true);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '批量删除失败']);
        }
    }

    /**
     * 重置节点流量
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetTraffic(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $server = Server::find($request->id);
        if (!$server) {
            return $this->fail([400202, '服务器不存在']);
        }

        try {
            $server->u = 0;
            $server->d = 0;
            $server->save();
            
            Log::info("Server {$server->id} ({$server->name}) traffic reset by admin");
            return $this->success(true);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '重置失败']);
        }
    }

    /**
     * 批量重置节点流量
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchResetTraffic(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $ids = $request->input('ids');
        if (empty($ids)) {
            return $this->fail([400, '请选择要重置的节点']);
        }

        try {
            Server::whereIn('id', $ids)->update([
                'u' => 0,
                'd' => 0,
            ]);
            
            Log::info("Servers " . implode(',', $ids) . " traffic reset by admin");
            return $this->success(true);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '批量重置失败']);
        }
    }

    /**
     * 复制节点
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function copy(Request $request)
    {
        $server = Server::find($request->input('id'));
        if (!$server) {
            return $this->fail([400202, '服务器不存在']);
        }

        $copiedServer = $server->replicate();
        $copiedServer->show = 0;
        $copiedServer->code = null;
        $copiedServer->u = 0;
        $copiedServer->d = 0;
        $copiedServer->save();

        return $this->success(true);
    }
}
