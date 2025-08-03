<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe 微信支付/支付宝 - 支付处理</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f6f9fc;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }
        .payment-methods {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 30px 0;
        }
        .payment-button {
            padding: 15px 30px;
            border: 2px solid #0570de;
            background: white;
            color: #0570de;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .payment-button:hover {
            background: #0570de;
            color: white;
        }
        .payment-button.wechat {
            border-color: #00c800;
            color: #00c800;
        }
        .payment-button.wechat:hover {
            background: #00c800;
            color: white;
        }
        .payment-button.alipay {
            border-color: #1677ff;
            color: #1677ff;
        }
        .payment-button.alipay:hover {
            background: #1677ff;
            color: white;
        }
        .status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            display: none;
        }
        .status.success {
            background: #d1edff;
            color: #0969da;
            border: 1px solid #b6e3ff;
        }
        .status.error {
            background: #ffe6e6;
            color: #d1242f;
            border: 1px solid #ffcccc;
        }
        .status.processing {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .loading {
            display: none;
            margin: 20px 0;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0570de;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .order-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: left;
        }
        .qr-container {
            display: none;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        #qr-code {
            margin: 20px auto;
            max-width: 300px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>选择支付方式</h1>
        
        <div class="order-info">
            <h3>订单信息</h3>
            <p><strong>订单号：</strong><span id="order-id">{{ $order['trade_no'] ?? '' }}</span></p>
            <p><strong>金额：</strong><span id="amount">{{ number_format(($order['total_amount'] ?? 0) / 100, 2) }}</span> <span id="currency">{{ $paymentData['currency'] ?? 'CNY' }}</span></p>
            <p><strong>商品：</strong><span id="description">订阅服务</span></p>
        </div>

        <div class="payment-methods" id="payment-methods">
            @if(in_array('wechat_pay', $paymentData['payment_methods'] ?? []))
            <button class="payment-button wechat" onclick="processPayment('wechat_pay')">
                💬 微信支付
            </button>
            @endif
            
            @if(in_array('alipay', $paymentData['payment_methods'] ?? []))
            <button class="payment-button alipay" onclick="processPayment('alipay')">
                💙 支付宝
            </button>
            @endif
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>正在处理支付...</p>
        </div>

        <div class="qr-container" id="qr-container">
            <h3>请使用手机扫描二维码支付</h3>
            <div id="qr-code"></div>
            <p>扫码后请按提示完成支付</p>
        </div>

        <div class="status" id="status"></div>
    </div>

    <script>
        const stripe = Stripe('{{ $paymentData["publishable_key"] ?? "" }}');
        const clientSecret = '{{ $paymentData["client_secret"] ?? "" }}';
        const returnUrl = '{{ $paymentData["return_url"] ?? "" }}';

        function showStatus(message, type) {
            const statusDiv = document.getElementById('status');
            statusDiv.textContent = message;
            statusDiv.className = `status ${type}`;
            statusDiv.style.display = 'block';
        }

        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
            document.getElementById('payment-methods').style.display = show ? 'none' : 'flex';
        }

        function showQRCode(qrData) {
            const qrContainer = document.getElementById('qr-container');
            const qrCodeDiv = document.getElementById('qr-code');
            
            // 简单的二维码显示（实际项目中可能需要使用QR码生成库）
            qrCodeDiv.innerHTML = `<img src="data:image/svg+xml;base64,${btoa(qrData)}" alt="QR Code" style="max-width: 100%;">`;
            qrContainer.style.display = 'block';
        }

        async function processPayment(paymentMethodType) {
            showLoading(true);
            showStatus('正在初始化支付...', 'processing');

            try {
                // 确认支付意图
                const result = await stripe.confirmPayment({
                    clientSecret: clientSecret,
                    payment_method: {
                        type: paymentMethodType,
                    },
                    return_url: returnUrl,
                    confirmation_method: 'automatic',
                });

                if (result.error) {
                    console.error('Payment confirmation error:', result.error);
                    showStatus('支付失败: ' + result.error.message, 'error');
                    showLoading(false);
                } else {
                    // 支付成功或需要进一步操作
                    const paymentIntent = result.paymentIntent;
                    
                    if (paymentIntent.status === 'succeeded') {
                        showStatus('支付成功！正在跳转...', 'success');
                        setTimeout(() => {
                            window.location.href = returnUrl;
                        }, 2000);
                    } else if (paymentIntent.status === 'requires_action') {
                        // 需要用户操作（如扫二维码）
                        showStatus('请按提示完成支付操作', 'processing');
                        
                        // 处理下一步操作
                        if (paymentIntent.next_action) {
                            handleNextAction(paymentIntent.next_action, paymentMethodType);
                        }
                    } else {
                        showStatus('支付状态: ' + paymentIntent.status, 'processing');
                    }
                    
                    showLoading(false);
                }
            } catch (error) {
                console.error('Payment processing error:', error);
                showStatus('支付处理失败: ' + error.message, 'error');
                showLoading(false);
            }
        }

        function handleNextAction(nextAction, paymentMethodType) {
            if (paymentMethodType === 'wechat_pay' && nextAction.wechat_pay_display_qr_code) {
                showQRCode(nextAction.wechat_pay_display_qr_code.data);
                showStatus('请使用微信扫描二维码完成支付', 'processing');
                
                // 轮询支付状态
                pollPaymentStatus();
            } else if (paymentMethodType === 'alipay' && nextAction.alipay_handle_redirect) {
                // Alipay重定向
                showStatus('正在跳转到支付宝...', 'processing');
                window.location.href = nextAction.alipay_handle_redirect.url;
            }
        }

        async function pollPaymentStatus() {
            // 每3秒检查一次支付状态
            const interval = setInterval(async () => {
                try {
                    const result = await stripe.retrievePaymentIntent(clientSecret);
                    const paymentIntent = result.paymentIntent;
                    
                    if (paymentIntent.status === 'succeeded') {
                        clearInterval(interval);
                        showStatus('支付成功！正在跳转...', 'success');
                        setTimeout(() => {
                            window.location.href = returnUrl;
                        }, 2000);
                    } else if (paymentIntent.status === 'canceled' || paymentIntent.status === 'payment_failed') {
                        clearInterval(interval);
                        showStatus('支付失败或已取消', 'error');
                    }
                } catch (error) {
                    console.error('Status polling error:', error);
                }
            }, 3000);
            
            // 10分钟后停止轮询
            setTimeout(() => {
                clearInterval(interval);
            }, 600000);
        }

        // 页面加载时检查URL参数
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('payment_intent')) {
                showStatus('正在验证支付结果...', 'processing');
                
                stripe.retrievePaymentIntent(clientSecret).then(function(result) {
                    const paymentIntent = result.paymentIntent;
                    
                    if (paymentIntent.status === 'succeeded') {
                        showStatus('支付成功！', 'success');
                        setTimeout(() => {
                            window.location.href = returnUrl;
                        }, 2000);
                    } else {
                        showStatus('支付未完成，状态: ' + paymentIntent.status, 'error');
                    }
                });
            }
        });
    </script>
</body>
</html>