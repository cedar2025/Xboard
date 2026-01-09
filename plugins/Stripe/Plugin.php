<?php

namespace Plugin\Stripe;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    protected $stripe;

    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $paymentMethod = $this->getConfig('payment_method', 'card_wechat_alipay');
                
                // 根据配置注册不同的支付方式
                if ($paymentMethod === 'wechat_pay') {
                    $methods['StripeWeChatPay'] = [
                        'name' => 'WeChat Pay (微信支付)',
                        'icon' => '💬',
                        'plugin_code' => $this->getPluginCode(),
                        'type' => 'plugin'
                    ];
                } elseif ($paymentMethod === 'alipay') {
                    $methods['StripeAlipay'] = [
                        'name' => 'Alipay (支付宝)',
                        'icon' => '💙',
                        'plugin_code' => $this->getPluginCode(),
                        'type' => 'plugin'
                    ];
                } elseif ($paymentMethod === 'card') {
                    $methods['StripeCard'] = [
                        'name' => 'Credit/Debit Card (信用卡)',
                        'icon' => '💳',
                        'plugin_code' => $this->getPluginCode(),
                        'type' => 'plugin'
                    ];
                } elseif ($paymentMethod === 'stripe_checkout') {
                    // 新增：Stripe Checkout 选项（包含所有支付方式）
                    $methods['StripeCheckout'] = [
                        'name' => 'Stripe 支付 (Card/WeChat/Alipay/Google Pay)',
                        'icon' => '🌟',
                        'plugin_code' => $this->getPluginCode(),
                        'type' => 'plugin'
                    ];
                } elseif ($paymentMethod === 'stripe_checkout_crypto') {
                    // 新增：Stripe Checkout + 加密货币选项
                    $methods['StripeCrypto'] = [
                        'name' => 'Stripe 支付 + 加密货币 (Card/WeChat/Alipay/Crypto)',
                        'icon' => '₿',
                        'plugin_code' => $this->getPluginCode(),
                        'type' => 'plugin'
                    ];
                } else {
                    // 兼容原有配置
                    $methods['Stripe'] = [
                        'name' => $this->getConfig('display_name', 'Stripe 支付'),
                        'icon' => $this->getConfig('icon', '💳'),
                        'plugin_code' => $this->getPluginCode(),
                        'type' => 'plugin'
                    ];
                }
            }
            return $methods;
        });

        // 注册路由
        $this->registerRoutes();

        // 注册视图
        $this->registerViews();
    }

    /**
     * 注册插件路由
     */
    private function registerRoutes(): void
    {
        // 手动加载控制器类
        $controllerFile = $this->getBasePath() . '/Controllers/PaymentController.php';
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
        }
        
        $routeFile = $this->getBasePath() . '/routes/web.php';
        if (file_exists($routeFile)) {
            include $routeFile;
        }
    }

    /**
     * 注册插件视图
     */
    private function registerViews(): void
    {
        $viewPath = $this->getBasePath() . '/resources/views';
        if (is_dir($viewPath)) {
            view()->addNamespace('Stripe', $viewPath);
        }
    }

    public function form(): array
    {
        return [
            'stripe_secret_key' => [
                'label' => 'Stripe Secret Key',
                'type' => 'string',
                'required' => true,
                'description' => 'Stripe API密钥 (sk_live_... 或 sk_test_...)',
                'placeholder' => 'sk_live_...'
            ],
            'stripe_publishable_key' => [
                'label' => 'Stripe Publishable Key',
                'type' => 'string',
                'required' => true,
                'description' => 'Stripe可发布密钥 (pk_live_... 或 pk_test_...)',
                'placeholder' => 'pk_live_...'
            ],
            'webhook_secret' => [
                'label' => 'Webhook Secret',
                'type' => 'string',
                'required' => false,
                'description' => 'Stripe Webhook签名密钥 (whsec_...)，用于验证回调安全性',
                'placeholder' => 'whsec_...'
            ],
            'payment_method' => [
                'label' => '支付方式模式',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'wechat_pay', 'label' => '微信支付 (WeChat Pay) - 传统自定义页面'],
                    ['value' => 'alipay', 'label' => '支付宝 (Alipay) - 传统自定义页面'],
                    ['value' => 'card', 'label' => '信用卡/借记卡 (Card) - 传统自定义页面'],
                    ['value' => 'stripe_checkout', 'label' => 'Stripe 官方支付页面 - 支持 Card/WeChat/Alipay (推荐)'],
                    ['value' => 'stripe_checkout_crypto', 'label' => 'Stripe 官方支付页面 + 加密货币 - 支持 Card/WeChat/Alipay/Crypto (🆕)'],
                    ['value' => 'card_wechat_alipay', 'label' => '兼容模式 - 全部支付方式 (旧版配置兼容)']
                ],
                'default' => 'stripe_checkout',
                'description' => '推荐使用 "Stripe 官方支付页面"。如需支持加密货币，请选择 "Stripe 官方支付页面 + 加密货币"。'
            ],
            'enable_crypto' => [
                'label' => '启用加密货币支付',
                'type' => 'select',
                'required' => false,
                'options' => [
                    ['value' => 'true', 'label' => '是 - 启用 USDC/USDT/Bitcoin 等加密货币'],
                    ['value' => 'false', 'label' => '否 - 不启用加密货币']
                ],
                'default' => 'false',
                'description' => '启用后，用户可以使用 USDC、USDT、Bitcoin 等加密货币支付。注意：需在 Stripe Dashboard 中先启用加密货币支付。'
            ],
            'currency' => [
                'label' => '货币类型',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'CNY', 'label' => '人民币 (CNY)'],
                    ['value' => 'USD', 'label' => '美元 (USD)'],
                    ['value' => 'EUR', 'label' => '欧元 (EUR)'],
                    ['value' => 'GBP', 'label' => '英镑 (GBP)'],
                    ['value' => 'HKD', 'label' => '港币 (HKD)'],
                    ['value' => 'JPY', 'label' => '日元 (JPY)'],
                    ['value' => 'SGD', 'label' => '新币 (SGD)']
                ],
                'default' => 'CNY',
                'description' => 'WeChat Pay和Alipay支持的货币类型'
            ],
            'product_description' => [
                'label' => '商品描述',
                'type' => 'string',
                'required' => false,
                'description' => '将显示在支付页面的商品描述',
                'default' => '订阅服务'
            ],
            'logo_url' => [
                'label' => 'Logo URL（可选）',
                'type' => 'string',
                'required' => false,
                'description' => '支付页面显示的 Logo 图片 URL（需要是 HTTPS 链接，建议尺寸：512x512 像素）。留空则使用 Stripe Dashboard 中配置的 Logo。',
                'placeholder' => 'https://yourdomain.com/logo.png'
            ],
            'enable_currency_conversion' => [
                'label' => '启用货币选择（多货币显示）',
                'type' => 'select',
                'required' => false,
                'options' => [
                    ['value' => 'true', 'label' => '是 - 用户可在支付页面选择货币'],
                    ['value' => 'false', 'label' => '否 - 仅显示单一货币']
                ],
                'default' => 'false',
                'description' => '启用后，用户在支付页面可以选择用人民币或美元支付。例如：网站显示 CN¥50，用户可选择用 CN¥50 或 $7.26 支付。'
            ],
            'enable_invoice_creation' => [
                'label' => '启用发票生成（Invoice & Receipt PDF）',
                'type' => 'select',
                'required' => false,
                'options' => [
                    ['value' => 'true', 'label' => '是 - 支付成功后自动生成 Invoice 和 Receipt PDF 并发送邮件'],
                    ['value' => 'false', 'label' => '否 - 仅发送基础收据邮件']
                ],
                'default' => 'true',
                'description' => '启用后，用户支付成功会收到带有 Invoice PDF 和 Receipt PDF 附件的邮件。'
            ],
            'auto_capture' => [
                'label' => '自动确认付款',
                'type' => 'select',
                'required' => false,
                'options' => [
                    ['value' => 'true', 'label' => '是'],
                    ['value' => 'false', 'label' => '否']
                ],
                'default' => 'true',
                'description' => '是否自动确认付款，关闭后需要手动确认'
            ]
        ];
    }

    private function initializeStripe(): void
    {
        $secretKey = $this->getConfig('stripe_secret_key');
        if (empty($secretKey)) {
            throw new ApiException('Stripe Secret Key 未配置');
        }

        $this->stripe = new StripeClient($secretKey);
        Stripe::setApiKey($secretKey);
    }

    public function pay($order): array
    {
        try {
            $this->initializeStripe();

            $currency = strtoupper($this->getConfig('currency', 'CNY'));
            $amount = $order['total_amount'];
            $paymentMethod = $this->getConfig('payment_method', 'both');

            // 货币转换处理
            if ($currency !== 'CNY') {
                $exchangeRate = $this->getExchangeRate('CNY', $currency);
                if (!$exchangeRate) {
                    throw new ApiException('货币转换失败，请稍后重试');
                }
                $amount = floor($amount * $exchangeRate);
            }

            // 检查最小金额限制
            $minAmount = $this->getMinimumAmount($currency);
            if ($amount < $minAmount) {
                throw new ApiException("支付金额过小，最小金额为 {$minAmount} {$currency}");
            }

            Log::info('Stripe WeChat/Alipay 支付发起', [
                'trade_no' => $order['trade_no'],
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $paymentMethod
            ]);

            // 创建支付意图
            return $this->createPaymentIntent($order, $amount, $currency, $paymentMethod);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API 错误', [
                'error' => $e->getMessage(),
                'trade_no' => $order['trade_no'] ?? 'unknown'
            ]);
            throw new ApiException('支付网关错误: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Stripe 支付错误', [
                'error' => $e->getMessage(),
                'trade_no' => $order['trade_no'] ?? 'unknown'
            ]);
            throw new ApiException('支付处理失败: ' . $e->getMessage());
        }
    }

    private function createPaymentIntent($order, $amount, $currency, $paymentMethod): array
    {
        // 如果是 stripe_checkout 或 stripe_checkout_crypto 模式，直接使用 Checkout Session
        if ($paymentMethod === 'stripe_checkout' || $paymentMethod === 'stripe_checkout_crypto') {
            return $this->createCheckoutSession($order, $amount, $currency, $paymentMethod);
        }

        // 传统模式：根据支付方式设置可用的支付方法
        $paymentMethodTypes = [];
        if ($paymentMethod === 'wechat_pay' || $paymentMethod === 'wechat_alipay' || $paymentMethod === 'card_wechat_alipay') {
            $paymentMethodTypes[] = 'wechat_pay';
        }
        if ($paymentMethod === 'alipay' || $paymentMethod === 'wechat_alipay' || $paymentMethod === 'card_wechat_alipay') {
            $paymentMethodTypes[] = 'alipay';
        }
        if ($paymentMethod === 'card' || $paymentMethod === 'card_wechat_alipay') {
            $paymentMethodTypes[] = 'card';
        }

        // 使用传统的Payment Intent + 自定义页面逻辑
        $params = [
            'amount' => $amount,
            'currency' => strtolower($currency),
            'payment_method_types' => $paymentMethodTypes,
            'confirmation_method' => 'automatic',
            'confirm' => false, // 不立即确认，让前端处理
            'statement_descriptor_suffix' => 'PremiumLinks',
            'description' => $this->getConfig('product_description', '订阅服务'),
            'metadata' => [
                'user_id' => $order['user_id'],
                'out_trade_no' => $order['trade_no'],
                'order_amount' => $order['total_amount']
            ]
        ];

        // 设置捕获方式
        $params['capture_method'] = ($this->getConfig('auto_capture', 'true') === 'true') ? 'automatic' : 'manual';

        $paymentIntent = $this->stripe->paymentIntents->create($params);

        // 根据支付方式返回不同的处理方式
        if (count($paymentMethodTypes) === 1) {
            // 单一支付方式，直接确认支付
            return $this->confirmPaymentForSingleMethod($paymentIntent, $paymentMethodTypes[0], $order);
        } else {
            // 多支付方式，返回自定义页面让用户选择
            $paymentPageUrl = url("/plugins/stripe/payment") . '?' . http_build_query([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'publishable_key' => $this->getConfig('stripe_publishable_key'),
                'payment_methods' => implode(',', $paymentMethodTypes),
                'return_url' => $order['return_url'],
                'trade_no' => $order['trade_no'],
                'currency' => $currency,
                'amount' => $amount
            ]);

            return [
                'type' => 1, // 重定向类型
                'data' => $paymentPageUrl
            ];
        }
    }

    /**
     * 创建Stripe Checkout会话（原生支付页面）
     */
    private function createCheckoutSession($order, $amount, $currency, $paymentMethod = 'card'): array
    {
        try {
            // 根据配置的支付方式设置Checkout支持的支付方法
            $paymentMethodTypes = [];
            $currencyLower = strtolower($currency);

            // 检查是否启用加密货币
            $enableCrypto = $this->getConfig('enable_crypto', 'false') === 'true';
            $isCryptoMode = $paymentMethod === 'stripe_checkout_crypto';

            // WeChat Pay 支持的货币检查
            $wechatSupportedCurrencies = ['cny', 'usd', 'hkd', 'eur', 'gbp', 'jpy', 'sgd', 'aud', 'cad'];
            if (($paymentMethod === 'wechat_pay' || $paymentMethod === 'wechat_alipay' || $paymentMethod === 'card_wechat_alipay' || $paymentMethod === 'stripe_checkout' || $isCryptoMode)
                && in_array($currencyLower, $wechatSupportedCurrencies)) {
                $paymentMethodTypes[] = 'wechat_pay';
            }

            // Alipay 支持的货币检查
            $alipaySupportedCurrencies = ['cny', 'usd', 'hkd', 'eur', 'gbp', 'jpy', 'sgd', 'aud', 'cad', 'nzd'];
            if (($paymentMethod === 'alipay' || $paymentMethod === 'wechat_alipay' || $paymentMethod === 'card_wechat_alipay' || $paymentMethod === 'stripe_checkout' || $isCryptoMode)
                && in_array($currencyLower, $alipaySupportedCurrencies)) {
                $paymentMethodTypes[] = 'alipay';
            }

            // Card 支持所有货币
            if ($paymentMethod === 'card' || $paymentMethod === 'card_wechat_alipay' || $paymentMethod === 'stripe_checkout' || $isCryptoMode) {
                $paymentMethodTypes[] = 'card';
            }

            // 加密货币支付方式（仅在启用时添加）
            $shouldEnableCrypto = false;

            if ($enableCrypto || $isCryptoMode) {
                // 检查主货币是否支持
                if (in_array($currencyLower, ['usd', 'eur'])) {
                    $shouldEnableCrypto = true;
                    Log::info('主货币支持加密货币支付', [
                        'currency' => $currency,
                        'payment_method' => $paymentMethod
                    ]);
                } else {
                    // 检查是否启用了多货币选择，且备选货币包含 USD 或 EUR
                    $enableCurrencyConversion = $this->getConfig('enable_currency_conversion', 'false') === 'true';
                    if ($enableCurrencyConversion) {
                        // 获取备选货币
                        $currencyPairs = [
                            'CNY' => ['USD', 'EUR', 'HKD'],
                            'HKD' => ['CNY', 'USD'],
                            'GBP' => ['USD', 'EUR'],
                            'JPY' => ['USD', 'CNY'],
                            'SGD' => ['USD', 'CNY'],
                        ];

                        $primaryCurrency = strtoupper($currency);
                        if (isset($currencyPairs[$primaryCurrency])) {
                            $alternativeCurrencies = $currencyPairs[$primaryCurrency];
                            // 如果备选货币包含 USD 或 EUR，启用加密货币
                            if (array_intersect($alternativeCurrencies, ['USD', 'EUR'])) {
                                $shouldEnableCrypto = true;
                                Log::info('备选货币支持加密货币支付', [
                                    'primary_currency' => $currency,
                                    'alternative_currencies' => $alternativeCurrencies,
                                    'payment_method' => $paymentMethod
                                ]);
                            }
                        }
                    } else {
                        Log::warning('加密货币支付仅支持 USD 和 EUR 货币', [
                            'current_currency' => $currency,
                            'payment_method' => $paymentMethod
                        ]);
                    }
                }
            }

            // 注意：根据 Stripe 官方文档 (https://docs.stripe.com/payments/stablecoin-payments)
            // 稳定币支付会由 Stripe 自动检测和显示，不需要在 payment_method_types 中手动指定
            // 只要在 Stripe Dashboard 中启用了稳定币支付，Checkout 页面会自动显示 "Crypto" 选项

            if ($shouldEnableCrypto) {
                Log::info('加密货币支付已在配置中启用（将由 Stripe 自动检测）', [
                    'currency' => $currency,
                    'payment_method' => $paymentMethod,
                    'note' => 'Stripe 会根据 Dashboard 配置自动显示加密货币选项'
                ]);

                // 不需要手动添加 payment_method_type
                // Stripe 会根据以下条件自动显示 Crypto 选项：
                // 1. Dashboard 中已启用稳定币支付
                // 2. 货币为 USD（稳定币仅支持 USD）
                // 3. 客户位于支持的地区（主要是美国）
            }

            // Google Pay 会由 Stripe 自动检测并显示，无需在 payment_method_types 中指定

            // 如果没有匹配的支付方式，默认使用card
            if (empty($paymentMethodTypes)) {
                $paymentMethodTypes = ['card'];
                Log::warning('没有支持的支付方式，默认使用Card', [
                    'currency' => $currency,
                    'payment_method' => $paymentMethod
                ]);
            }

            // 获取用户邮箱信息
            $userEmail = '';
            $userName = '';
            
            if (!empty($order['user_id'])) {
                try {
                    $user = \App\Models\User::find($order['user_id']);
                    if ($user && $user->email) {
                        $userEmail = $user->email;
                        // 提取邮箱@符号前的部分作为姓名
                        $userName = strstr($userEmail, '@', true);
                    }
                } catch (\Exception $e) {
                    Log::warning('获取用户邮箱失败', [
                        'user_id' => $order['user_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 检查是否启用货币选择功能
            $enableCurrencyConversion = $this->getConfig('enable_currency_conversion', 'false') === 'true';
            $lineItem = [];

            Log::info('准备创建Checkout Session', [
                'enable_currency_conversion' => $enableCurrencyConversion,
                'currency' => $currency,
                'amount' => $amount,
                'trade_no' => $order['trade_no']
            ]);

            if ($enableCurrencyConversion) {
                // 启用多货币：需要先创建 Product 和 Price 对象
                $currencyOptions = $this->getCurrencyOptions($currency, $amount);

                Log::info('getCurrencyOptions 返回结果', [
                    'currency_options' => $currencyOptions,
                    'is_empty' => empty($currencyOptions),
                    'trade_no' => $order['trade_no']
                ]);

                if (!empty($currencyOptions)) {
                    Log::info('启用多货币选择功能', [
                        'primary_currency' => $currency,
                        'alternative_currencies' => array_keys($currencyOptions),
                        'trade_no' => $order['trade_no']
                    ]);

                    // 创建 Product（如果需要图片，在这里设置）
                    $productParams = [
                        'name' => $this->getConfig('product_description', '订阅服务'),
                        'description' => 'PremiumLinks - 订单号: ' . $order['trade_no'],
                    ];

                    $images = $this->getProductImages();
                    if (!empty($images)) {
                        $productParams['images'] = $images;
                    }

                    $product = $this->stripe->products->create($productParams);

                    // 创建 Price 对象，包含 currency_options
                    $priceParams = [
                        'product' => $product->id,
                        'currency' => strtolower($currency),
                        'unit_amount' => $amount,
                        'currency_options' => $currencyOptions,
                    ];

                    $price = $this->stripe->prices->create($priceParams);

                    Log::info('创建多货币 Price 对象成功', [
                        'price_id' => $price->id,
                        'product_id' => $product->id,
                        'trade_no' => $order['trade_no']
                    ]);

                    // 使用 Price ID
                    $lineItem = [
                        'price' => $price->id,
                        'quantity' => 1,
                    ];
                } else {
                    // 没有可用的备选货币，使用标准 price_data
                    Log::warning('getCurrencyOptions 返回空，使用标准 price_data', [
                        'currency' => $currency,
                        'amount' => $amount,
                        'trade_no' => $order['trade_no']
                    ]);

                    $lineItem = [
                        'price_data' => [
                            'currency' => strtolower($currency),
                            'product_data' => [
                                'name' => $this->getConfig('product_description', '订阅服务'),
                                'description' => 'PremiumLinks - 订单号: ' . $order['trade_no'],
                                'images' => $this->getProductImages(),
                            ],
                            'unit_amount' => $amount,
                        ],
                        'quantity' => 1,
                    ];
                }
            } else {
                // 未启用多货币：使用标准 price_data
                Log::info('未启用多货币选择，使用标准 price_data', [
                    'currency' => $currency,
                    'trade_no' => $order['trade_no']
                ]);

                $lineItem = [
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => $this->getConfig('product_description', '订阅服务'),
                            'description' => 'PremiumLinks - 订单号: ' . $order['trade_no'],
                            'images' => $this->getProductImages(),
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ];
            }

            // 检查是否启用发票生成
            $enableInvoiceCreation = $this->getConfig('enable_invoice_creation', 'true') === 'true';

            // 构建Checkout Session参数
            $sessionParams = [
                'payment_method_types' => $paymentMethodTypes,
                'line_items' => [$lineItem],
                'mode' => 'payment',
                'success_url' => $order['return_url'] . '?session_id={CHECKOUT_SESSION_ID}&trade_no=' . $order['trade_no'],
                'cancel_url' => $order['return_url'] . '?canceled=1&trade_no=' . $order['trade_no'],
                'metadata' => [
                    'user_id' => $order['user_id'],
                    'out_trade_no' => $order['trade_no'],
                    'order_amount' => $order['total_amount']
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'user_id' => $order['user_id'],
                        'out_trade_no' => $order['trade_no'],
                        'order_amount' => $order['total_amount']
                    ],
                    'statement_descriptor_suffix' => 'PremiumLinks',
                    'capture_method' => ($this->getConfig('auto_capture', 'true') === 'true') ? 'automatic' : 'manual',
                    'receipt_email' => $userEmail ?? null, // 自动发送收据到用户邮箱
                ],
                'billing_address_collection' => 'auto',
                'customer_creation' => 'always',
                'locale' => 'auto',
            ];

            // 如果启用了发票生成功能，添加 invoice_creation 参数
            if ($enableInvoiceCreation) {
                $sessionParams['invoice_creation'] = [
                    'enabled' => true,
                    'invoice_data' => [
                        'description' => 'PremiumLinks 订阅服务 - 订单号: ' . $order['trade_no'],
                        'metadata' => [
                            'order_id' => $order['trade_no'],
                        ],
                        'footer' => '感谢您的购买！如有疑问，请联系客服。',
                    ]
                ];

                Log::info('已启用发票生成功能', [
                    'trade_no' => $order['trade_no'],
                    'receipt_email' => $userEmail ?? 'none'
                ]);
            }

            // 如果有用户邮箱，预填到Checkout页面
            if ($userEmail) {
                $sessionParams['customer_email'] = $userEmail;

                Log::info('Stripe Checkout 预填用户信息', [
                    'user_email' => $userEmail,
                    'user_name' => $userName,
                    'trade_no' => $order['trade_no']
                ]);
            }

            // 设置 payment_method_options
            $paymentMethodOptions = [];

            // 如果包含 WeChat Pay，需要设置 payment_method_options
            if (in_array('wechat_pay', $paymentMethodTypes)) {
                $paymentMethodOptions['wechat_pay'] = [
                    'client' => 'web' // Checkout页面只支持web客户端
                ];
            }

            // 加密货币支付不需要配置 payment_method_options
            // Stripe 会自动处理稳定币支付的所有配置
            // 参考：https://docs.stripe.com/payments/stablecoin-payments

            if (!empty($paymentMethodOptions)) {
                $sessionParams['payment_method_options'] = $paymentMethodOptions;
            }

            $session = $this->stripe->checkout->sessions->create($sessionParams);

            Log::info('Stripe Checkout会话创建成功', [
                'session_id' => $session->id,
                'trade_no' => $order['trade_no'],
                'amount' => $amount,
                'currency' => $currency,
                'payment_method_types' => $paymentMethodTypes,
                'checkout_url' => $session->url,
                'supported_methods' => implode(', ', $paymentMethodTypes)
            ]);

            return [
                'type' => 1, // 重定向类型
                'data' => $session->url // 直接跳转到Stripe Checkout页面
            ];

        } catch (\Exception $e) {
            Log::error('创建Stripe Checkout会话失败', [
                'error' => $e->getMessage(),
                'trade_no' => $order['trade_no'],
                'payment_method' => $paymentMethod
            ]);
            throw $e;
        }
    }

    /**
     * 确认单一支付方式的支付
     */
    private function confirmPaymentForSingleMethod($paymentIntent, $paymentMethodType, $order): array
    {
        try {
            // Card支付处理（仅在使用自定义页面时）
            if ($paymentMethodType === 'card') {
                Log::info('Card支付返回自定义支付页面URL', [
                    'payment_intent_id' => $paymentIntent->id,
                    'trade_no' => $order['trade_no']
                ]);
                
                $paymentPageUrl = url("/plugins/stripe/payment") . '?' . http_build_query([
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'publishable_key' => $this->getConfig('stripe_publishable_key'),
                    'payment_methods' => 'card',
                    'return_url' => $order['return_url'],
                    'trade_no' => $order['trade_no'],
                    'currency' => strtoupper($this->getConfig('currency', 'CNY')),
                    'amount' => $paymentIntent->amount
                ]);
                
                return [
                    'type' => 1, // 重定向类型，与现有前端兼容
                    'data' => $paymentPageUrl
                ];
            }
            
            // WeChat Pay和Alipay的原有逻辑
            // 创建支付方法
            $paymentMethod = $this->stripe->paymentMethods->create([
                'type' => $paymentMethodType,
            ]);

            // 将支付方法附加到支付意图
            $this->stripe->paymentIntents->update($paymentIntent->id, [
                'payment_method' => $paymentMethod->id,
            ]);

            // 构建确认参数
            $confirmParams = [
                'return_url' => $order['return_url'] . '?payment_intent=' . $paymentIntent->id . '&trade_no=' . $order['trade_no']
            ];
            
            // 为WeChat Pay添加必要的支付方式选项
            if ($paymentMethodType === 'wechat_pay') {
                // 检测客户端类型
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $isMobile = preg_match('/(android|iphone|ipad|mobile)/i', $userAgent);
                
                $confirmParams['payment_method_options'] = [
                    'wechat_pay' => [
                        'client' => $isMobile ? 'mobile' : 'web'
                    ]
                ];
                
                Log::info('WeChat Pay 确认参数', [
                    'client_type' => $isMobile ? 'mobile' : 'web',
                    'payment_intent_id' => $paymentIntent->id,
                    'user_agent' => $userAgent
                ]);
            }

            // 确认支付意图
            $confirmedPaymentIntent = $this->stripe->paymentIntents->confirm($paymentIntent->id, $confirmParams);

            if ($confirmedPaymentIntent->status === 'succeeded') {
                // 支付立即成功，直接返回成功
                return [
                    'type' => 1,
                    'data' => $order['return_url'] . '?payment_success=1&trade_no=' . $order['trade_no']
                ];
            } elseif ($confirmedPaymentIntent->status === 'requires_action') {
                // 需要用户操作
                $nextAction = $confirmedPaymentIntent->next_action;
                
                if ($paymentMethodType === 'wechat_pay' && isset($nextAction->wechat_pay_display_qr_code)) {
                    // WeChat Pay QR码
                    return [
                        'type' => 0, // QR码类型
                        'data' => $nextAction->wechat_pay_display_qr_code->data
                    ];
                } elseif ($paymentMethodType === 'alipay' && isset($nextAction->alipay_handle_redirect)) {
                    // Alipay重定向
                    return [
                        'type' => 1, // 重定向类型
                        'data' => $nextAction->alipay_handle_redirect->url
                    ];
                } elseif ($paymentMethodType === 'card' && isset($nextAction->use_stripe_sdk)) {
                    // Card支付需要客户端确认
                    return [
                        'type' => 2, // 客户端确认类型
                        'data' => [
                            'client_secret' => $confirmedPaymentIntent->client_secret,
                            'publishable_key' => $this->getConfig('stripe_publishable_key'),
                            'payment_intent_id' => $confirmedPaymentIntent->id
                        ]
                    ];
                }
            }

            // 其他情况，返回支付页面
            $paymentPageUrl = url("/plugins/stripe/payment") . '?' . http_build_query([
                'client_secret' => $confirmedPaymentIntent->client_secret,
                'payment_intent_id' => $confirmedPaymentIntent->id,
                'publishable_key' => $this->getConfig('stripe_publishable_key'),
                'payment_methods' => $paymentMethodType,
                'return_url' => $order['return_url'],
                'trade_no' => $order['trade_no'],
                'status' => $confirmedPaymentIntent->status
            ]);

            return [
                'type' => 1,
                'data' => $paymentPageUrl
            ];

        } catch (\Exception $e) {
            Log::error('确认支付失败', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntent->id,
                'payment_method_type' => $paymentMethodType
            ]);
            throw $e;
        }
    }

    public function notify($params): array|bool
    {
        try {
            Log::info('Stripe WeChat/Alipay Webhook 开始处理', [
                'has_webhook_secret' => !empty($this->getConfig('webhook_secret')),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'params_preview' => array_keys($params)
            ]);

            // 检查是否是真正的URL参数回调（只有包含payment_intent和trade_no时才是）
            if (!empty($params) && is_array($params) && 
                (isset($params['payment_intent']) || isset($params['trade_no']))) {
                Log::info('处理URL参数形式的回调', ['params' => $params]);
                return $this->processUrlCallback($params);
            }
            
            // 如果params包含Stripe webhook的特征，则作为webhook处理
            if (!empty($params) && is_array($params) && isset($params['type']) && isset($params['data'])) {
                Log::info('处理Stripe Webhook事件', [
                    'event_type' => $params['type'],
                    'event_id' => $params['id'] ?? 'unknown'
                ]);
                $event = $this->arrayToObject($params);
                return $this->processWebhookEvent($event);
            }

            // 处理Stripe Webhook
            $payload = file_get_contents('php://input');
            if (empty($payload)) {
                Log::error('Stripe webhook: 空的请求体');
                return false;
            }

            // 获取签名头
            $signatureHeader = $this->getStripeSignatureHeader();

            // 验证webhook签名
            $webhookSecret = $this->getConfig('webhook_secret');
            if (!empty($webhookSecret)) {
                try {
                    $event = Webhook::constructEvent(
                        $payload,
                        $signatureHeader,
                        $webhookSecret
                    );
                    Log::info('Stripe webhook 签名验证成功');
                } catch (SignatureVerificationException $e) {
                    Log::error('Stripe webhook 签名验证失败', [
                        'error' => $e->getMessage(),
                        'signature_header' => $signatureHeader
                    ]);
                    return false;
                }
            } else {
                Log::warning('未配置webhook密钥，跳过签名验证');
                $eventData = json_decode($payload, true);
                if (!$eventData) {
                    Log::error('Stripe webhook: 无效的JSON数据');
                    return false;
                }
                $event = $this->arrayToObject($eventData);
            }

            Log::info('Stripe webhook 事件处理', [
                'event_id' => $event->id ?? 'unknown',
                'event_type' => $event->type ?? 'unknown'
            ]);

            // 处理事件
            return $this->processWebhookEvent($event);

        } catch (\Exception $e) {
            Log::error('Stripe webhook 处理错误', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }

    /**
     * 处理URL参数形式的回调（用于处理前端确认的支付）
     */
    private function processUrlCallback($params): array|bool
    {
        // 检查必要的参数
        if (empty($params['payment_intent']) && empty($params['trade_no']) && empty($params['session_id'])) {
            Log::error('URL回调缺少必要参数', ['params' => $params]);
            return false;
        }

        try {
            $this->initializeStripe();

            // 处理Stripe Checkout回调
            if (!empty($params['session_id'])) {
                return $this->processCheckoutCallback($params);
            }

            // 处理Payment Intent回调
            $paymentIntentId = $params['payment_intent'] ?? null;
            $tradeNo = $params['trade_no'] ?? null;

            if ($paymentIntentId) {
                // 通过payment_intent_id获取支付信息
                $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
                
                if ($paymentIntent->status === 'succeeded') {
                    $tradeNo = $paymentIntent->metadata->out_trade_no ?? $tradeNo;
                    
                    if (!$tradeNo) {
                        Log::error('PaymentIntent中缺少订单号', [
                            'payment_intent_id' => $paymentIntentId
                        ]);
                        return false;
                    }

                    Log::info('URL回调支付成功', [
                        'payment_intent_id' => $paymentIntentId,
                        'trade_no' => $tradeNo,
                        'amount' => $paymentIntent->amount,
                        'currency' => $paymentIntent->currency
                    ]);

                    return [
                        'trade_no' => $tradeNo,
                        'callback_no' => $paymentIntentId,
                        'custom_result' => 'success' // 自定义返回结果
                    ];
                } else {
                    Log::warning('PaymentIntent状态不是成功', [
                        'payment_intent_id' => $paymentIntentId,
                        'status' => $paymentIntent->status
                    ]);
                    return false;
                }
            }

            Log::error('无法处理URL回调', ['params' => $params]);
            return false;

        } catch (\Exception $e) {
            Log::error('URL回调处理失败', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            return false;
        }
    }

    /**
     * 处理Stripe Checkout回调
     */
    private function processCheckoutCallback($params): array|bool
    {
        $sessionId = $params['session_id'];
        $tradeNo = $params['trade_no'] ?? null;

        try {
            // 检索Checkout Session
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $tradeNo = $session->metadata->out_trade_no ?? $tradeNo;
                
                if (!$tradeNo) {
                    Log::error('Checkout Session中缺少订单号', [
                        'session_id' => $sessionId
                    ]);
                    return false;
                }

                Log::info('Stripe Checkout支付成功', [
                    'session_id' => $sessionId,
                    'payment_intent_id' => $session->payment_intent,
                    'trade_no' => $tradeNo,
                    'amount_total' => $session->amount_total,
                    'currency' => $session->currency
                ]);

                return [
                    'trade_no' => $tradeNo,
                    'callback_no' => $session->payment_intent,
                    'custom_result' => 'success'
                ];
            } else {
                Log::warning('Checkout Session支付状态不是成功', [
                    'session_id' => $sessionId,
                    'payment_status' => $session->payment_status
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('处理Checkout回调失败', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'trade_no' => $tradeNo
            ]);
            return false;
        }
    }

    private function processWebhookEvent($event): array|bool
    {
        $eventType = $event->type;
        $object = $event->data->object;

        Log::info('处理 Stripe webhook 事件', [
            'event_type' => $eventType,
            'object_type' => $object->object ?? 'unknown',
            'object_id' => $object->id ?? 'unknown',
            'object_status' => $object->status ?? 'unknown'
        ]);

        switch ($eventType) {
            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($object);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($object);

            case 'payment_intent.requires_action':
                Log::info('支付需要额外操作', [
                    'payment_intent_id' => $object->id
                ]);
                return false;

            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($object);

            default:
                Log::warning('未处理的 Stripe webhook 事件类型', [
                    'event_type' => $eventType,
                    'object_id' => $object->id ?? 'unknown'
                ]);
                return false;
        }
    }

    private function handlePaymentIntentSucceeded($object): array|bool
    {
        if ($object->status !== 'succeeded') {
            Log::warning('支付意图状态不是成功', [
                'payment_intent_id' => $object->id,
                'status' => $object->status
            ]);
            return false;
        }

        // 检查元数据中是否包含订单号
        if (empty($object->metadata) || empty($object->metadata->out_trade_no)) {
            Log::error('支付意图元数据中缺少订单号', [
                'payment_intent_id' => $object->id,
                'metadata' => $object->metadata ?? 'null'
            ]);
            return false;
        }

        $tradeNo = $object->metadata->out_trade_no;
        Log::info('支付成功', [
            'payment_intent_id' => $object->id,
            'trade_no' => $tradeNo,
            'amount' => $object->amount,
            'currency' => $object->currency,
            'payment_method_types' => $object->payment_method_types ?? [],
            'user_id' => $object->metadata->user_id ?? 'unknown'
        ]);

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $object->id
        ];
    }

    private function handlePaymentIntentFailed($object): bool
    {
        Log::warning('支付失败', [
            'payment_intent_id' => $object->id,
            'last_payment_error' => $object->last_payment_error ?? 'unknown'
        ]);
        return false;
    }

    private function handleCheckoutSessionCompleted($object): array|bool
    {
        if ($object->payment_status !== 'paid') {
            Log::warning('Checkout会话完成但支付状态不是已付款', [
                'session_id' => $object->id,
                'payment_status' => $object->payment_status
            ]);
            return false;
        }

        // 检查元数据中是否包含订单号
        if (empty($object->metadata) || empty($object->metadata->out_trade_no)) {
            Log::error('Checkout会话元数据中缺少订单号', [
                'session_id' => $object->id,
                'metadata' => $object->metadata ?? 'null'
            ]);
            return false;
        }

        $tradeNo = $object->metadata->out_trade_no;
        Log::info('Checkout支付成功', [
            'session_id' => $object->id,
            'payment_intent_id' => $object->payment_intent,
            'trade_no' => $tradeNo,
            'amount_total' => $object->amount_total,
            'currency' => $object->currency,
            'user_id' => $object->metadata->user_id ?? 'unknown'
        ]);

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $object->payment_intent
        ];
    }

    private function getStripeSignatureHeader(): string
    {
        // 尝试不同方式获取Stripe签名头
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Stripe-Signature'])) {
                return $headers['Stripe-Signature'];
            }
        }

        $serverHeaders = [
            'HTTP_STRIPE_SIGNATURE',
            'STRIPE_SIGNATURE'
        ];

        foreach ($serverHeaders as $header) {
            if (isset($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }

        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (isset($apacheHeaders['Stripe-Signature'])) {
                return $apacheHeaders['Stripe-Signature'];
            }
        }

        return '';
    }

    private function arrayToObject($array): object
    {
        $object = new \stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $object->$key = $this->arrayToObject($value);
            } else {
                $object->$key = $value;
            }
        }
        return $object;
    }

    private function getMinimumAmount($currency): int
    {
        // Stripe最小金额限制（以最小货币单位计）
        $minimums = [
            'USD' => 50,   // $0.50
            'EUR' => 50,   // €0.50
            'GBP' => 30,   // £0.30
            'CAD' => 50,   // $0.50
            'AUD' => 50,   // $0.50
            'SGD' => 50,   // $0.50
            'HKD' => 400,  // $4.00
            'JPY' => 50,   // ¥50
            'CNY' => 350,  // ¥3.50
        ];

        return $minimums[$currency] ?? 50;
    }

    private function getExchangeRate($from, $to): ?float
    {
        $from = strtolower($from);
        $to = strtolower($to);

        // 相同货币不需要转换
        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = "stripe_exchange_rate_{$from}_{$to}";

        // 先从缓存获取（5分钟缓存）
        $cachedRate = cache()->get($cacheKey);
        if ($cachedRate !== null) {
            Log::info('使用缓存的汇率', [
                'from' => $from,
                'to' => $to,
                'rate' => $cachedRate
            ]);
            return $cachedRate;
        }

        try {
            // 使用免费汇率API
            $url = "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$from}.min.json";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Xboard/1.0'
                ]
            ]);

            $result = file_get_contents($url, false, $context);
            if ($result === false) {
                throw new \Exception("获取汇率失败");
            }

            $data = json_decode($result, true);
            if (!$data || !isset($data[$from][$to])) {
                throw new \Exception("无效的汇率数据");
            }

            $rate = (float) $data[$from][$to];

            if ($rate <= 0) {
                throw new \Exception("无效的汇率: {$rate}");
            }

            // 缓存5分钟
            cache()->put($cacheKey, $rate, 300);

            Log::info('成功获取汇率', [
                'from' => $from,
                'to' => $to,
                'rate' => $rate
            ]);

            return $rate;

        } catch (\Exception $e) {
            Log::error('获取汇率失败', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            // 使用固定汇率作为备用
            $fixedRates = [
                'cny_usd' => 0.14,
                'cny_eur' => 0.13,
                'cny_gbp' => 0.11,
                'cny_hkd' => 1.1,
                'cny_jpy' => 20.0,
                'cny_sgd' => 0.19,
            ];

            $key = $from . '_' . $to;
            if (isset($fixedRates[$key])) {
                Log::warning('使用固定汇率', [
                    'from' => $from,
                    'to' => $to,
                    'rate' => $fixedRates[$key]
                ]);
                return $fixedRates[$key];
            }

            return null;
        }
    }

    /**
     * 获取商品图片（用于 Checkout 页面显示）
     *
     * @return array
     */
    private function getProductImages(): array
    {
        $logoUrl = $this->getConfig('logo_url');

        // 验证 Logo URL 是否有效
        if (!empty($logoUrl) && filter_var($logoUrl, FILTER_VALIDATE_URL) && strpos($logoUrl, 'https://') === 0) {
            return [$logoUrl];  // Stripe 要求是数组格式，最多支持 8 张图片
        }

        // 如果没有配置或无效，返回空数组（使用 Stripe Dashboard 中的默认设置）
        return [];
    }

    /**
     * 获取货币选项（用于多货币显示功能）
     *
     * @param string $primaryCurrency 主货币（网站显示的货币）
     * @param int $primaryAmount 主货币金额（最小单位）
     * @return array
     */
    private function getCurrencyOptions(string $primaryCurrency, int $primaryAmount): array
    {
        $primaryCurrency = strtoupper($primaryCurrency);
        $currencyOptions = [];

        // 定义货币组合：主货币 => 备选货币列表
        $currencyPairs = [
            'CNY' => ['USD', 'EUR', 'HKD'],  // 人民币可选美元、欧元、港币
            'USD' => ['CNY', 'EUR'],          // 美元可选人民币、欧元
            'EUR' => ['USD', 'CNY'],          // 欧元可选美元、人民币
            'HKD' => ['CNY', 'USD'],          // 港币可选人民币、美元
            'GBP' => ['USD', 'EUR'],          // 英镑可选美元、欧元
            'JPY' => ['USD', 'CNY'],          // 日元可选美元、人民币
            'SGD' => ['USD', 'CNY'],          // 新币可选美元、人民币
        ];

        // 如果主货币不在支持列表中，返回空
        if (!isset($currencyPairs[$primaryCurrency])) {
            Log::warning('货币不支持多货币选择功能', [
                'currency' => $primaryCurrency
            ]);
            return [];
        }

        // 获取备选货币列表
        $alternativeCurrencies = $currencyPairs[$primaryCurrency];

        // 为每个备选货币计算转换后的金额
        foreach ($alternativeCurrencies as $altCurrency) {
            try {
                // 获取汇率
                $rate = $this->getExchangeRate(strtolower($primaryCurrency), strtolower($altCurrency));

                if ($rate && $rate > 0) {
                    // 计算转换后的金额（四舍五入到最小单位）
                    $convertedAmount = (int) round($primaryAmount * $rate);

                    // 检查是否符合目标货币的最小金额要求
                    $minAmount = $this->getMinimumAmount($altCurrency);
                    if ($convertedAmount >= $minAmount) {
                        $currencyOptions[strtolower($altCurrency)] = [
                            'unit_amount' => $convertedAmount
                        ];

                        Log::info('添加备选货币', [
                            'from' => $primaryCurrency,
                            'to' => $altCurrency,
                            'from_amount' => $primaryAmount,
                            'to_amount' => $convertedAmount,
                            'rate' => $rate
                        ]);
                    } else {
                        Log::warning('转换后金额低于最小限制', [
                            'currency' => $altCurrency,
                            'converted_amount' => $convertedAmount,
                            'min_amount' => $minAmount
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('货币转换失败', [
                    'from' => $primaryCurrency,
                    'to' => $altCurrency,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $currencyOptions;
    }
}