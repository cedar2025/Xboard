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
    .urgent-title {
        font-size: 26px !important;
        color: #e53e3e !important;
        margin-bottom: 25px !important;
        font-weight: 600;
        text-align: center;
        padding: 15px;
        border: 2px solid #fed7d7;
        border-radius: 8px;
        background: #fff5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .warning-icon {
        font-size: 28px;
        color: #e53e3e;
    }
    .footer {
        background: #f7f7f7 !important;
        padding: 30px 40px !important;
        text-align: center;
        border-top: 1px solid #e4e4e4;
    }
    .button {
        display: inline-block;
        padding: 12px 30px;
        background-color: #415A94;
        color: white !important;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 500;
        font-size: 15px;
        transition: background-color 0.3s ease;
        margin: 20px 0;
    }
    .button:hover {
        background-color: #344b7d;
        text-decoration: underline;
    }
    .text-content {
        color: #4a5568 !important;
        line-height: 1.6;
        font-size: 15px;
        padding: 20px 0 !important;
    }
    .highlight {
        color: #e53e3e;
        font-weight: 600;
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
                        <div class="urgent-title">
                            <i class="fas fa-exclamation-triangle warning-icon"></i>
                            到期通知
                        </div>
                        <p class="text-content">
                            尊敬的用户您好！<br><br>
                            您的服务将在<span class="highlight">24小时内到期</span>。为了不影响正常使用，请及时续费。
                        </p>
                        <p class="text-content">
                            如果已完成续费，请忽略此邮件。<br>
                            感谢您选择{{$name}}！
                        </p>
                        <center>
                            <a href="{{$url}}" class="button">立即续费</a>
                        </center>
                    </td>
                </tr>
                <tr>
                    <td class="footer">
                        <p style="color: #999; font-size: 12px; margin: 0;">
                            如需帮助，请联系我们：
                            <a href="https://shadowfly.net/#/stage/ticket">Shadowfly Support</a>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
