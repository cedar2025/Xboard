<?php

namespace App\Payments;

use App\Contracts\PaymentInterface;

class TTPay implements PaymentInterface
{
    protected $config;
    private $apiUrl = 'https://api.tokenpay.me';

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'app_id' => [
                'label' => 'APP ID',
                'description' => '应用 ID',
                'type' => 'input',
            ],
            'mch_id' => [
                'label' => '商户 ID',
                'description' => '商户 ID',
                'type' => 'input',
            ],
            'app_secret' => [
                'label' => 'APP Secret',
                'description' => '应用密钥',
                'type' => 'input',
            ],
            'chain' => [
                'label' => '区块链',
                'description' => '所属公链，例如 TRON',
                'type' => 'input',
            ],
            'currency' => [
                'label' => '币种',
                'description' => '支付币种，例如 USDT',
                'type' => 'input',
            ],
            'exchange_rate' => [
                'label' => '汇率',
                'description' => '从支付币种到订单货币的汇率（例如，1 USDT = 7.15 CNY）',
                'type' => 'input',
            ],
        ];
    }

    public function pay($order): array
    {
        $url = '/v1/transaction/prepayment';
        $timestamp = time() * 1000;
        $nonce = $this->generateRandomString(32);

        $exchange_rate = floatval($this->config['exchange_rate'] ?? 1.0);
        if ($exchange_rate <= 0) {
            throw new \Exception('汇率必须为正数');
        }

        $amount_in_cny = $order['total_amount'] / 100;
        $converted_amount = round($amount_in_cny / $exchange_rate, 2);

        $body = json_encode([
            'app_id' => $this->config['app_id'],
            'mch_id' => $this->config['mch_id'],
            'description' => $order['description'] ?? '充值',
            'out_trade_no' => $order['trade_no'],
            'expire_second' => $order['expire_second'] ?? 600,
            'amount' => $converted_amount,
            'chain' => $this->config['chain'],
            'currency' => $this->config['currency'],
            'to_address' => $this->config['to_address'] ?? '',
            'attach' => $order['attach'] ?? '',
            'locale' => $order['locale'] ?? 'zh_cn',
            'notify_url' => $order['notify_url'],
            'return_url' => $order['return_url'],
            'order_type' => 'platform_order',
        ], JSON_UNESCAPED_SLASHES);

        $message = "$url\n$timestamp\n$nonce\n$body";
        $cipher = 'aes-256-ecb';
        $signature = base64_encode(openssl_encrypt($message, $cipher, $this->config['app_secret'], OPENSSL_RAW_DATA));

        $authorization = sprintf(
            'TTPAY-AES-256-ECB app_id=%s,mch_id=%s,nonce_str=%s,timestamp=%d,signature=%s',
            $this->config['app_id'],
            $this->config['mch_id'],
            $nonce,
            $timestamp,
            $signature
        );

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            "Authorization: $authorization",
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($response, true);
        if ($result && isset($result['code']) && $result['code'] === 0) {
            return [
                'type' => 1,
                'data' => $result['data']['payment_url'],
            ];
        }

        throw new \Exception('支付请求失败: ' . ($result['msg'] ?? '未知错误'));
    }

    public function notify($params): array|bool
    {
        $headers = getallheaders();
        $authorization = $headers['Authorization'] ?? '';
        if (!$authorization || strpos($authorization, 'TTPAY-AES-256-ECB') !== 0) {
            return false;
        }

        preg_match('/app_id=([^,]+),mch_id=([^,]+),nonce_str=([^,]+),timestamp=([^,]+),signature=(.+)/', $authorization, $matches);
        if (count($matches) !== 6) {
            return false;
        }

        $app_id = $matches[1];
        $mch_id = $matches[2];
        $nonce = $matches[3];
        $timestamp = $matches[4];
        $signature = $matches[5];

        $body = json_encode($params, JSON_UNESCAPED_SLASHES);
        $message = "$timestamp\n$nonce\n$body";

        $expected_signature = base64_encode(openssl_encrypt($message, 'aes-256-ecb', $this->config['app_secret'], OPENSSL_RAW_DATA));
        if ($signature !== $expected_signature) {
            return false;
        }

        if (isset($params['resource']['ciphertext']) && $params['resource']['algorithm'] === 'AEAD_AES_256_GCM') {
            $decrypted = openssl_decrypt(
                $params['resource']['ciphertext'],
                'aes-256-gcm',
                $this->config['app_secret'],
                0,
                base64_decode($params['resource']['nonce'])
            );
            if ($decrypted === false) {
                return false;
            }
            $params = json_decode($decrypted, true);
        }

        return [
            'trade_no' => $params['out_trade_no'] ?? $params['resource']['out_trade_no'],
            'callback_no' => $params['transaction_id'] ?? $params['resource']['transaction_id'],
        ];
    }

    private function generateRandomString($length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $res = '';
        for ($i = 0; $i < $length; $i++) {
            $res .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $res;
    }
}
