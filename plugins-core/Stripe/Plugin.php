<?php

namespace Plugin\Stripe;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Services\Plugin\AbstractPlugin;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    private const SUPPORTED_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];

    public function boot(): void
    {
        $this->filter('available_payment_methods', function (array $methods): array {
            if ($this->getConfig('enabled', true)) {
                $methods['Stripe'] = [
                    'name' => $this->getConfig('display_name', 'Stripe'),
                    'icon' => $this->getConfig('icon', '💳'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin',
                ];
            }

            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'stripe_secret_key' => [
                'label' => 'Secret Key',
                'type' => 'string',
                'required' => true,
                'placeholder' => 'sk_live_...',
                'description' => 'Stripe 后台 Developers > API keys 中的 Secret key',
            ],
            'stripe_webhook_secret' => [
                'label' => 'Webhook Secret',
                'type' => 'string',
                'required' => true,
                'placeholder' => '先不填并保存配置，然后去Stripe创建Webhook端点，获得密钥再填入',
                'description' => 'Stripe Webhook Endpoint 签名密钥',
            ],
            'stripe_currency' => [
                'label' => '币种',
                'type' => 'string',
                'required' => true,
                'default' => 'CNY',
                'placeholder' => 'CNY',
                'description' => 'ISO 4217 三位币种代码；订单金额会按 Stripe 最小货币单位提交',
            ],
            'stripe_product_name' => [
                'label' => '商品名称',
                'type' => 'string',
                'default' => 'Xboard Subscription',
                'description' => 'Stripe Checkout 页面显示的商品名称',
            ],
            'payment_method_types' => [
                'label' => 'payment_method_types',
                'type' => 'select',
                'required' => true,
                'default' => 'automatic',
                'description' => 'Stripe Checkout 支付方式；automatic 表示由 Stripe Dashboard 自动选择',
                'options' => [
                    ['label' => 'Stripe 自动选择（推荐）', 'value' => 'automatic'],
                    ['label' => '银行卡', 'value' => 'card'],
                    ['label' => '银行卡 + 支付宝', 'value' => 'card,alipay'],
                    ['label' => '银行卡 + 微信支付', 'value' => 'card,wechat_pay'],
                    ['label' => '银行卡 + 支付宝 + 微信支付', 'value' => 'card,alipay,wechat_pay'],
                    ['label' => '自定义', 'value' => 'custom'],
                ],
            ],
            'custom_payment_method_types' => [
                'label' => 'custom payment_method_types',
                'type' => 'string',
                'default' => 'card,alipay,wechat_pay',
                'placeholder' => 'card,alipay,wechat_pay',
                'description' => '仅在选择“自定义”时生效，填写 Stripe payment method type，使用英文逗号分隔',
            ],
        ];
    }

    public function pay($order): array
    {
        $secretKey = trim((string) $this->getConfig('stripe_secret_key'));
        if ($secretKey === '') {
            throw new ApiException('Stripe Secret Key 未配置');
        }

        $currency = $this->currency();
        $tradeNo = (string) ($order['trade_no'] ?? '');
        $amount = (int) ($order['total_amount'] ?? 0);

        if ($tradeNo === '' || $amount <= 0) {
            throw new ApiException('Stripe 订单号或金额无效');
        }

        try {
            $stripe = new StripeClient($secretKey);
            $sessionParams = [
                'mode' => 'payment',
                'client_reference_id' => $tradeNo,
                'success_url' => $order['return_url'],
                'cancel_url' => $order['return_url'],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amount,
                        'product_data' => [
                            'name' => $this->getConfig('stripe_product_name', 'Xboard Subscription'),
                            'description' => 'order: ' . $tradeNo,
                        ],
                    ],
                ]],
                'metadata' => [
                    'xboard_trade_no' => $tradeNo,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'xboard_trade_no' => $tradeNo,
                    ],
                ],
            ];

            $paymentMethods = $this->paymentMethods();
            if ($paymentMethods !== null) {
                $sessionParams['payment_method_types'] = $paymentMethods;

                if (in_array('wechat_pay', $paymentMethods, true)) {
                    $sessionParams['payment_method_options'] = [
                        'wechat_pay' => [
                            'client' => 'web',
                        ],
                    ];
                }
            }

            $session = $stripe->checkout->sessions->create($sessionParams, [
                'idempotency_key' => 'xboard_checkout_' . $tradeNo,
            ]);
        } catch (ApiErrorException $e) {
            throw new ApiException('Stripe 创建 Checkout Session 失败：' . $e->getMessage());
        }

        if (empty($session->url)) {
            throw new ApiException('Stripe 未返回 Checkout URL');
        }

        return [
            'type' => 1,
            'data' => $session->url,
        ];
    }

    public function notify($params): array
    {
        $payload = request()->getContent();
        $signature = (string) request()->header('Stripe-Signature', '');
        $webhookSecret = trim((string) $this->getConfig('stripe_webhook_secret'));

        if ($payload === '' || $signature === '' || $webhookSecret === '') {
            throw new ApiException('Stripe Webhook 参数不完整');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (UnexpectedValueException | SignatureVerificationException $e) {
            throw new ApiException('Stripe Webhook 签名验证失败', 400);
        }

        if (!in_array($event->type, self::SUPPORTED_EVENTS, true)) {
            throw new ApiException('不支持的 Stripe Webhook 事件：' . $event->type);
        }

        $session = $event->data->object;
        if (($session->mode ?? null) !== 'payment' || ($session->payment_status ?? null) !== 'paid') {
            throw new ApiException('Stripe Checkout Session 尚未支付');
        }

        $metadataTradeNo = (string) ($session->metadata->xboard_trade_no ?? '');
        $referenceTradeNo = (string) ($session->client_reference_id ?? '');
        $tradeNo = $metadataTradeNo ?: $referenceTradeNo;

        if ($tradeNo === '' || ($metadataTradeNo !== '' && $referenceTradeNo !== '' && $metadataTradeNo !== $referenceTradeNo)) {
            throw new ApiException('Stripe Checkout Session 订单号无效');
        }

        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order) {
            throw new ApiException('Stripe 回调对应的 Xboard 订单不存在');
        }

        if ((int) $order->payment_id !== (int) $this->getConfig('id')) {
            throw new ApiException('Stripe 回调与订单支付方式不一致');
        }

        $expectedAmount = (int) $order->total_amount + (int) ($order->handling_amount ?? 0);
        if ((int) ($session->amount_total ?? -1) !== $expectedAmount) {
            throw new ApiException('Stripe 回调金额与订单金额不一致');
        }

        if (strtolower((string) ($session->currency ?? '')) !== $this->currency()) {
            throw new ApiException('Stripe 回调币种与支付配置不一致');
        }

        $paymentIntent = $session->payment_intent ?? null;
        $callbackNo = is_object($paymentIntent)
            ? (string) ($paymentIntent->id ?? '')
            : (string) ($paymentIntent ?: ($session->id ?? ''));
        if ($callbackNo === '') {
            throw new ApiException('Stripe 回调缺少支付流水号');
        }

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $callbackNo,
        ];
    }

    private function currency(): string
    {
        $currency = strtolower(trim((string) $this->getConfig('stripe_currency', 'CNY')));
        if (!preg_match('/^[a-z]{3}$/', $currency)) {
            throw new ApiException('Stripe 币种必须是 ISO 4217 三位代码');
        }

        return $currency;
    }

    private function paymentMethods(): ?array
    {
        $legacyPreset = (string) $this->getConfig('stripe_payment_method_preset', 'automatic');
        $legacyPresets = [
            'automatic' => 'automatic',
            'card' => ['card'],
            'card_alipay' => ['card', 'alipay'],
            'card_wechat_pay' => ['card', 'wechat_pay'],
            'china' => ['card', 'alipay', 'wechat_pay'],
            'custom' => 'custom',
        ];
        $configured = $this->getConfig('payment_method_types');

        if ($configured === null && array_key_exists($legacyPreset, $legacyPresets)) {
            $legacyValue = $legacyPresets[$legacyPreset];
            if (is_array($legacyValue)) {
                return $legacyValue;
            }
            $configured = $legacyValue;
        }

        $configured = trim((string) ($configured ?? 'automatic'));
        if ($configured === 'automatic') {
            return null;
        }

        $customMethods = (string) $this->getConfig(
            'custom_payment_method_types',
            $this->getConfig('stripe_custom_payment_methods', '')
        );
        $methodList = $configured === 'custom' ? $customMethods : $configured;
        $methods = array_values(array_unique(array_filter(array_map(
            static fn (string $method): string => strtolower(trim($method)),
            explode(',', $methodList)
        ))));

        if ($methods === [] || count($methods) > 10) {
            throw new ApiException('Stripe 自定义支付方式需填写 1 至 10 项');
        }

        foreach ($methods as $method) {
            if (!preg_match('/^[a-z0-9_]+$/', $method)) {
                throw new ApiException('Stripe 自定义支付方式格式无效：' . $method);
            }
        }

        return $methods;
    }
}
