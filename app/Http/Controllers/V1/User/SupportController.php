<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    private const DEFAULT_DIFY_TOKEN = 'LnMUVn4RJRDzxGFm';
    private const DEFAULT_DIFY_BASE_URL = 'https://ai.443ds443.com';

    public function difyContext(Request $request)
    {
        if (!filter_var(admin_setting('dify_support_enabled', env('DIFY_SUPPORT_ENABLED', true)), FILTER_VALIDATE_BOOLEAN)) {
            return $this->fail([403, 'AI support is not enabled']);
        }

        $baseUrl = rtrim(admin_setting('dify_support_base_url', env('DIFY_SUPPORT_BASE_URL', self::DEFAULT_DIFY_BASE_URL)), '/');
        $token = admin_setting('dify_support_token', env('DIFY_SUPPORT_TOKEN', self::DEFAULT_DIFY_TOKEN));

        return $this->success([
            'token' => $token,
            'base_url' => $baseUrl,
            'embed_script_url' => $baseUrl . '/embed.min.js',
            'user_id' => (string) $request->user()->id,
            'user_display_name' => $request->user()->email,
        ]);
    }
}
