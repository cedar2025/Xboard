<div style="background: linear-gradient(145deg, #f6f8fb 0%, #f0f3f7 100%); padding: 40px 20px;">
    <div style="max-width: 580px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(32, 43, 54, 0.08);">
        <!-- Logo区域 -->
        <div style="padding: 35px 40px; text-align: center; border-bottom: 1px solid #f1f3f5;">
            <h1 style="margin: 0; font-size: 22px; color: #2c3345; font-weight: 600; letter-spacing: -0.5px;">{{$name}}</h1>
        </div>

        <!-- 内容区 -->
        <div style="padding: 40px;">
            <div style="text-align: center; margin-bottom: 35px;">
                <h2 style="margin: 20px 0 0; font-size: 20px; color: #2c3345; font-weight: 600;">到期提醒</h2>
            </div>

            <div style="margin: 35px 0; padding: 25px; background: linear-gradient(145deg, #f9fafb 0%, #f5f7f9 100%); border: 1px solid #e9ecef; border-radius: 12px;">
                <p style="margin: 0; font-size: 15px; color: #4f566b; line-height: 1.7;">
                    您的服务即将到期，为确保服务正常使用，建议及时处理。
                </p>
            </div>

            <div style="margin: 35px 0; text-align: center;">
                <a href="{{$url}}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(145deg, #2c3345 0%, #232838 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 500; transition: all 0.2s; box-shadow: 0 4px 12px rgba(44, 51, 69, 0.1);">立即处理</a>
            </div>
        </div>

        <!-- 底部 -->
        <div style="padding: 20px; background-color: #f9fafb; text-align: center;">
            <p style="margin: 0; font-size: 13px; color: #8792a2;">
                到期提醒 · <a href="{{$url}}" style="color:#8792a2;">{{$name}}</a> · 版权所有
            </p>
        </div>
    </div>
</div>
