import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../core/services/invite_share_service.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/app_text_styles.dart';

class InviteShareSheet extends StatelessWidget {
  InviteShareSheet({
    super.key,
    required this.inviteUrl,
    required this.onCopySuccess,
    required this.onShareError,
    InviteShareService? shareService,
  }) : shareService = shareService ?? InviteShareService();

  final String inviteUrl;
  final VoidCallback onCopySuccess;
  final ValueChanged<String> onShareError;
  final InviteShareService shareService;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final actions = <Widget>[
      _ShareAction(
        label: '复制链接',
        icon: Icons.link_rounded,
        onTap: () async {
          await Clipboard.setData(ClipboardData(text: inviteUrl));
          if (context.mounted) Navigator.pop(context);
          onCopySuccess();
        },
      ),
      for (final platform in InviteSharePlatform.values)
        _ShareAction(
          label: platform.label,
          assetPath: platform.assetPath,
          onTap: () async {
            Navigator.pop(context);
            try {
              await shareService.shareInvite(
                platform: platform,
                inviteUrl: inviteUrl,
              );
            } on PlatformException {
              onShareError(platform.label);
            }
          },
        ),
    ];

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '邀请好友',
              style: AppTextStyles.titleMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.lightTextPrimary,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 18),
            GridView.count(
              crossAxisCount: 3,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 18,
              crossAxisSpacing: 12,
              childAspectRatio: 1.35,
              children: actions,
            ),
          ],
        ),
      ),
    );
  }
}

class _ShareAction extends StatelessWidget {
  const _ShareAction({
    required this.label,
    required this.onTap,
    this.icon,
    this.assetPath,
  });

  final String label;
  final Future<void> Function() onTap;
  final IconData? icon;
  final String? assetPath;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return InkWell(
      key: ValueKey('invite-share-$label'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 44,
            height: 44,
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: isDark ? AppColors.darkCardSecondary : AppColors.slate50,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: isDark
                    ? AppColors.darkCardBorder
                    : AppColors.lightBorderSecondary,
              ),
            ),
            child: assetPath != null
                ? SvgPicture.asset(assetPath!)
                : Icon(
                    icon,
                    color: isDark ? AppColors.primaryLight : AppColors.primary,
                  ),
          ),
          const SizedBox(height: 7),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTextStyles.labelMedium.copyWith(
              color: isDark
                  ? AppColors.darkTextPrimary
                  : AppColors.lightTextPrimary,
            ),
          ),
        ],
      ),
    );
  }
}
