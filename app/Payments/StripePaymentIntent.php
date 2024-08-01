<?php

namespace App\Payments;

use App\Exceptions\ApiException;
use Log;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;

class StripePaymentIntent
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
                'label' => 'Stripe API Key',
                'description' => 'Your Stripe secret API key',
                'type' => 'input',
            ],
            'webhook_secret' => [
                'label' => 'Stripe Webhook Secret',
                'description' => 'Your Stripe webhook secret',
                'type' => 'input',
            ],
            'product_name' => [
                'label' => 'Custom Product Name',
                'description' => 'This will appear on the Stripe invoice',
                'type' => 'input'
            ]
        ];
    }

    /**
     * @throws ApiException
     */
    public function pay($order)
    {
        Stripe::setApiKey($this->config['api_key']);

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $order['total_amount'],
                'currency' => 'usd',
                'description' => $this->config['product_name'] ?? 'Default Product Name',
                'metadata' => ['order_trade_no' => $order['trade_no']],
            ]);

            return [
                'type' => 1, // Payment intent created, client handles next step.
                'data' => $paymentIntent->client_secret
            ];
        } catch (ApiErrorException $e) {
            Log::error($e);
            throw new ApiException($e->getMessage());
        }
    }

    public function notify($payload)
    {
        Stripe::setApiKey($this->config['api_key']);

        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = Webhook::constructEvent(
                $payload, $sig_header, $this->config['webhook_secret']
            );
        } catch (UnexpectedValueException $e) {
            Log::error("Webhook signature verification failed: " . $e->getMessage());
            throw new ApiException('Webhook signature verification failed');
        } catch (SignatureVerificationException $e) {
            Log::error("Webhook signature verification failed: " . $e->getMessage());
            throw new ApiException('Webhook signature verification failed');
        }

        switch ($event->type) {
            case Event::PAYMENT_INTENT_SUCCEEDED:
                $intent = $event->data->object;
                return [
                    'trade_no' => $intent->metadata->order_trade_no,
                    'callback_no' => $intent->id,
                ];
            default:
                return false;
        }
    }
}
