<div style="background: linear-gradient(145deg, #f6f8fb 0%, #f0f3f7 100%); padding: 40px 20px;">
    <div style="max-width: 580px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(32, 43, 54, 0.08);">
        <!-- Logo区域 -->
        <div style="padding: 35px 40px; text-align: center; border-bottom: 1px solid #f1f3f5;">
            <h1 style="margin: 0; font-size: 22px; color: #2c3345; font-weight: 600; letter-spacing: -0.5px;">{{$name}}</h1>
        </div>

        <!-- 内容区 -->
        <div style="padding: 40px;">
            <div style="text-align: center; margin-bottom: 35px;">
                <h2 style="margin: 20px 0 0; font-size: 20px; color: #2c3345; font-weight: 600;">登录验证</h2>
            </div>

            <p style="margin: 0 0 30px; font-size: 15px; color: #4f566b; line-height: 1.7; text-align: center;">
                我们收到了新的登录请求，请点击下方按钮完成验证。
            </p>

            <div style="margin: 35px 0; text-align: center;">
                <a href="{{$link}}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(145deg, #2c3345 0%, #232838 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 500; transition: all 0.2s; box-shadow: 0 4px 12px rgba(44, 51, 69, 0.1);">确认登录</a>
            </div>

            <div style="margin: 40px 0 0; padding-top: 24px; border-top: 1px dashed #e9ecef;">
                <p style="margin: 0; font-size: 13px; color: #8792a2; line-height: 1.6; text-align: center;">
                    此链接将在 5 分钟后失效。如非本人操作，请忽略此邮件。
                </p>
            </div>
        </div>

        <!-- 底部 -->
        <div style="padding: 20px; background-color: #f9fafb; text-align: center;">
            <p style="margin: 0; font-size: 13px; color: #8792a2;">
                安全邮件提醒 · <a href="{{$url}}" style="color:#8792a2;">{{$name}}</a> · 版权所有
            </p>
        </div>
    </div>
</div>
