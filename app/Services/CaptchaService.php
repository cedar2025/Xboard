<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReCaptcha\ReCaptcha;
use Throwable;

class CaptchaService
{
    /**
     * 验证人机验证码
     *
     * @param Request $request 请求对象
     * @return array [是否通过, 错误消息]
     */
    public function verify(Request $request): array
    {
        if (!(int) admin_setting('captcha_enable', 0)) {
            return [true, null];
        }

        $captchaType = admin_setting('captcha_type', 'recaptcha');

        return match ($captchaType) {
            'turnstile' => $this->verifyTurnstile($request),
            'recaptcha-v3' => $this->verifyRecaptchaV3($request),
            'recaptcha' => $this->verifyRecaptcha($request),
            default => [false, [400, __('Invalid captcha type')]]
        };
    }

    /**
     * 验证 Cloudflare Turnstile
     *
     * @param Request $request
     * @return array
     */
    private function verifyTurnstile(Request $request): array
    {
        $turnstileToken = $request->input('turnstile_token')
            ?: $request->input('cf-turnstile-response');
        if (!$turnstileToken) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $secretKey = admin_setting('turnstile_secret_key');
        if (!$secretKey) {
            Log::warning('Turnstile verification skipped because secret key is missing');
            return [false, [400, __('Invalid code is incorrect')]];
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secretKey,
                    'response' => $turnstileToken,
                    'remoteip' => $request->ip()
                ]);
        } catch (Throwable $e) {
            Log::warning('Turnstile verification request failed', [
                'error' => $e->getMessage(),
                'host' => $request->getHost()
            ]);
            return [false, [400, __('Invalid code is incorrect')]];
        }

        if (!$response->ok()) {
            Log::warning('Turnstile verification returned non-OK response', [
                'status' => $response->status(),
                'host' => $request->getHost()
            ]);
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $result = $response->json();
        if (!is_array($result) || empty($result['success'])) {
            Log::info('Turnstile verification rejected token', [
                'host' => $request->getHost(),
                'error_codes' => $result['error-codes'] ?? []
            ]);
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $hostname = strtolower((string) ($result['hostname'] ?? ''));
        if (!$hostname || !in_array($hostname, $this->allowedTurnstileHosts($request), true)) {
            Log::warning('Turnstile verification rejected hostname', [
                'hostname' => $hostname,
                'request_host' => $request->getHost()
            ]);
            return [false, [400, __('Invalid code is incorrect')]];
        }

        return [true, null];
    }

    private function allowedTurnstileHosts(Request $request): array
    {
        $hosts = [$request->getHost()];
        $appUrl = admin_setting('app_url');
        if ($appUrl) {
            $hosts[] = parse_url($appUrl, PHP_URL_HOST);
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($host) => strtolower((string) $host),
            $hosts
        ))));
    }

    /**
     * 验证 Google reCAPTCHA v3
     *
     * @param Request $request
     * @return array
     */
    private function verifyRecaptchaV3(Request $request): array
    {
        $recaptchaV3Token = $request->input('recaptcha_v3_token');
        if (!$recaptchaV3Token) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $recaptcha = new ReCaptcha(admin_setting('recaptcha_v3_secret_key'));
        $recaptchaResp = $recaptcha->verify($recaptchaV3Token, $request->ip());

        if (!$recaptchaResp->isSuccess()) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        // 检查分数阈值（如果有的话）
        $score = $recaptchaResp->getScore();
        $threshold = admin_setting('recaptcha_v3_score_threshold', 0.5);
        if ($score < $threshold) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        return [true, null];
    }

    /**
     * 验证 Google reCAPTCHA v2
     *
     * @param Request $request
     * @return array
     */
    private function verifyRecaptcha(Request $request): array
    {
        $recaptchaData = $request->input('recaptcha_data');
        if (!$recaptchaData) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        $recaptcha = new ReCaptcha(admin_setting('recaptcha_key'));
        $recaptchaResp = $recaptcha->verify($recaptchaData);

        if (!$recaptchaResp->isSuccess()) {
            return [false, [400, __('Invalid code is incorrect')]];
        }

        return [true, null];
    }
}
