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
    .custom-content {
        color: #4a5568 !important;
        line-height: 1.6;
        font-size: 15px;
        padding: 25px;
        background: #f8fafc;
        border-radius: 8px;
        margin: 20px 0;
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
        margin-top: 25px;
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
                            <i class="fas fa-bell" style="color: #415A94; font-size: 28px;"></i>
                            重要通知
                        </div>

                        <div class="custom-content">
                            <p style="margin: 0 0 20px; font-weight: 500;">尊敬的用户您好！</p>
                            <div style="border-left: 3px solid #c3dafe; padding-left: 20px;">
                                {!! nl2br($content) !!}
                            </div>
                        </div>

                        <center>
                            <a href="{{$url}}" class="button">
                                <i class="fas fa-arrow-right"></i>
                                返回{{$name}}
                            </a>
                        </center>
                    </td>
                </tr>
                <tr>
                    <td class="footer">
                        <p style="color: #999; font-size: 12px; margin: 0;">
                            需要帮助？访问我们的
                            <a href="https://shadowfly.net/#/stage/ticket"
                               style="color: #415A94 !important; text-decoration: none; font-weight: 500;">
                                <i class="fas fa-life-ring"></i> 帮助中心
                            </a>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
