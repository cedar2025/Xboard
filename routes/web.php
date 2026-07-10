<?php

use App\Services\ThemeService;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

$serveKnowledgeImage = function (string $path) {
    $path = ltrim($path, '/');
    if ($path === '' || str_contains($path, '..') || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
        abort(404);
    }

    $storagePath = 'knowledge-images/' . $path;
    $disk = Storage::disk('public');
    if (!$disk->exists($storagePath)) {
        abort(404);
    }

    return response($disk->get($storagePath), 200, [
        'Content-Type' => $disk->mimeType($storagePath) ?: 'application/octet-stream',
        'Cache-Control' => 'public, max-age=31536000, immutable',
        'Content-Disposition' => 'inline; filename="' . basename($storagePath) . '"',
    ]);
};

Route::get('/knowledge-images/{path}', $serveKnowledgeImage)->where('path', '.*');
Route::get('/storage/knowledge-images/{path}', $serveKnowledgeImage)->where('path', '.*');

$isAllowedAppHost = function (Request $request): bool {
    if (!admin_setting('app_url') || !admin_setting('safe_mode_enable', 0)) {
        return true;
    }

    $allowedHosts = [];
    $primaryHost = parse_url(admin_setting('app_url'), PHP_URL_HOST);
    if ($primaryHost) {
        $allowedHosts[] = strtolower($primaryHost);
    }

    $aliases = admin_setting('app_url_aliases', []);
    if (is_string($aliases)) {
        $aliases = preg_split('/[\s,]+/', $aliases, -1, PREG_SPLIT_NO_EMPTY);
    }

    if (is_array($aliases)) {
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '') {
                continue;
            }

            $host = parse_url($alias, PHP_URL_HOST) ?: $alias;
            $allowedHosts[] = strtolower($host);
        }
    }

    return in_array(strtolower($request->getHost()), array_unique($allowedHosts), true);
};

$redirectToLogin = function (Request $request) use ($isAllowedAppHost) {
    // 检查管理员安全模式设置，保持与 /app 相同的 Host 保护。
    if (!$isAllowedAppHost($request)) {
        abort(403);
    }

    return redirect('/app#/login', 302);
};

// Root path - redirect to the SPA login page.
Route::get('/', $redirectToLogin);

// Legacy landing page path - no longer serves the landing page.
Route::get('/welcome', $redirectToLogin);

Route::get('/support/ai', function () {
    return view('support_ai', [
        'title' => admin_setting('app_name', 'XBoard') . ' AI 客服',
    ]);
});



// Dashboard/App Route - for SPA functionality (login, register, dashboard)
Route::get('/app', function (Request $request) use ($isAllowedAppHost) {
    // Original dashboard logic
    if (!$isAllowedAppHost($request)) {
        abort(403);
    }

    $theme = admin_setting('frontend_theme', 'Xboard');
    $themeService = new ThemeService();

    try {
        if (!$themeService->exists($theme)) {
            if ($theme !== 'Xboard') {
                Log::warning('Theme not found, switching to default theme', ['theme' => $theme]);
                $theme = 'Xboard';
                admin_setting(['frontend_theme' => $theme]);
            }
            $themeService->switch($theme);
        }

        if (!$themeService->getThemeViewPath($theme)) {
            throw new Exception('主题视图文件不存在');
        }

        $publicThemePath = public_path('theme/' . $theme);
        if (!File::exists($publicThemePath)) {
            $themePath = $themeService->getThemePath($theme);
            if (!$themePath || !File::copyDirectory($themePath, $publicThemePath)) {
                throw new Exception('主题初始化失败');
            }
            Log::info('Theme initialized in public directory', ['theme' => $theme]);
        }

        $renderParams = [
            'title' => admin_setting('app_name', 'Xboard'),
            'theme' => $theme,
            'version' => app(UpdateService::class)->getCurrentVersion(),
            'description' => admin_setting('app_description', 'Xboard is best'),
            'logo' => admin_setting('logo'),
            'theme_config' => $themeService->getConfig($theme)
        ];
        return view('theme::' . $theme . '.dashboard', $renderParams);
    } catch (Exception $e) {
        Log::error('Theme rendering failed', [
            'theme' => $theme,
            'error' => $e->getMessage()
        ]);
        abort(500, '主题加载失败');
    }
});


//TODO:: 兼容
Route::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))), function () {
    return view('admin', [
        'title' => admin_setting('app_name', 'XBoard'),
        'theme_sidebar' => admin_setting('frontend_theme_sidebar', 'light'),
        'theme_header' => admin_setting('frontend_theme_header', 'dark'),
        'theme_color' => admin_setting('frontend_theme_color', 'default'),
        'background_url' => admin_setting('frontend_background_url'),
        'version' => app(UpdateService::class)->getCurrentVersion(),
        'logo' => admin_setting('logo'),
        'secure_path' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))))
    ]);
});

Route::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))) . '/app-downloads', function () {
    return view('admin_app_downloads', [
        'title' => admin_setting('app_name', 'XBoard'),
        'secure_path' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))))
    ]);
});

Route::get('/' . (admin_setting('subscribe_path', 's')) . '/{token}', [\App\Http\Controllers\V1\Client\ClientController::class, 'subscribe'])
    ->middleware('client')
    ->name('client.subscribe');
