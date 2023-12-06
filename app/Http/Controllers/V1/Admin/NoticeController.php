<?php

namespace App\Http\Controllers\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NoticeSave;
use App\Models\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function fetch(Request $request)
    {
        return $this->success(Notice::orderBy('id', 'DESC')->get());
    }

    public function save(NoticeSave $request)
    {
        $data = $request->only([
            'title',
            'content',
            'img_url',
            'tags'
        ]);
        if (!$request->input('id')) {
            if (!Notice::create($data)) {
                return $this->fail([500 ,'保存失败']);
            }
        } else {
            try {
                Notice::find($request->input('id'))->update($data);
            } catch (\Exception $e) {
                return $this->fail([500 ,'保存失败']);
            }
        }
        return $this->success(true);
    }



    public function show(Request $request)
    {
        if (empty($request->input('id'))) {
            return $this->fail([500 ,'公告ID不能为空']);
        }
        $notice = Notice::find($request->input('id'));
        if (!$notice) {
            return $this->fail([400202 ,'公告不存在']);
        }
        $notice->show = $notice->show ? 0 : 1;
        if (!$notice->save()) {
            return $this->fail([500 ,'保存失败']);
        }

        return $this->success(true);
    }

    public function drop(Request $request)
    {
        if (empty($request->input('id'))) {
            return $this->fail([422 ,'公告ID不能为空']);
        }
        $notice = Notice::find($request->input('id'));
        if (!$notice) {
            return $this->fail([400202 ,'公告不存在']);
        }
        if (!$notice->delete()) {
            return $this->fail([500 ,'删除失败']);
        }
        return $this->success(true);
    }
}
