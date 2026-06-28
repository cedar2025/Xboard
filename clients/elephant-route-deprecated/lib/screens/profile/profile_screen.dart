import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';
import '../../providers/user_provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/theme_provider.dart';
import '../../providers/language_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';
import '../../utils/constants.dart';
import '../../utils/platform_utils.dart';
import '../../utils/toast_utils.dart';
import '../../widgets/custom_webview.dart';
import 'change_password_screen.dart';
import 'about_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  static final Future<PackageInfo> _packageInfoFuture =
      PackageInfo.fromPlatform();

  @override
  Widget build(BuildContext context) {
    final userProvider = context.read<UserProvider>();
    final authProvider = context.read<AuthProvider>();
    if (authProvider.isLoggedIn &&
        userProvider.user == null &&
        !userProvider.isLoading &&
        userProvider.errorMessage == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (context.mounted) {
          context.read<UserProvider>().refresh();
        }
      });
    }

    final isDark = Theme.of(context).brightness == Brightness.dark;
    final tr = context.read<LanguageProvider>();

    return Scaffold(
      backgroundColor:
          isDark ? AppColors.darkBackground : AppColors.lightBackground,
      body: SafeArea(
        child: Column(
          children: [
            // 自定义顶部 Header，无论是否有用户数据都显示，保持统一
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    '个人中心',
                    style: AppTextStyles.titleMedium.copyWith(
                      color: isDark
                          ? AppColors.darkTextPrimary
                          : AppColors.lightTextPrimary,
                      fontSize: 20, // 增大字号匹配其他页面
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  InkWell(
                    onTap: () => _handleLogout(context),
                    borderRadius: BorderRadius.circular(20),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: isDark
                            ? AppColors.error.withValues(alpha: 0.1)
                            : AppColors.error.withValues(alpha: 0.05),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.logout_rounded,
                            color:
                                isDark ? AppColors.errorLight : AppColors.error,
                            size: 18,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            tr.translate('logout'),
                            style: AppTextStyles.labelMedium.copyWith(
                              color: isDark
                                  ? AppColors.errorLight
                                  : AppColors.error,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // 可滚动的用户信息区域
            Expanded(
              child: Consumer<UserProvider>(
                builder: (context, provider, child) {
                  final user = provider.user;
                  if (user == null) {
                    if (provider.errorMessage != null) {
                      return _buildLoadError(context, provider, isDark);
                    }
                    return Center(
                      child: CircularProgressIndicator(
                        color:
                            isDark ? AppColors.primaryLight : AppColors.primary,
                      ),
                    );
                  }

                  return SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
                    physics: const BouncingScrollPhysics(
                        parent: AlwaysScrollableScrollPhysics()),
                    child: Center(
                      child: ConstrainedBox(
                        constraints:
                            const BoxConstraints(maxWidth: 520, minWidth: 400),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // 头像与用户信息
                            _buildUserHeader(context, user, isDark),
                            const SizedBox(height: 32),

                            // 账号信息卡片
                            _buildInfoCard(context, user, isDark),
                            const SizedBox(height: 24),

                            // 设置信息卡片
                            _buildSettingsCard(context, isDark),
                            const SizedBox(height: 40),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLoadError(
      BuildContext context, UserProvider provider, bool isDark) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.cloud_off_rounded,
              size: 44,
              color: isDark
                  ? AppColors.darkTextTertiary
                  : AppColors.lightTextTertiary,
            ),
            const SizedBox(height: 16),
            Text(
              provider.errorMessage ?? '用户信息加载失败',
              textAlign: TextAlign.center,
              style: AppTextStyles.bodyMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextSecondary
                    : AppColors.lightTextSecondary,
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: provider.isLoading ? null : () => provider.refresh(),
              child: provider.isLoading
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('重试'),
            ),
          ],
        ),
      ),
    );
  }

  /// 用户 Header
  Widget _buildUserHeader(BuildContext context, dynamic user, bool isDark) {
    final tr = context.read<LanguageProvider>();
    return Column(
      children: [
        // 邮箱首字母标识
        Container(
          width: 100,
          height: 100,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [
                isDark ? AppColors.primaryDark : AppColors.primary,
                isDark ? AppColors.primary : AppColors.primaryLight,
              ],
            ),
            borderRadius: BorderRadius.circular(30),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.3),
                blurRadius: 20,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          alignment: Alignment.center,
          child: Text(
            user.emailInitial,
            style: AppTextStyles.price.copyWith(
              color: Colors.white,
              fontSize: 40,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
        const SizedBox(height: 20),

        // 邮箱
        Text(
          user.email,
          style: AppTextStyles.titleLarge.copyWith(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 12),

        // 会员标签
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
          decoration: BoxDecoration(
            color: isDark
                ? AppColors.primaryUltraDark.withValues(alpha: 0.3)
                : AppColors.primaryUltraLight,
            borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
            border: Border.all(
              color: isDark ? AppColors.primaryDark : AppColors.primary,
              width: 1,
            ),
          ),
          child: Consumer<UserProvider>(
            builder: (context, userProvider, _) {
              final plan = userProvider.subscribeInfo?['plan'];
              final planName = plan?['name'];
              final hasPlan = plan != null;

              String displayText;
              if (!hasPlan) {
                displayText = '未订阅';
              } else if (user.isExpired) {
                displayText = tr.translate('expiry');
              } else {
                displayText = planName ?? tr.translate('pro_member');
              }

              return Text(
                displayText,
                style: AppTextStyles.labelMedium.copyWith(
                  color:
                      isDark ? AppColors.primaryLight : AppColors.primaryDark,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 1,
                  fontSize: 13,
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  /// 账号信息卡片
  Widget _buildInfoCard(BuildContext context, dynamic user, bool isDark) {
    return Container(
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: AppDimensions.borderRadiusLarge,
        border: Border.all(
          color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
        ),
        boxShadow: AppShadows.getCard(isDark),
      ),
      child: Column(
        children: [
          _buildInfoItem(
            context,
            Icons.account_balance_wallet_outlined,
            '账号余额',
            '¥${(user.balance / 100).toStringAsFixed(2)}',
            isDark,
            isFirst: true,
          ),
          _buildDivider(isDark),
          Consumer<UserProvider>(
            builder: (context, provider, _) {
              final inviteCode = provider.inviteCode;
              return InkWell(
                onTap: () {
                  if (inviteCode != null && inviteCode.isNotEmpty) {
                    Clipboard.setData(ClipboardData(text: inviteCode));
                    ToastUtils.show(context, '已复制邀请码');
                  }
                },
                child: _buildInfoItem(
                  context,
                  Icons.group_add_outlined,
                  '我的邀请码',
                  inviteCode ?? '获取中...',
                  isDark,
                  showChevron: true,
                ),
              );
            },
          ),
          _buildDivider(isDark),
          _buildActionItem(
            context,
            Icons.lock_outline,
            '修改密码',
            '',
            isDark,
            () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const ChangePasswordScreen()),
            ),
            isLast: true,
          ),
        ],
      ),
    );
  }

  /// 统一处理 WebView 跳转 (自动登录)
  Future<void> _openWebPage(
      BuildContext context, String title, String path) async {
    final tr = context.read<LanguageProvider>();

    // 1. 显示加载弹窗
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => Center(
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const CircularProgressIndicator(),
                const SizedBox(height: 16),
                Text(tr.translate('connecting')),
              ],
            ),
          ),
        ),
      ),
    );

    try {
      final authProvider = context.read<AuthProvider>();
      String? quickLoginUrl = await authProvider.getQuickLoginUrl(path);

      // 关闭加载弹窗
      if (context.mounted) Navigator.of(context).pop();

      if (quickLoginUrl != null && context.mounted) {
        debugPrint('DEBUG: ----------------------------------------');
        debugPrint('DEBUG: Opening Webview with URL');
        debugPrint('DEBUG: Original QuickLoginUrl = $quickLoginUrl');
        // 检查 URL 中是否包含 auth_data
        final uri = Uri.parse(quickLoginUrl);
        final authData = uri.queryParameters['auth_data'];
        debugPrint(
            'DEBUG: URL auth_data param = ${authData?.substring(0, 10)}...');
        debugPrint('DEBUG: ----------------------------------------');

        // 增强替换逻辑：确保端口号也正确
        if (ApiConstants.baseUrl.contains('192.168.')) {
          final baseUri = Uri.parse(ApiConstants.baseUrl);
          final quickUri = Uri.parse(quickLoginUrl);

          // 构造新的 URL，强制使用 API 的 host 和 port
          // 注意：如果原 URL 没有端口（通常是因为 APP_URL 没配置端口），replace 会补上 API 正在使用的端口
          quickLoginUrl = quickUri
              .replace(
                scheme: baseUri.scheme,
                host: baseUri.host,
                port: baseUri.port,
              )
              .toString();
        }

        debugPrint('DEBUG: Refined QuickLoginUrl = $quickLoginUrl');

        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => CustomWebView(
              title: title,
              url: quickLoginUrl!,
            ),
          ),
        );
      } else if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('加载失败，请重试')),
        );
      }
    } catch (e) {
      if (context.mounted) {
        Navigator.of(context).pop(); // 以防万一，关闭弹窗
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('错误: $e')),
        );
      }
    }
  }

  /// 设置卡片
  Widget _buildSettingsCard(BuildContext context, bool isDark) {
    final trProvider = context.watch<LanguageProvider>();
    final tr = trProvider.translate;

    return Container(
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: AppDimensions.borderRadiusLarge,
        border: Border.all(
          color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
        ),
        boxShadow: AppShadows.getCard(isDark),
      ),
      child: Column(
        children: [
          _buildActionItem(
            context,
            Icons.receipt_long_outlined,
            tr('orders'),
            '查看历史',
            isDark,
            () => _openWebPage(context, tr('orders'), 'order'),
            isFirst: true,
          ),
          _buildDivider(isDark),
          _buildActionItem(
            context,
            Icons.support_agent_outlined,
            '我的工单',
            '问题咨询',
            isDark,
            () => _openWebPage(context, '我的工单', 'ticket'),
          ),
          _buildDivider(isDark),
          Consumer<ThemeProvider>(
            builder: (context, themeProvider, _) {
              return _buildActionItem(
                context,
                Icons.dark_mode_outlined,
                '主题模式',
                themeProvider.themeModeName,
                isDark,
                () => _showThemePicker(context, themeProvider, isDark),
              );
            },
          ),
          _buildDivider(isDark),
          FutureBuilder<PackageInfo>(
            future: _packageInfoFuture,
            builder: (context, snapshot) {
              final version =
                  snapshot.hasData ? 'v${snapshot.data!.version}' : '查看版本';
              return _buildActionItem(
                context,
                Icons.info_outline,
                '关于大象网络',
                version,
                isDark,
                () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const AboutScreen()),
                  );
                },
                isLast: true,
              );
            },
          ),
        ],
      ),
    );
  }

  /// 信息项
  Widget _buildInfoItem(
    BuildContext context,
    IconData icon,
    String label,
    String value,
    bool isDark, {
    bool isFirst = false,
    bool showChevron = false,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      child: Row(
        children: [
          Icon(
            icon,
            size: 20,
            color: isDark
                ? AppColors.darkTextSecondary
                : AppColors.lightTextSecondary,
          ),
          const SizedBox(width: 12),
          Text(
            label,
            style: AppTextStyles.bodyMedium.copyWith(
              color: isDark
                  ? AppColors.darkTextPrimary
                  : AppColors.lightTextPrimary,
            ),
          ),
          const Spacer(),
          Expanded(
            child: Text(
              value,
              style: AppTextStyles.bodyMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextSecondary
                    : AppColors.lightTextSecondary,
                fontWeight: FontWeight.w600,
              ),
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.right,
            ),
          ),
          if (showChevron) ...[
            const SizedBox(width: 8),
            Icon(
              Icons.content_copy,
              size: 14,
              color: isDark
                  ? AppColors.darkTextTertiary
                  : AppColors.lightTextTertiary,
            ),
          ],
        ],
      ),
    );
  }

  /// 操作项
  Widget _buildActionItem(
    BuildContext context,
    IconData icon,
    String label,
    String value,
    bool isDark,
    VoidCallback onTap, {
    bool isFirst = false,
    bool isLast = false,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.vertical(
        top: isFirst ? Radius.circular(AppDimensions.radiusLarge) : Radius.zero,
        bottom:
            isLast ? Radius.circular(AppDimensions.radiusLarge) : Radius.zero,
      ),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        child: Row(
          children: [
            Icon(
              icon,
              size: 20,
              color: isDark
                  ? AppColors.darkTextSecondary
                  : AppColors.lightTextSecondary,
            ),
            const SizedBox(width: 12),
            Text(
              label,
              style: AppTextStyles.bodyMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.lightTextPrimary,
              ),
            ),
            const Spacer(),
            Text(
              value,
              style: AppTextStyles.bodySmall.copyWith(
                color: isDark
                    ? AppColors.darkTextTertiary
                    : AppColors.lightTextSecondary,
              ),
            ),
            const SizedBox(width: 8),
            Icon(
              Icons.chevron_right,
              size: 16,
              color: isDark ? AppColors.darkTextTertiary : AppColors.slate300,
            ),
          ],
        ),
      ),
    );
  }

  /// 分隔线
  Widget _buildDivider(bool isDark) {
    return Container(
      height: 1,
      margin: const EdgeInsets.symmetric(horizontal: 16),
      color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
    );
  }

  /// 处理退出登录逻辑
  Future<void> _handleLogout(BuildContext context) async {
    final tr = context.read<LanguageProvider>();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(tr.translate('logout')),
        content: const Text('确定要退出登录吗？'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('取消'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(
              foregroundColor: AppColors.error,
            ),
            child: Text(tr.translate('logout')),
          ),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      await context.read<AuthProvider>().logout();
      if (context.mounted) {
        Navigator.of(context).pushReplacementNamed('/login');
      }
    }
  }

  /// 主题选择器
  /// 主题选择器
  void _showThemePicker(
      BuildContext context, ThemeProvider provider, bool isDark) {
    if (PlatformUtils.isDesktop) {
      showDialog(
        context: context,
        builder: (context) {
          return Dialog(
            backgroundColor: isDark ? AppColors.darkCard : AppColors.lightCard,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppDimensions.radiusLarge),
            ),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      '选择主题',
                      style: AppTextStyles.titleSmall.copyWith(
                        color: isDark
                            ? AppColors.darkTextPrimary
                            : AppColors.lightTextPrimary,
                      ),
                    ),
                    const SizedBox(height: 16),
                    _buildThemeOption(
                        context, provider, '跟随系统', ThemeMode.system, isDark),
                    _buildThemeOption(
                        context, provider, '浅色模式', ThemeMode.light, isDark),
                    _buildThemeOption(
                        context, provider, '深色模式', ThemeMode.dark, isDark),
                    const SizedBox(height: 16),
                  ],
                ),
              ),
            ),
          );
        },
      );
      return;
    }

    showModalBottomSheet(
      context: context,
      backgroundColor: isDark ? AppColors.darkCard : AppColors.lightCard,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(AppDimensions.radiusLarge),
        ),
      ),
      builder: (context) {
        return Container(
          padding: const EdgeInsets.symmetric(vertical: 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '选择主题',
                style: AppTextStyles.titleSmall.copyWith(
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.lightTextPrimary,
                ),
              ),
              const SizedBox(height: 16),
              _buildThemeOption(
                  context, provider, '跟随系统', ThemeMode.system, isDark),
              _buildThemeOption(
                  context, provider, '浅色模式', ThemeMode.light, isDark),
              _buildThemeOption(
                  context, provider, '深色模式', ThemeMode.dark, isDark),
              const SizedBox(height: 16),
            ],
          ),
        );
      },
    );
  }

  /// 主题选项
  Widget _buildThemeOption(
    BuildContext context,
    ThemeProvider provider,
    String title,
    ThemeMode mode,
    bool isDark,
  ) {
    final isSelected = provider.themeMode == mode;

    return ListTile(
      title: Text(
        title,
        style: AppTextStyles.bodyLarge.copyWith(
          color: isSelected
              ? (isDark ? AppColors.primaryLight : AppColors.primary)
              : (isDark
                  ? AppColors.darkTextPrimary
                  : AppColors.lightTextPrimary),
          fontWeight: isSelected ? FontWeight.w700 : FontWeight.w400,
        ),
      ),
      trailing: isSelected
          ? Icon(
              Icons.check,
              color: isDark ? AppColors.primaryLight : AppColors.primary,
            )
          : null,
      onTap: () {
        provider.setThemeMode(mode);
        Navigator.pop(context);
      },
    );
  }
}
