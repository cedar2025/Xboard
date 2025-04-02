<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style type="text/css">
    .container {
        max-width: 600px;
        margin: 0 auto;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }
    .header {
        background: #415A94 !important;
        padding: 30px 0 !important;
        text-align: center !important;
        font-size: 28px !important;
        font-weight: bold;
        color: #fff !important;
        letter-spacing: 1px;
    }
    .content {
        padding: 40px !important;
        background: #ffffff;
    }
    .progress-container {
        width: 100%;
        height: 20px;
        background: #f3f4f6;
        border-radius: 10px;
        margin: 25px 0 35px;
        position: relative;
        overflow: hidden;
    }
    .progress-bar {
        width: 80%;
        height: 100%;
        background: linear-gradient(90deg, #f6ad55 0%, #fc8181 100%);
        border-radius: 10px;
        position: absolute;
        left: 0;
    }
    .progress-label {
        display: block;
        text-align: center;
        color: #e53e3e;
        font-weight: 600;
        font-size: 16px;
        margin: 15px 0;
    }
    .notification-title {
        font-size: 26px !important;
        color: #2d3748 !important;
        margin-bottom: 25px !important;
        font-weight: 600;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .primary-button {
        display: inline-flex;
        align-items: center;
        padding: 16px 40px;
        background: linear-gradient(135deg, #415A94 0%, #344b7d 100%);
        color: white !important;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        transition: transform 0.2s ease;
        margin: 25px 0;
        gap: 12px;
    }
    .primary-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(65,90,148,0.2);
    }
    .text-content {
        color: #4a5568 !important;
        line-height: 1.6;
        font-size: 15px;
        padding: 15px 0 !important;
    }
    .footer {
        background: #f7f7f7 !important;
        padding: 30px 40px !important;
        text-align: center;
        border-top: 1px solid #e4e4e4;
    }
</style>
</head>
<body style="background: #eee; margin: 0; padding: 40px 0;">
    <div class="container">
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <td class="header">{{$name}}</td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="content">
                        <div class="notification-title">
                            <i class="fas fa-tachometer-alt" style="color: #415A94; font-size: 28px;"></i>
                            流量使用通知
                        </div>
                        <p class="text-content">
                            尊敬的用户您好！<br><br>
                            您本月的流量使用情况如下：
                        </p>

                        <div class="progress-container">
                            <div class="progress-bar"></div>
                        </div>
                        <span class="progress-label">⚠️ 80% 流量已使用</span>

                        <p class="text-content">
                            当前剩余流量仅剩20%，为避免服务中断：<br>
                            • 请优化使用或立即升级套餐<br>
                            • 流量周期重置时间：每月按购买日期重置
                        </p>

                        <center>
                            <a href="https://shadowfly.net/#/stage/mysubs" class="primary-button">
                                <i class="fas fa-shopping-cart"></i>
                                立即管理套餐与流量
                            </a>
                        </center>
                    </td>
                </tr>
                <tr>
                    <td class="footer">
                        <p style="color: #999; font-size: 14px; margin: 0 0 10px;">
                            需要即时帮助？<br>
                            <a href="https://shadowfly.net/#/stage/ticket"
                               style="color: #415A94 !important; font-weight: 600; text-decoration: none;">
                                <i class="fas fa-life-ring"></i> 创建支持工单
                            </a>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
