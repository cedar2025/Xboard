<?php

namespace App\Payments;

use \Curl\Curl;

class TokenPay {
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'token_pay_url' => [
                'label' => 'API 地址',
                'description' => '您的TokenPay API接口地址 例如: https://tokenpay.xxx.com',
                'type' => 'input',
            ],
            'token_pay_apitoken' => [
                'label' => 'API Token',
                'description' => '您的TokenPay API Token',
                'type' => 'input',
            ],
            'token_pay_currency' => [
                'label' => '币种',
                'description' => '您的TokenPay币种 例如USDT_TRC20、TRX',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        $params = [
			"ActualAmount" => $order['total_amount'] / 100,
			"OutOrderId" => $order['trade_no'], 
			"OrderUserKey" => strval($order['user_id']), 
			"Currency" => $this->config['token_pay_currency'],
			'RedirectUrl' => $order['return_url'],
			'NotifyUrl' => $order['notify_url'],
        ];
        ksort($params);
        reset($params);
        $str = stripslashes(urldecode(http_build_query($params))) . $this->config['token_pay_apitoken'];
        $params['Signature'] = md5($str);

        $curl = new Curl();
        $curl->setUserAgent('TokenPay');
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, 0);
        $curl->setOpt(CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $curl->post($this->config['token_pay_url'] . '/CreateOrder', json_encode($params));
        $result = $curl->response;
        $curl->close();

        if (!isset($result->success) || !$result->success) {
            abort(500, "Failed to create order. Error: {$result->message}");
        }

        $paymentURL = $result->data;
        return [
            'type' => 1, // 0:qrcode 1:url
            'data' => $paymentURL
        ];
    }

    public function notify($params)
    {
        $sign = $params['Signature'];
        unset($params['Signature']);
        ksort($params);
        reset($params);
        $str = stripslashes(urldecode(http_build_query($params))) . $this->config['token_pay_apitoken'];
        if ($sign !== md5($str)) {
            die('cannot pass verification');
        }
        $status = $params['Status'];
        // 0: Pending 1: Paid 2: Expired
        if ($status != 1) {
            die('failed');
        }
        return [
            'trade_no' => $params['OutOrderId'],
            'callback_no' => $params['Id'],
            'custom_result' => 'ok'
        ];
    }
}