<?php

namespace Plugin\Smogate;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use Curl\Curl;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['Smogate'] = [
                    'name' => $this->getConfig('display_name', 'Smogate'),
                    'icon' => $this->getConfig('icon', '🔥'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin'
                ];
            }
            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'smogate_app_id' => [
                'label' => 'APP ID',
                'type' => 'string',
                'required' => true,
                'description' => 'Smogate -> 接入文档和密钥 -> 查看APPID和密钥'
            ],
            'smogate_app_secret' => [
                'label' => 'APP Secret',
                'type' => 'string',
                'required' => true,
                'description' => 'Smogate -> 接入文档和密钥 -> 查看APPID和密钥'
            ],
            'smogate_source_currency' => [
                'label' => '源货币',
                'type' => 'string',
                'description' => '默认CNY，源货币类型'
            ],
            'smogate_method' => [
                'label' => '支付方式',
                'type' => 'string',
                'required' => true,
                'description' => 'Smogate支付方式标识'
            ]
        ];
    }

    public function pay($order): array
    {
        $params = [
            'out_trade_no' => $order['trade_no'],
            'total_amount' => $order['total_amount'],
            'notify_url' => $order['notify_url'],
            'method' => $this->getConfig('smogate_method')
        ];
        
        if ($this->getConfig('smogate_source_currency')) {
            $params['source_currency'] = strtolower($this->getConfig('smogate_source_currency'));
        }
        
        $params['app_id'] = $this->getConfig('smogate_app_id');
        ksort($params);
        $str = http_build_query($params) . $this->getConfig('smogate_app_secret');
        $params['sign'] = md5($str);
        
        $curl = new Curl();
        $curl->setUserAgent("Smogate {$this->getConfig('smogate_app_id')}");
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, 0);
        $curl->post("https://{$this->getConfig('smogate_app_id')}.vless.org/v1/gateway/pay", http_build_query($params));
        $result = $curl->response;
        
        if (!$result) {
            abort(500, '网络异常');
        }
        
        if ($curl->error) {
            if (isset($result->errors)) {
                $errors = (array)$result->errors;
                abort(500, $errors[array_keys($errors)[0]][0]);
            }
            if (isset($result->message)) {
                abort(500, $result->message);
            }
            abort(500, '未知错误');
        }
        
        $curl->close();
        
        if (!isset($result->data)) {
            abort(500, '请求失败');
        }
        
        return [
            'type' => $this->isMobile() ? 1 : 0,
            'data' => $result->data
        ];
    }

    public function notify($params): array|bool
    {
        $sign = $params['sign'];
        unset($params['sign']);
        ksort($params);
        reset($params);
        $str = http_build_query($params) . $this->getConfig('smogate_app_secret');
        
        if ($sign !== md5($str)) {
            return false;
        }
        
        return [
            'trade_no' => $params['out_trade_no'],
            'callback_no' => $params['trade_no']
        ];
    }

    private function isMobile(): bool
    {
        return strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mobile') !== false;
    }
} 