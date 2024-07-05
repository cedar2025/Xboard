<?php

/**
 * 自己写别抄，抄NMB抄
 */
namespace App\Payments;
use App\Exceptions\ApiException;

class StripeALLInOne {
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'currency' => [
                'label' => '货币单位',
                'description' => '请使用符合ISO 4217标准的三位字母，例如GBP',
                'type' => 'input',
            ],
            'stripe_sk_live' => [
                'label' => 'SK_LIVE',
                'description' => '',
                'type' => 'input',
            ],
            'stripe_webhook_key' => [
                'label' => 'WebHook密钥签名',
                'description' => 'whsec_....',
                'type' => 'input',
            ],
            'description' => [
                'label' => '自定义商品介绍',
                'description' => '',
                'type' => 'input',
            ],
            'payment_method' => [
                'label' => '支付方式',
                'description' => '请输入alipay, wechat_pay, cards',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        $currency = $this->config['currency'];
        $exchange = $this->exchange('CNY', strtoupper($currency));
        if (!$exchange) {
            throw new ApiException('Currency conversion has timed out, please try again later', 500);
        }
        //jump url
        $jumpUrl = null;
        $actionType = 0;
        $stripe = new \Stripe\StripeClient($this->config['stripe_sk_live']);

        if ($this->config['payment_method'] != "cards"){
            $stripePaymentMethod = $stripe->paymentMethods->create([
                'type' => $this->config['payment_method'],
            ]);
            // 准备支付意图的基础参数
            $params = [
                'amount' => floor($order['total_amount'] * $exchange),
                'currency' => $currency,
                'confirm' => true,
                'payment_method' => $stripePaymentMethod->id,
                'automatic_payment_methods' => ['enabled' => true],
                'statement_descriptor' => 'sub-' . $order['user_id'] . '-' . substr($order['trade_no'], -8),
                'description' => $this->config['description'],
                'metadata' => [
                    'user_id' => $order['user_id'],
                    'out_trade_no' => $order['trade_no'],
                    'identifier' => ''
                ],
                'return_url' => $order['return_url']
            ];

            // 如果支付方式为 wechat_pay，添加相应的支付方式选项
            if ($this->config['payment_method'] === 'wechat_pay') {
                $params['payment_method_options'] = [
                    'wechat_pay' => [
                        'client' => 'web'
                    ],
                ];
            }
            //更新支持最新的paymentIntents方法，Sources API将在今年被彻底替
            $stripeIntents = $stripe->paymentIntents->create($params);

            $nextAction = null;

            if (!$stripeIntents['next_action']) {
                throw new ApiException(__('Payment gateway request failed'));
            }else {
                $nextAction = $stripeIntents['next_action'];
            }

            switch ($this->config['payment_method']){
                case "alipay":
                    if (isset($nextAction['alipay_handle_redirect'])){
                        $jumpUrl = $nextAction['alipay_handle_redirect']['url'];
                        $actionType = 1;
                    }else {
                        throw new ApiException('unable get Alipay redirect url', 500);
                    }
                    break;
                case "wechat_pay":
                    if (isset($nextAction['wechat_pay_display_qr_code'])){
                        $jumpUrl = $nextAction['wechat_pay_display_qr_code']['data'];
                    }else {
                        throw new ApiException('unable get WeChat Pay redirect url', 500);
                    }
            }
        } else {
            $creditCheckOut = $stripe->checkout->sessions->create([
                'success_url' => $order['return_url'],
                'client_reference_id' => $order['trade_no'],
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => floor($order['total_amount'] * $exchange),
                            'product_data' => [
                                'name' => 'sub-' . $order['user_id'] . '-' . substr($order['trade_no'], -8),
                                'description' => $this->config['description'],
                            ]
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
            ]);
            $jumpUrl = $creditCheckOut['url'];
            $actionType = 1;
        }

        return [
            'type' => $actionType,
            'data' => $jumpUrl
        ];
    }

    public function notify($params)
    {
        try {
            \Stripe\Stripe::setApiKey($this->config['stripe_sk_live']);
            //Workerman不支持使用php://input, stripe同时要求验证签名的payload不能经过修改，所以使用这个方法
            $payload = $GLOBALS['HTTP_RAW_POST_DATA'];
            $headers = getallheaders();
            $headerName = 'Stripe-Signature';
            $signatureHeader = $headers[$headerName] ?? '';
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signatureHeader,
                $this->config['stripe_webhook_key']
            );

        } catch (\UnexpectedValueException $e){
            throw new ApiException('Error parsing payload', 400);
        }
        catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new ApiException('signature not match', 400);
        }
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $object = $event->data->object;
                if ($object->status === 'succeeded') {
                    if (!isset($object->metadata->out_trade_no)) {
                        return('order error');
                    }
                    $metaData = $object->metadata;
                    $tradeNo = $metaData->out_trade_no;
                    return [
                        'trade_no' => $tradeNo,
                        'callback_no' => $object->id
                    ];
                }
                break;
            case 'checkout.session.completed':
                $object = $event->data->object;
                if ($object->payment_status === 'paid') {
                    return [
                        'trade_no' => $object->client_reference_id,
                        'callback_no' => $object->payment_intent
                    ];
                }
                break;
            case 'checkout.session.async_payment_succeeded':
                $object = $event->data->object;
                return [
                    'trade_no' => $object->client_reference_id,
                    'callback_no' => $object->payment_intent
                ];
                break;
            default:
                throw new ApiException('event is not support');
        }
        return('success');
    }

    private function exchange($from, $to)
    {
        $from = strtolower($from);
        $to = strtolower($to);
        $result = file_get_contents("https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/" . $from . ".min.json");
        $result = json_decode($result, true);
        return $result[$from][$to];
    }
}
