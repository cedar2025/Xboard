<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * 不需要加密的Cookie名称列表
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
