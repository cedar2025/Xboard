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
        return $this->success(ServerService::getAllServers());
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
                    $model::where('id', $id)->update(['sort' => $sort]);
                }
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            \Log::error($e);
            return $this->fail([500,'保存失败']);
        
        }
        return $this->success(true);
    }
}
