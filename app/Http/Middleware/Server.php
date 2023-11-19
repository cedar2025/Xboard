<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Server
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // alias
        $aliasTypes = [
            'v2ray' => 'vmess',
            'hysteria2' => 'hysteria'
        ];
        $request->validate([
            'token' => ['required','string',function ($attribute, $value, $fail) {
                if ($value != admin_setting('server_token')) {
                    $fail("The $attribute is error.");
                }
            }],
            'node_id' => 'required',
            'node_type' => [
                'nullable',
                'string',
                'regex:/^(?i)(hysteria|hysteria2|vless|trojan|vmess|v2ray|tuic|shadowsocks|shadowsocks-plugin)$/',
                function ($attribute, $value, $fail)use($aliasTypes) {
                    // 将值转换为小写
                    request()->merge([$attribute => strtolower($value)]);
                    // 类别别名
                    if (in_array($value, array_keys($aliasTypes))){
                        request()->merge([$attribute =>  $aliasTypes[$value]]);
                    }
                },
            ]
        ]);
        
        return $next($request);
    }
}
