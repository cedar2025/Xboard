<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeSave;
use App\Http\Requests\Admin\KnowledgeSort;
use App\Models\Knowledge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KnowledgeController extends Controller
{
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            $knowledge = Knowledge::find($request->input('id'))->toArray();
            if (!$knowledge)
                return $this->fail([400202, '知识不存在']);
            if (isset($knowledge['body'])) {
                $knowledge['body'] = $this->normalizeKnowledgeImageUrls($knowledge['body']);
            }
            return $this->success($knowledge);
        }
        $data = Knowledge::select(['title', 'id', 'updated_at', 'category', 'show'])
            ->orderBy('sort', 'ASC')
            ->get();
        return $this->success($data);
    }

    public function getCategory(Request $request)
    {
        return $this->success(array_keys(Knowledge::get()->groupBy('category')->toArray()));
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:5120',
            ],
        ], [
            'file.required' => '请选择图片文件',
            'file.file' => '无效的文件类型',
            'file.image' => '上传文件必须是图片',
            'file.mimes' => '图片仅支持 jpg、jpeg、png、gif、webp 格式',
            'file.max' => '图片大小不能超过5MB',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $directory = 'knowledge-images/' . now()->format('Y/m');
        $filename = Str::uuid() . '.' . $extension;

        $disk = Storage::disk('public');
        $disk->makeDirectory($directory);
        $path = $disk->putFileAs($directory, $file, $filename);
        if (!$path) {
            throw new ApiException('图片上传失败');
        }

        $publicPath = ltrim(substr(ltrim($path, '/'), strlen('knowledge-images/')), '/');

        return $this->success([
            'url' => '/knowledge-images/' . $publicPath,
        ]);
    }

    public function save(KnowledgeSave $request)
    {
        $params = $request->validated();
        $params['body'] = $this->normalizeKnowledgeImageUrls($params['body']);

        if (!$request->input('id')) {
            if (!Knowledge::create($params)) {
                return $this->fail([500, '创建失败']);
            }
        } else {
            try {
                Knowledge::find($request->input('id'))->update($params);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500, '创建失败']);
            }
        }

        return $this->success(true);
    }

    public function show(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => '知识库ID不能为空'
        ]);
        $knowledge = Knowledge::find($request->input('id'));
        if (!$knowledge) {
            throw new ApiException('知识不存在');
        }
        $knowledge->show = !$knowledge->show;
        if (!$knowledge->save()) {
            throw new ApiException('保存失败');
        }

        return $this->success(true);
    }

    public function sort(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ], [
            'ids.required' => '参数有误',
            'ids.array' => '参数有误'
        ]);
        try {
            DB::beginTransaction();
            foreach ($request->input('ids') as $k => $v) {
                $knowledge = Knowledge::find($v);
                $knowledge->timestamps = false;
                $knowledge->update(['sort' => $k + 1]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException('保存失败');
        }
        return $this->success(true);
    }

    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => '知识库ID不能为空'
        ]);
        $knowledge = Knowledge::find($request->input('id'));
        if (!$knowledge) {
            return $this->fail([400202, '知识不存在']);
        }
        if (!$knowledge->delete()) {
            return $this->fail([500, '删除失败']);
        }

        return $this->success(true);
    }

    private function normalizeKnowledgeImageUrls(string $body): string
    {
        return str_replace('/storage/knowledge-images/', '/knowledge-images/', $body);
    }
}
