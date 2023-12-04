<?php

namespace App\Http\Controllers\V1\Staff;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NoticeSave;
use App\Models\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function fetch(Request $request)
    {
        return response([
            'data' => Notice::orderBy('id', 'DESC')->get()
        ]);
    }

    public function save(NoticeSave $request)
    {
        $data = $request->only([
            'title',
            'content',
            'img_url'
        ]);
        if (!$request->input('id')) {
            if (!Notice::create($data)) {
                throw new ApiException(500, '保存失败');
            }
        } else {
            try {
                Notice::find($request->input('id'))->update($data);
            } catch (\Exception $e) {
                throw new ApiException(500, '保存失败');
            }
        }
        return response([
            'data' => true
        ]);
    }

    public function drop(Request $request)
    {
        if (empty($request->input('id'))) {
            throw new ApiException(422, '参数错误');
        }
        $notice = Notice::find($request->input('id'));
        if (!$notice) {
            throw new ApiException(500, '公告不存在');
        }
        if (!$notice->delete()) {
            throw new ApiException(500, '删除失败');
        }
        return response([
            'data' => true
        ]);
    }
}
