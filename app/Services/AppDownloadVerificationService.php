<?php

namespace App\Services;

use Illuminate\Http\Request;

class AppDownloadVerificationService
{
    public function settings(): array
    {
        $siteKey = admin_setting('app_download_turnstile_site_key');
        $secretKey = admin_setting('app_download_turnstile_secret_key');
        $usesGlobalFallback = !$siteKey || !$secretKey;

        return [
            'enabled' => (bool) (int) admin_setting('app_download_turnstile_enable', 1),
            'site_key' => $usesGlobalFallback ? admin_setting('turnstile_site_key', '') : $siteKey,
            'secret_key' => $usesGlobalFallback ? admin_setting('turnstile_secret_key', '') : $secretKey,
            'uses_global_fallback' => $usesGlobalFallback,
        ];
    }

    public function publicSettings(): array
    {
        $settings = $this->settings();

        return [
            'enabled' => $settings['enabled'],
            'site_key' => $settings['site_key'],
        ];
    }

    public function verify(Request $request): array
    {
        $settings = $this->settings();
        if (!$settings['enabled']) {
            return [true, null];
        }

        if (!$settings['secret_key']) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        return app(CaptchaService::class)->verifyTurnstileChallenge($request, $settings['secret_key']);
    }
}
