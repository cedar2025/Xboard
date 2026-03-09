import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';

class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppColors.darkBackground : AppColors.lightBackground,
      appBar: AppBar(
        title: Text(
          '关于大象网络',
          style: TextStyle(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: Icon(
            Icons.arrow_back_ios_new,
            size: 20,
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: SingleChildScrollView(
            physics: const BouncingScrollPhysics(),
            padding: const EdgeInsets.only(bottom: 40),
            child: Column(
              children: [
                // 顶部 Logo / Slogan 区域
                _buildHeroSection(context, isDark),
                const SizedBox(height: 24),

                // 功能点区域
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '解决你的每一个痛点',
                        style: AppTextStyles.titleMedium.copyWith(
                          color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                          fontWeight: FontWeight.w900,
                          fontSize: 20,
                        ),
                      ),
                      const SizedBox(height: 16),
                      _buildFeatureCard(
                        context,
                        icon: Icons.g_mobiledata,
                        title: '注册 Google，不再拦截',
                        desc: '大象网络提供纯净住宅级 IP，风控评分极低，轻松完成注册，账号更稳定长久。',
                        isDark: isDark,
                      ),
                      const SizedBox(height: 12),
                      _buildFeatureCard(
                        context,
                        icon: Icons.smart_toy_outlined,
                        title: 'AI 工具稳定不掉线',
                        desc: '针对 OpenAI、Anthropic 等主流 AI 平台进行专线优化，低延迟、零封锁，让你的工作流不再被中断。',
                        isDark: isDark,
                      ),
                      const SizedBox(height: 12),
                      _buildFeatureCard(
                        context,
                        icon: Icons.four_k_outlined,
                        title: 'Netflix 4K 想看就看',
                        desc: '解锁主流流媒体完整内容库，支持 4K 高清，无缓冲畅享国际版流媒体。',
                        isDark: isDark,
                      ),
                      const SizedBox(height: 40),

                      // 用户评价区域
                      Text(
                        '他们都在用',
                        style: AppTextStyles.titleMedium.copyWith(
                          color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                          fontWeight: FontWeight.w900,
                          fontSize: 20,
                        ),
                      ),
                      const SizedBox(height: 16),
                      _buildTestimonialCard(
                        context,
                        quote: '以前总担心 Google 账号被封，现在用大象的低风控住宅 IP，注册、登录一路畅通。',
                        author: 'SaaS 产品经理',
                        isDark: isDark,
                      ),
                      const SizedBox(height: 12),
                      _buildTestimonialCard(
                        context,
                        quote: '跑 TikTok 账号最怕网络不稳，切到大象之后，直播不掉线，视频发布秒传。',
                        author: '短视频运营',
                        isDark: isDark,
                      ),
                      const SizedBox(height: 12),
                      _buildTestimonialCard(
                        context,
                        quote: '在咖啡厅远程办公最受不了网络不给力，大象的客户端一键连接，稳定且性价比极高。',
                        author: '数字游民',
                        isDark: isDark,
                      ),
                    ],
                  ),
                ),
                
                const SizedBox(height: 48),
                // Footer
                Text(
                  '© 2024 大象网络 Inc.\nCONNECT THE UNSEEN',
                  textAlign: TextAlign.center,
                  style: AppTextStyles.labelSmall.copyWith(
                    color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeroSection(BuildContext context, bool isDark) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 24),
      decoration: BoxDecoration(
        color: isDark ? AppColors.primaryUltraDark : AppColors.primaryUltraLight,
        borderRadius: const BorderRadius.only(
          bottomLeft: Radius.circular(40),
          bottomRight: Radius.circular(40),
        ),
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: isDark ? AppColors.primaryDark : AppColors.primary,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: (isDark ? AppColors.primaryDark : AppColors.primaryLight).withOpacity(0.5),
                  blurRadius: 30,
                  offset: const Offset(0, 10),
                )
              ],
            ),
            child: const Icon(
              Icons.vpn_lock,
              size: 48,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 24),
          Text(
            '大象网络',
            style: AppTextStyles.displaySmall.copyWith(
              color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
              fontWeight: FontWeight.w900,
              fontSize: 28,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            '专为极客打造的下一代全球网络加速服务。\n突破物理边界，重塑数字自由。',
            textAlign: TextAlign.center,
            style: AppTextStyles.bodyMedium.copyWith(
              color: isDark ? AppColors.darkTextSecondary : AppColors.lightTextSecondary,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFeatureCard(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String desc,
    required bool isDark,
  }) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: AppDimensions.borderRadiusLarge,
        border: Border.all(
          color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
        ),
        boxShadow: AppShadows.getCard(isDark),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: isDark ? AppColors.primaryDark.withOpacity(0.15) : AppColors.primaryUltraLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              icon,
              size: 24,
              color: isDark ? AppColors.primaryLight : AppColors.primary,
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: AppTextStyles.headlineSmall.copyWith(
                    color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  desc,
                  style: AppTextStyles.bodySmall.copyWith(
                    color: isDark ? AppColors.darkTextSecondary : AppColors.lightTextSecondary,
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTestimonialCard(
    BuildContext context, {
    required String quote,
    required String author,
    required bool isDark,
  }) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.format_quote_rounded,
            size: 24,
            color: isDark ? AppColors.darkTextTertiary : AppColors.slate300,
          ),
          const SizedBox(height: 8),
          Text(
            quote,
            style: AppTextStyles.bodyMedium.copyWith(
              color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
              fontStyle: FontStyle.italic,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            '— $author',
            style: AppTextStyles.labelMedium.copyWith(
              color: isDark ? AppColors.primaryLight : AppColors.primary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
