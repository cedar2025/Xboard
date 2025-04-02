<!DOCTYPE html>
<html>
<head>
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
    .title {
        font-size: 26px !important;
        color: #2d3748 !important;
        margin-bottom: 30px !important;
        font-weight: 600;
        text-align: center;
    }
    .code-box {
        background: #f8f9fa;
        padding: 25px;
        margin: 30px 0;
        text-align: center;
        border-radius: 6px;
        font-size: 36px;
        font-weight: bold;
        color: #415A94;
        letter-spacing: 3px;
        border: 2px dashed #e2e8f0;
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
    }
    .button:hover {
        background-color: #344b7d;
        text-decoration: underline;
    }
    .text-muted {
        color: #718096 !important;
        line-height: 1.6;
        font-size: 15px;
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
                        <h1 class="title">邮箱验证码</h1>
                        <p class="text-muted">
                            尊敬的用户您好！<br><br>
                            您的验证码是：
                        </p>
                        <div class="code-box">{{$code}}</div>
                        <p class="text-muted">
                            请在 5 分钟内进行验证。<br>
                            如果该验证码不为您本人申请，请无视。
                        </p>
                    </td>
                </tr>
                <tr>
                    <td class="footer">
                        <a href="{{$url}}" class="button">返回{{$name}}首页</a>
                        <p style="margin-top: 20px; color: #999; font-size: 12px;">
                            如果按钮无法点击，请复制以下链接到浏览器：<br>
                            {{$url}}
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
