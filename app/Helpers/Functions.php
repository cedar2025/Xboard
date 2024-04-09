<?php
use App\Support\Setting;


if (! function_exists("get_request_content")){
    function get_request_content(){

        return request()->getContent() ?: json_encode($_POST);
        
    }
}

if (! function_exists('admin_setting')) {
    /**
     * 获取或保存配置参数.
     *
     * @param  string|array  $key
     * @param  mixed  $default
     * @return App\Support\Setting|mixed
     */
    function admin_setting($key = null, $default = null)
    {
        if ($key === null) {
            return App::make(Setting::class)->toArray();
        }

        if (is_array($key)) {
            App::make(Setting::class)->save($key);
            return '';
        }
        $default = config('v2board.'. $key) ?? $default;
        return App::make(Setting::class)->get($key) ?? $default ;
    }
}
