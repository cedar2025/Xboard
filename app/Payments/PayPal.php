<?php

namespace App\Payments;

use GuzzleHttp\Client;

class PayPal
{
    private $config;
    private $client;
    private $token;
    private $apiHost;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = new Client();
        $this->apiHost = optional($this->config)['mode'] == 'sandbox' ? "https://api.sandbox.paypal.com" : "https://api.paypal.com";
    }

    public function form()
    {
        return [
            'mode' => [
                'label' => 'Mode',
                'description' => '沙箱/生产模式  sandbox/live',
                'type' => 'input',
            ],
            'client_id' => [
                'label' => 'Client ID',
                'description' => 'PayPal Client ID',
                'type' => 'input',
            ],
            'client_secret' => [
                'label' => 'Client Secret',
                'description' => 'PayPal Client Secret',
                'type' => 'input',
            ],
            'rate' => [
                'label' => '汇率',
                'description' => 'Paypal支付单位为USD，如果您站点金额单位不为USD则需要填写汇率',
                'type' => 'input',
                'default' => '2333'
            ],
        ];
    }

    public function pay($order)
    {
        $this->token = json_decode($this->client->post("{$this->apiHost}/v1/oauth2/token", [
            'auth' => [$this->config['client_id'], $this->config['client_secret']],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'form_params' => ['grant_type' => 'client_credentials']
        ])->getBody(), true)['access_token'];
        // 创建订单
        $order = json_decode($this->client->request('POST', "{$this->apiHost}/v2/checkout/orders", [
            'headers' => [
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => $order['trade_no'],
                'Authorization' => "Bearer {$this->token}"
            ],
            'json' => [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        "reference_id" => $order['trade_no'],
                        "amount" => [
                            "currency_code" => "USD",
                            "value" => number_format(ceil(($order["total_amount"] * 100) / ($this->config['rate'] ?? "1")) / 10000, 2, '.', '')
                        ]
                    ]
                ],
                "payment_source" => [
                    "paypal" => [
                        "experience_context" => [
                            "payment_method_preference" => "UNRESTRICTED",
                            "brand_name" => $order['trade_no'],
                            "locale" => "zh-CN",
                            "landing_page" => "NO_PREFERENCE",
                            "shipping_preference" => "NO_SHIPPING",
                            "user_action" => "PAY_NOW",
                            "return_url" => $order['return_url'],
                            "cancel_url" => $order['return_url']
                        ]
                    ]
                ]
            ]

        ])->getBody(), true);

        $payerActionUrl = '';
        foreach ($order['links'] as $link) {
            if ($link['rel'] === 'payer-action') {
                $payerActionUrl = $link['href'];
                break;
            }
        }

        return [
            'type' => 1, // 0:qrcode 1:url
            'data' => $payerActionUrl
        ];

    }

    public function notify($params)
    {
        $this->token = json_decode($this->client->post("{$this->apiHost}/v1/oauth2/token", [
            'auth' => [$this->config['client_id'], $this->config['client_secret']],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'form_params' => ['grant_type' => 'client_credentials']
        ])->getBody(), true)['access_token'];
        $resource = $params['resource'];
        $purchase_units = $resource['purchase_units'];
        if ($params['event_type'] == 'CHECKOUT.ORDER.APPROVED') {
            $order = json_decode($this->client->request('POST', "{$this->apiHost}/v2/checkout/orders/{$resource['id']}/capture", [
                "headers" => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->token}"
                ]
            ])->getBody(), true);
            if ($order['status'] == 'COMPLETED') {
                return [
                    'trade_no' => $purchase_units[0]['reference_id'],
                    'callback_no' => $order['id']
                ];
            }
        }
        return false;

    }
}
