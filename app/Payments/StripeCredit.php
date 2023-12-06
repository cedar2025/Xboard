<?php

/**
 * 自己写别抄，抄NMB抄
 */
namespace App\Payments;

use App\Exceptions\ApiException;
use Stripe\Source;
use Stripe\Stripe;

class StripeCredit {
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'currency' => [
                'label' => '货币单位',
                'description' => '',
                'type' => 'input',
            ],
            'stripe_sk_live' => [
                'label' => 'SK_LIVE',
                'description' => '',
                'type' => 'input',
            ],
            'stripe_pk_live' => [
                'label' => 'PK_LIVE',
                'description' => '',
                'type' => 'input',
            ],
            'stripe_webhook_key' => [
                'label' => 'WebHook密钥签名',
                'description' => '',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        info($order);
        $currency = $this->config['currency'];
        $exchange = $this->exchange('CNY', strtoupper($currency));
        if (!$exchange) {
            throw new ApiException(__('Currency conversion has timed out, please try again later'));
        }
        Stripe::setApiKey($this->config['stripe_sk_live']);
        try {
            $charge = \Stripe\Charge::create([
                'amount' => floor($order['total_amount'] * $exchange),
                'currency' => $currency,
                'source' => $order['stripe_token'],
                'metadata' => [
                    'user_id' => $order['user_id'],
                    'out_trade_no' => $order['trade_no'],
                    'identifier' => ''
                ]
            ]);
        } catch (\Exception $e) {
            info($e);
            throw new ApiException(__('Payment failed. Please check your credit card information'));
        }
        if (!$charge->paid) {
            throw new ApiException(__('Payment failed. Please check your credit card information'));
        }
        return [
            'type' => 2,
            'data' => $charge->paid
        ];
    }

    public function notify($params)
    {
        \Stripe\Stripe::setApiKey($this->config['stripe_sk_live']);
        try {
            $event = \Stripe\Webhook::constructEvent(
                get_request_content(),
                request()->header('HTTP_STRIPE_SIGNATURE'),
                $this->config['stripe_webhook_key']
            );
        } catch (\Stripe\Error\SignatureVerification $e) {
            \Log::error($e);
            abort(400);
        }
        switch ($event->type) {
            case 'source.chargeable':
                $object = $event->data->object;
                \Stripe\Charge::create([
                    'amount' => $object->amount,
                    'currency' => $object->currency,
                    'source' => $object->id,
                    'metadata' => json_decode($object->metadata, true)
                ]);
                break;
            case 'charge.succeeded':
                $object = $event->data->object;
                if ($object->status === 'succeeded') {
                    if (!isset($object->metadata->out_trade_no) && !isset($object->source->metadata)) {
                        return('order error');
                    }
                    $metaData = isset($object->metadata->out_trade_no) ? $object->metadata : $object->source->metadata;
                    $tradeNo = $metaData->out_trade_no;
                    return [
                        'trade_no' => $tradeNo,
                        'callback_no' => $object->id
                    ];
                }
                break;
            default:
                throw new ApiException('event is not support');
        }
        return('success');
    }

    private function exchange($from, $to)
    {
        $result = file_get_contents('https://api.exchangerate.host/latest?symbols=' . $to . '&base=' . $from);
        $result = json_decode($result, true);
        return $result['rates'][$to];
    }
}
