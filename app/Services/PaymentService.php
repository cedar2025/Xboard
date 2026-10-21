<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Services\Plugin\PluginManager;
use App\Services\Plugin\HookManager;

class PaymentService
{
    public $method;
    protected $config;
    protected $payment;
    protected $pluginManager;
    protected $class;

    public function __construct($method, $id = NULL, $uuid = NULL)
    {
        $this->method = $method;
        $this->pluginManager = app(PluginManager::class);

        if ($method === 'temp') {
            return;
        }

        if ($id) {
            $paymentModel = Payment::find($id);
            if (!$paymentModel) {
                throw new ApiException('payment not found');
            }
            $payment = $paymentModel->makeVisible('config')->toArray();
        }
        if ($uuid) {
            $paymentModel = Payment::where('uuid', $uuid)->first();
            if (!$paymentModel) {
                throw new ApiException('payment not found');
            }
            $payment = $paymentModel->makeVisible('config')->toArray();
        }

        $this->config = [];
        if (isset($payment)) {
            // The callback path must not select a different verifier for this configuration.
            if ($payment['payment'] !== $method) {
                throw new ApiException('payment method mismatch');
            }
            $this->config = is_string($payment['config']) ? json_decode($payment['config'], true) : $payment['config'];
            if (!is_array($this->config)) {
                throw new ApiException('invalid payment config');
            }
            $this->config['enable'] = $payment['enable'];
            $this->config['id'] = $payment['id'];
            $this->config['uuid'] = $payment['uuid'];
            $this->config['notify_domain'] = $payment['notify_domain'] ?? '';
        }

        $paymentMethods = $this->getAvailablePaymentMethods();
        if (isset($paymentMethods[$this->method])) {
            $pluginCode = $paymentMethods[$this->method]['plugin_code'];
            $paymentPlugins = $this->pluginManager->getEnabledPaymentPlugins();
            foreach ($paymentPlugins as $plugin) {
                if ($plugin->getPluginCode() === $pluginCode) {
                    // Loaded plugins may be shared by long-running workers.
                    $this->payment = clone $plugin;
                    $this->payment->setConfig($this->config);
                    return;
                }
            }
        }

        throw new ApiException('payment method is unavailable');
    }

    public function notify($params)
    {
        if (!$this->config['enable'])
            throw new ApiException('gate is not enable');
        $secretKey = match ($this->method) {
            'EPay' => 'key',
            'MGate' => 'mgate_app_secret',
            'Coinbase' => 'coinbase_webhook_key',
            'BTCPay' => 'btcpay_webhook_key',
            'CoinPayments' => 'coinpayments_ipn_secret',
            'AlipayF2F' => 'public_key',
            default => null,
        };
        if ($secretKey !== null && (!is_string($this->config[$secretKey] ?? null)
            || trim($this->config[$secretKey]) === '')) {
            throw new ApiException('payment verification key is missing');
        }
        return $this->payment->notify($params);
    }

    public function getPaymentId(): int
    {
        return (int) ($this->config['id'] ?? 0);
    }

    public function pay($order)
    {
        // custom notify domain name
        $notifyUrl = url("/api/v1/guest/payment/notify/{$this->method}/{$this->config['uuid']}");
        if ($this->config['notify_domain']) {
            $parseUrl = parse_url($notifyUrl);
            $notifyUrl = $this->config['notify_domain'] . $parseUrl['path'];
        }

        return $this->payment->pay([
            'notify_url' => $notifyUrl,
            'return_url' => source_base_url('/#/order/' . $order['trade_no']),
            'trade_no' => $order['trade_no'],
            'total_amount' => $order['total_amount'],
            'user_id' => $order['user_id'],
            'stripe_token' => $order['stripe_token']
        ]);
    }

    public function form()
    {
        $form = $this->payment->form();
        $result = [];
        foreach ($form as $key => $field) {
            $result[$key] = [
                'type' => $field['type'],
                'label' => $field['label'] ?? '',
                'placeholder' => $field['placeholder'] ?? '',
                'description' => $field['description'] ?? '',
                'value' => $this->config[$key] ?? $field['default'] ?? '',
                'options' => $field['select_options'] ?? $field['options'] ?? []
            ];
        }
        return $result;
    }

    /**
     * 获取所有可用的支付方式
     */
    public function getAvailablePaymentMethods(): array
    {
        $methods = [];

        $methods = HookManager::filter('available_payment_methods', $methods);

        return $methods;
    }

    /**
     * 获取所有支付方式名称列表（用于管理后台）
     */
    public static function getAllPaymentMethodNames(): array
    {
        $pluginManager = app(PluginManager::class);
        $pluginManager->initializeEnabledPlugins();

        $instance = new self('temp');
        $methods = $instance->getAvailablePaymentMethods();

        return array_keys($methods);
    }
}
