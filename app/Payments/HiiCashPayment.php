<?php

namespace App\Payments;

class HiiCashPayment
{
    protected $config;
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'rate' => [
                'label' => '汇率',
                'description' => 'HiiCash支付单位为美元，如果您站点金额单位不为美元则需要填写汇率',
                'type' => 'input',
                'default' => '2333'
            ],
            'pid' => [
                'label' => '商户号',
                'description' => '',
                'type' => 'input',
            ],
            'appid' => [
                'label'  => '应用ID',
                'description' => '',
                'type' => 'input'
            ],
            'key' => [
                'label' => '私钥',
                'description' => '',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {

        $request = [
            "mchNo" => $this->config["pid"],
            "appId" => $this->config["appid"],
            "mchOrderNo" => $order["trade_no"],
            "amount" => ceil(($order["total_amount"] * 100) / ($this->config['rate'] ?? "1")) / 100,
            "payDataType" => "Cashier",
            "currency" => "USD",
            "subject" => $order["trade_no"],
            "notifyUrl" => $order["notify_url"],
            "returnUrl" => $order["return_url"],
        ];
        $headers = [
            "HiicashPay-Timestamp" => (int)(string)floor(microtime(true) * 1000),
            "HiicashPay-Nonce" => \Str::random(32),
            "HiicashPay-AppId" => $this->config["appid"]
        ];
        $body = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payload = $headers['HiicashPay-Timestamp'] . chr(0x0A) . $headers['HiicashPay-Nonce'] . chr(0x0A) . $body . chr(0x0A);
        $signature = $this->generate_signature($payload, $this->config['key']);
        $headers["HiicashPay-Signature"] = $signature;
        $jsonStr = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $httpHeaders = [];
        foreach ($headers as $key => $header) {
            $httpHeaders[] = $key . ': ' . $header;
        }
        $httpHeaders[] = 'Content-Type: application/json; charset=utf-8';
        $httpHeaders[] = 'Content-Length: ' . strlen($jsonStr);
        $ch = curl_init(file_get_contents('https://hiicash.oss-ap-northeast-1.aliyuncs.com/gateway.txt') . 'pay/order/create');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) {
            abort(400, '支付失败，请稍后再试');
        }
        $res = json_decode($response, true);
        if (!is_array($res) || !isset($res['data'])) {
            abort(400, '支付失败，请稍后再试');
        }
        if (!is_array($res['data']) || !isset($res['data']['payData'])) {
            abort(400, '支付失败，请稍后再试');
        }
        return [
            'type' => 1, // 0:qrcode 1:url
            'data' => $res['data']['payData']
        ];
    }

    public function notify($params)
    {
        if (!isset($params['mchOrderNo']) || !isset($params['mchOrderNo'])) {
            return false;
        }
        return [
            'trade_no' => $params['mchOrderNo'],
            'callback_no' => $params['payOrderId'],
            'custom_result' => '{"returnCode": "success","returnMsg": ""}'
        ];
    }


    // 使用 HMAC-SHA512 算法生成签名
    function generate_signature(string $payload, string $secret_key)
    {
        $hash = hash_hmac("sha512", $payload, $secret_key, true);
        // 将签名转换为大写字符串
        return strtoupper(bin2hex($hash));
    }
}
