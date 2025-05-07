<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * 是否在响应中设置XSRF-TOKEN cookie
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * 不需要CSRF验证的URI列表
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
