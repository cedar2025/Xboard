<?php

namespace App\Payments;

use App\Exceptions\ApiException;
use App\Contracts\PaymentInterface;

class BTCPay implements PaymentInterface
{
    protected $config;
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'btcpay_url' => [
                'label' => 'API接口所在网址(包含最后的斜杠)',
                'description' => '',
                'type' => 'input',
            ],
            'btcpay_storeId' => [
                'label' => 'storeId',
                'description' => '',
                'type' => 'input',
            ],
            'btcpay_api_key' => [
                'label' => 'API KEY',
                'description' => '个人设置中的API KEY(非商店设置中的)',
                'type' => 'input',
            ],
            'btcpay_webhook_key' => [
                'label' => 'WEBHOOK KEY',
                'description' => '',
                'type' => 'input',
            ],
        ];
    }

    public function pay($order): array
    {

        $params = [
            'jsonResponse' => true,
            'amount' => sprintf('%.2f', $order['total_amount'] / 100),
            'currency' => 'CNY',
            'metadata' => [
                'orderId' => $order['trade_no']
            ]
        ];

        $params_string = @json_encode($params);

        $ret_raw = self::_curlPost($this->config['btcpay_url'] . 'api/v1/stores/' . $this->config['btcpay_storeId'] . '/invoices', $params_string);

        $ret = @json_decode($ret_raw, true);

        if (empty($ret['checkoutLink'])) {
            throw new ApiException("error!");
        }
        return [
            'type' => 1, // Redirect to url
            'data' => $ret['checkoutLink'],
        ];
    }

    public function notify($params): array|bool
    {
        $payload = trim(request()->getContent());

        $headers = getallheaders();

        //IS Btcpay-Sig
        //NOT BTCPay-Sig
        //API doc is WRONG!
        $headerName = 'Btcpay-Sig';
        $signraturHeader = isset($headers[$headerName]) ? $headers[$headerName] : '';
        $json_param = json_decode($payload, true);

        $computedSignature = "sha256=" . \hash_hmac('sha256', $payload, $this->config['btcpay_webhook_key']);

        if (!self::hashEqual($signraturHeader, $computedSignature)) {
            throw new ApiException('HMAC signature does not match', 400);
        }

        //get order id store in metadata
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => "Authorization:" . "token " . $this->config['btcpay_api_key'] . "\r\n"
            )
        ));

        $invoiceDetail = file_get_contents($this->config['btcpay_url'] . 'api/v1/stores/' . $this->config['btcpay_storeId'] . '/invoices/' . $json_param['invoiceId'], false, $context);
        $invoiceDetail = json_decode($invoiceDetail, true);


        $out_trade_no = $invoiceDetail['metadata']["orderId"];
        $pay_trade_no = $json_param['invoiceId'];
        return [
            'trade_no' => $out_trade_no,
            'callback_no' => $pay_trade_no
        ];
    }


    private function _curlPost($url, $params = false)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array('Authorization:' . 'token ' . $this->config['btcpay_api_key'], 'Content-Type: application/json')
        );
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    /**
     * @param string $str1
     * @param string $str2
     * @return bool
     */
    private function hashEqual($str1, $str2)
    {

        if (function_exists('hash_equals')) {
            return \hash_equals($str1, $str2);
        }

        if (strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;

            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }
            return !$ret;
        }
    }
}
