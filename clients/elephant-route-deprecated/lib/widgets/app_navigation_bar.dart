import 'dart:ui';
import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';
import '../../providers/navigation_provider.dart';

class AppNavigationBar extends StatelessWidget {
  final NavigationPage currentPage;
  final Function(NavigationPage) onPageChanged;

  const AppNavigationBar({
    super.key,
    required this.currentPage,
    required this.onPageChanged,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Positioned(
      left: AppDimensions.spacingLarge,
      right: AppDimensions.spacingLarge,
      bottom: AppDimensions.navigationBarBottomPadding,
      child: ClipRRect(
        borderRadius: AppDimensions.borderRadiusLarge,
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 10, sigmaY: 10),
          child: Container(
            height: AppDimensions.navigationBarHeight,
            decoration: BoxDecoration(
              color: isDark
                  ? AppColors.darkCard.withValues(alpha: 0.8)
                  : AppColors.lightCard.withValues(alpha: 0.8),
              borderRadius: AppDimensions.borderRadiusLarge,
              border: Border.all(
                color: isDark
                    ? AppColors.darkCardBorder
                    : AppColors.lightCardBorder.withValues(alpha: 0.5),
              ),
              boxShadow: isDark
                  ? AppShadows.navigationDark
                  : AppShadows.navigationLight,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildNavItem(
                  page: NavigationPage.dashboard,
                  icon: Icons.home,
                  label: '首页',
                  isDark: isDark,
                ),
                _buildNavItem(
                  page: NavigationPage.shop,
                  icon: Icons.shopping_bag_outlined,
                  label: '订阅',
                  isDark: isDark,
                ),
                _buildNavItem(
                  page: NavigationPage.profile,
                  icon: Icons.person_outline,
                  label: '我的',
                  isDark: isDark,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem({
    required NavigationPage page,
    required IconData icon,
    required String label,
    required bool isDark,
  }) {
    final isActive = currentPage == page;

    return GestureDetector(
      onTap: () => onPageChanged(page),
      child: Container(
        width: 64,
        height: 64,
        color: Colors.transparent,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 24,
              color: isActive
                  ? (isDark ? AppColors.primaryLight : AppColors.primary)
                  : (isDark
                      ? AppColors.darkTextTertiary
                      : AppColors.lightTextSecondary),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: AppTextStyles.labelTiny.copyWith(
                color: isActive
                    ? (isDark ? AppColors.primaryLight : AppColors.primary)
                    : (isDark
                        ? AppColors.darkTextTertiary
                        : AppColors.lightTextSecondary),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
