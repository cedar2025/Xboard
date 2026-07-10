import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/app_dimensions.dart';
import '../core/theme/app_shadows.dart';
import '../core/theme/app_text_styles.dart';

class DashboardBrandHeader extends StatelessWidget {
  const DashboardBrandHeader({
    super.key,
    required this.appName,
    required this.slogan,
    required this.isDark,
  });

  final String appName;
  final String slogan;
  final bool isDark;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          key: const ValueKey('dashboard-brand-logo'),
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: isDark ? AppColors.darkCard : AppColors.lightCard,
            borderRadius: AppDimensions.borderRadiusMedium,
            boxShadow: AppShadows.getCard(isDark),
            border: Border.all(
              color:
                  isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
            ),
          ),
          child: SvgPicture.asset(
            'assets/images/logo.svg',
            width: 22,
            height: 22,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                appName,
                key: const ValueKey('dashboard-brand-name'),
                style: AppTextStyles.brandName.copyWith(
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.lightTextPrimary,
                  fontSize: 22,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                slogan,
                style: AppTextStyles.labelTiny.copyWith(
                  color: isDark ? AppColors.primaryLight : AppColors.primary,
                  letterSpacing: 1.2,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
