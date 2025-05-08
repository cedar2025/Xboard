<?php

namespace App\Payments;

use Illuminate\Support\Facades\Log;

class BinancePay
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'api_key' => [
                'label' => 'API Key',
                'type' => 'input',
                'description' => '请输入您的 Binance API Key'
            ],
            'secret_key' => [
                'label' => 'Secret Key',
                'type' => 'input',
                'description' => '请输入您的 Binance Secret Key'
            ]
        ];
    }

    public function pay($order)
    {
        $timestamp = intval(microtime(true) * 1000);  // Timestamp in milliseconds
        $nonceStr = bin2hex(random_bytes(16));  // Generating a nonce
        $request = [
            "env" => [
                "terminalType" => "APP"
            ],
            'merchantTradeNo' => strval($order['trade_no']),
            'fiatCurrency' => 'CNY',
            'fiatAmount' => ($order["total_amount"] / 100),
            'supportPayCurrency' => "USDT,BNB",
            'description' => strval($order['trade_no']),
            'webhookUrl' => $order['notify_url'],
            'returnUrl' => $order['return_url'],
            "goodsDetails" => [
                [
                    "goodsType" => "01",
                    "goodsCategory" => "D000",
                    "referenceGoodsId" => "7876763A3B",
                    "goodsName" => "Ice Cream",
                    "goodsDetail" => "Greentea ice cream cone"
                ]
            ]
        ];
        $body = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://bpay.binanceapi.com/binancepay/openapi/v3/order');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'BinancePay-Timestamp: ' . $timestamp,
            'BinancePay-Nonce: ' . $nonceStr,
            'BinancePay-Certificate-SN: ' . $this->config['api_key'],
            'BinancePay-Signature: ' . $this->generateSignature($body, $this->config['secret_key'], $timestamp, $nonceStr),
        ]);
        curl_setopt($ch, CURLOPT_PROXY, "socks5h://154.3.37.204:47714");
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "GGn28Io5fW:9VkWfoPGiG");
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) {
            abort(400, '支付失败，请稍后再试');
        }
        $res = json_decode($response, true);
        \Log::channel('daily')->info($res);
        if (!is_array($res)) {
            abort(400, '支付失败，请稍后再试');
        }
        if (isset($res['code']) && $res['code'] == '400201') {
            $res['data'] = \Cache::get('CheckoutInfo_' . strval($order['trade_no']));
        }
        if (!isset($res['data'])) {
            abort(400, '支付失败，请稍后再试');
        }
        if (!is_array($res['data']) || !isset($res['data']['checkoutUrl'])) {
            abort(400, '支付失败，请稍后再试');
        }
        // 缓存支付信息
        \Cache::put('CheckoutInfo_' . strval($order['trade_no']), $res['data']);
        return [
            'type' => 1, // 0:qrcode 1:url
            'data' => $res['data']['checkoutUrl']
        ];
    }

    public function notify($params)
    {
        $bizStatus = $params['bizStatus'];
        if ($bizStatus !== 'PAY_SUCCESS'){
            return false;
        }
        $data = json_decode($params['data'], true);

        return [
            'trade_no' => $data['merchantTradeNo'],
            'callback_no' => $params['bizIdStr'],
            'custom_result' => '{"returnCode":"SUCCESS","returnMessage":null}'
        ];
    }
    private function generateSignature($body, $secret, $timestamp, $nonceStr)
    {
        $payload = $timestamp . chr(0x0A) . $nonceStr . chr(0x0A) . $body . chr(0x0A);
        return strtoupper(hash_hmac('sha512', $payload, $secret));
    }
}
