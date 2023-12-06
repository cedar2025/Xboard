<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\ServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageController extends Controller
{
    public function getNodes(Request $request)
    {
        $serverService = new ServerService();
        return response([
            'data' => $serverService->getAllServers()
        ]);
    }

    public function sort(Request $request)
    {
        ini_set('post_max_size', '1m');
        $params = $request->only(
                'shadowsocks',
                'vmess',
                'trojan',
                'hysteria',
                'vless'
            ) ?? [];
        try{
            DB::beginTransaction();
            foreach ($params as $k => $v) {
                $model = 'App\\Models\\Server' . ucfirst($k);
                foreach($v as $id => $sort) {
                    if (!$model::find($id)->update(['sort' => $sort])) {
                        throw new ApiException(500, '保存失败');
                    }
                }
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            throw $e;
        }
        return $this->success(true);
    }
}
