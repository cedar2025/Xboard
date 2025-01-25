<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ThemeController extends Controller
{
    private $themeService;

    public function __construct(ThemeService $themeService)
    {
        $this->themeService = $themeService;
    }

    /**
     * 上传新主题
     * 
     * @throws ApiException
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:zip',
                'max:10240', // 最大10MB
            ]
        ], [
            'file.required' => '请选择主题包文件',
            'file.file' => '无效的文件类型',
            'file.mimes' => '主题包必须是zip格式',
            'file.max' => '主题包大小不能超过10MB'
        ]);

        try {
            // 检查上传目录权限
            $uploadPath = storage_path('tmp');
            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }

            if (!is_writable($uploadPath)) {
                throw new ApiException('上传目录无写入权限');
            }

            // 检查主题目录权限
            $themePath = base_path('theme');
            if (!is_writable($themePath)) {
                throw new ApiException('主题目录无写入权限');
            }

            $file = $request->file('file');

            // 检查文件MIME类型
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, ['application/zip', 'application/x-zip-compressed'])) {
                throw new ApiException('无效的文件类型，仅支持ZIP格式');
            }

            // 检查文件名安全性
            $originalName = $file->getClientOriginalName();
            if (!preg_match('/^[a-zA-Z0-9\-\_\.]+\.zip$/', $originalName)) {
                throw new ApiException('主题包文件名只能包含字母、数字、下划线、中划线和点');
            }

            $this->themeService->upload($file);
            return $this->success(true);

        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Theme upload failed', [
                'error' => $e->getMessage(),
                'file' => $request->file('file')?->getClientOriginalName()
            ]);
            throw new ApiException('主题上传失败：' . $e->getMessage());
        }
    }

    /**
     * 删除主题
     */
    public function delete(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required'
        ]);
        $this->themeService->delete($payload['name']);
        return $this->success(true);
    }

    /**
     * 获取所有主题和其配置列
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getThemes()
    {
        $data = [
            'themes' => $this->themeService->getList(),
            'active' => admin_setting('frontend_theme', 'Xboard')
        ];
        return $this->success($data);
    }

    /**
     * 切换主题
     */
    public function switchTheme(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required'
        ]);
        $this->themeService->switch($payload['name']);
        return $this->success(true);
    }

    /**
     * 获取主题配置
     */
    public function getThemeConfig(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required'
        ]);
        $data = $this->themeService->getConfig($payload['name']);
        return $this->success($data);
    }

    /**
     * 保存主题配置
     */
    public function saveThemeConfig(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required',
            'config' => 'required'
        ]);
        $this->themeService->updateConfig($payload['name'], $payload['config']);
        $config = $this->themeService->getConfig($payload['name']);
        return $this->success($config);
    }
}
