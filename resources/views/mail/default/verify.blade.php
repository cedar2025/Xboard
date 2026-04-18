<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>邮箱验证码</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5;padding:40px 20px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
    <!-- Logo -->
    <tr><td style="padding-bottom:24px;text-align:center;">
        <span style="font-size:20px;font-weight:700;color:#18181b;">{{$name}}</span>
    </td></tr>
    <!-- Card -->
    <tr><td style="background:#ffffff;border-radius:12px;border:1px solid #e4e4e7;padding:40px;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td style="font-size:22px;font-weight:700;color:#18181b;padding-bottom:8px;">邮箱验证码</td></tr>
            <tr><td style="font-size:15px;color:#52525b;line-height:1.6;padding-bottom:28px;">请使用以下验证码完成验证，有效期 5 分钟。如非本人操作，请忽略此邮件。</td></tr>
            <tr><td align="center" style="padding-bottom:28px;">
                <div style="display:inline-block;background:#f4f4f5;border:1px solid #e4e4e7;border-radius:8px;padding:16px 40px;font-size:32px;font-weight:700;letter-spacing:6px;color:#18181b;font-family:'Courier New',Courier,monospace;">{{$code}}</div>
            </td></tr>
            <tr><td style="font-size:13px;color:#a1a1aa;line-height:1.5;">如果您没有请求此验证码，无需进行任何操作。</td></tr>
        </table>
    </td></tr>
    <!-- Footer -->
    <tr><td style="padding-top:24px;text-align:center;">
        <a href="{{$url}}" style="font-size:13px;color:#a1a1aa;text-decoration:none;">{{$url}}</a>
        <p style="font-size:12px;color:#d4d4d8;margin:8px 0 0;">此邮件由系统自动发送，请勿直接回复。</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
