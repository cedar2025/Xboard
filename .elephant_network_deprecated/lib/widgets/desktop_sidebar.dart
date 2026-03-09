import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/app_text_styles.dart';
import '../core/theme/app_dimensions.dart';
import '../core/theme/app_shadows.dart';
import '../providers/navigation_provider.dart';
import '../providers/vpn_provider.dart';
import '../core/singbox/vpn_state.dart';

/// macOS / 桌面端侧边栏导航组件
/// 替代移动端的底部浮动导航栏
class DesktopSidebar extends StatelessWidget {
  final NavigationPage currentPage;
  final Function(NavigationPage) onPageChanged;

  const DesktopSidebar({
    super.key,
    required this.currentPage,
    required this.onPageChanged,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    
    return Container(
      width: AppDimensions.sidebarWidth,
      decoration: BoxDecoration(
        color: isDark 
          ? AppColors.darkCard 
          : AppColors.lightCard,
        border: Border(
          right: BorderSide(
            color: isDark 
              ? AppColors.darkCardBorder 
              : AppColors.lightCardBorder,
            width: 1,
          ),
        ),
        boxShadow: isDark
          ? AppShadows.navigationDark
          : AppShadows.navigationLight,
      ),
      child: Column(
        children: [
          // macOS 标题栏安全区域
          const SizedBox(height: AppDimensions.desktopTopPadding),
          
          // Logo 区域
          _buildLogo(isDark),
          
          const SizedBox(height: AppDimensions.spacingXL),
          
          // 导航项
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppDimensions.spacingSmall,
              ),
              child: Column(
                children: [
                  _buildNavItem(
                    page: NavigationPage.dashboard,
                    icon: Icons.home_rounded,
                    label: '首页',
                    isDark: isDark,
                  ),
                  const SizedBox(height: AppDimensions.spacingXS),
                  _buildNavItem(
                    page: NavigationPage.shop,
                    icon: Icons.shopping_bag_rounded,
                    label: '订阅',
                    isDark: isDark,
                  ),
                  const SizedBox(height: AppDimensions.spacingXS),
                  _buildNavItem(
                    page: NavigationPage.profile,
                    icon: Icons.person_rounded,
                    label: '我的',
                    isDark: isDark,
                  ),
                ],
              ),
            ),
          ),
          
          // 底部VPN状态指示器
          _buildConnectionStatus(context, isDark),
          
          const SizedBox(height: AppDimensions.spacingMedium),
        ],
      ),
    );
  }

  /// Logo 区域
  Widget _buildLogo(bool isDark) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppDimensions.spacingMedium,
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Image.asset(
              'assets/images/logo_icon_macos.png',
              width: 48,
              height: 48,
            ),
          ),
          const SizedBox(width: AppDimensions.spacingSM),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '大象网络',
                  style: AppTextStyles.titleSmall.copyWith(
                    color: isDark 
                      ? AppColors.darkTextPrimary 
                      : AppColors.lightTextPrimary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  'Elephant Network',
                  style: AppTextStyles.labelTiny.copyWith(
                    color: isDark 
                      ? AppColors.darkTextTertiary 
                      : AppColors.lightTextSecondary,
                    fontSize: 9,
                    letterSpacing: 0.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// 导航项
  Widget _buildNavItem({
    required NavigationPage page,
    required IconData icon,
    required String label,
    required bool isDark,
  }) {
    final isActive = currentPage == page;
    
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => onPageChanged(page),
        borderRadius: AppDimensions.borderRadiusSmall,
        focusColor: isDark 
            ? AppColors.primaryLight.withValues(alpha: 0.12)
            : AppColors.primary.withValues(alpha: 0.08),
        hoverColor: isDark 
            ? AppColors.primaryLight.withValues(alpha: 0.12)
            : AppColors.primary.withValues(alpha: 0.08),
        highlightColor: Colors.transparent,
        splashColor: Colors.transparent,
        child: Container(
          height: 44,
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.spacingSM,
          ),
          decoration: BoxDecoration(
            color: isActive
              ? (isDark 
                  ? AppColors.primaryLight.withValues(alpha: 0.12)
                  : AppColors.primary.withValues(alpha: 0.08))
              : Colors.transparent,
            borderRadius: AppDimensions.borderRadiusSmall,
          ),
          child: Row(
            children: [
              Icon(
                icon,
                size: 20,
                color: isActive
                  ? (isDark ? AppColors.primaryLight : AppColors.primary)
                  : (isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary),
              ),
              const SizedBox(width: AppDimensions.spacingSM),
              Text(
                label,
                style: AppTextStyles.bodyMedium.copyWith(
                  color: isActive
                    ? (isDark ? AppColors.primaryLight : AppColors.primary)
                    : (isDark ? AppColors.darkTextSecondary : AppColors.lightTextPrimary),
                  fontWeight: isActive ? FontWeight.w600 : FontWeight.w400,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// 底部连接状态指示器
  Widget _buildConnectionStatus(BuildContext context, bool isDark) {
    return Consumer<VpnProvider>(
      builder: (context, vpnProvider, _) {
        final isConnected = vpnProvider.isConnected;
        final isProcessing = vpnProvider.isProcessing;
        
        String statusText;
        Color statusColor;
        IconData statusIcon;
        
        if (isProcessing) {
          statusText = '连接中...';
          statusColor = Colors.orange;
          statusIcon = Icons.sync_rounded;
        } else if (isConnected) {
          statusText = '已连接';
          statusColor = Colors.greenAccent;
          statusIcon = Icons.check_circle_rounded;
        } else {
          statusText = '未连接';
          statusColor = isDark 
            ? AppColors.darkTextTertiary 
            : AppColors.lightTextSecondary;
          statusIcon = Icons.circle_outlined;
        }
        
        return Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.spacingMedium,
          ),
          child: Container(
            padding: const EdgeInsets.symmetric(
              horizontal: AppDimensions.spacingSM,
              vertical: AppDimensions.spacingSmall,
            ),
            decoration: BoxDecoration(
              color: isDark
                ? AppColors.darkBackground.withValues(alpha: 0.5)
                : AppColors.lightBackground.withValues(alpha: 0.5),
              borderRadius: AppDimensions.borderRadiusSmall,
              border: Border.all(
                color: statusColor.withValues(alpha: 0.3),
              ),
            ),
            child: Row(
              children: [
                Icon(
                  statusIcon,
                  size: 14,
                  color: statusColor,
                ),
                const SizedBox(width: AppDimensions.spacingSmall),
                Text(
                  statusText,
                  style: AppTextStyles.labelTiny.copyWith(
                    color: statusColor,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
