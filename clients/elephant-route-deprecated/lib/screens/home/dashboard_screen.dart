import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/user_provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/vpn_provider.dart';
import '../../providers/node_provider.dart';
import '../../providers/app_update_provider.dart';
import '../../providers/language_provider.dart';
import '../../providers/navigation_provider.dart';
import '../../models/user.dart';
import '../../core/services/mac_runtime_service.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';
import '../../utils/helpers.dart';
import '../../utils/flag_helper.dart';
import '../../utils/platform_utils.dart';
import '../../core/singbox/vpn_state.dart';
import '../../widgets/app_update_dialog.dart';
import '../../widgets/dashboard_brand_header.dart';
import '../../widgets/mac_setup_guide.dart';
import 'node_selection_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  late AnimationController _pulseController;
  bool _isPowerButtonPressed = false;
  bool _isPowerActionPending = false;
  Timer? _syncTimer;

  @override
  void initState() {
    super.initState();
    // 添加生命周期监听器
    WidgetsBinding.instance.addObserver(this);

    // 加载用户数据
    Future.microtask(() {
      if (!mounted) return;
      if (context.read<AuthProvider>().isLoggedIn) {
        context.read<UserProvider>().refresh();
      }
    });

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) showMacSetupGuideIfNeeded(context);
    });

    // 定期同步后端数据（每 10 分钟），防止流量漏报
    _syncTimer = Timer.periodic(const Duration(minutes: 10), (timer) {
      if (mounted) {
        _syncWithBackend();
      }
    });

    // 脉动动画控制器
    _pulseController = AnimationController(
      duration: const Duration(milliseconds: 1500),
      vsync: this,
    );
    _pulseController.repeat(reverse: true);
  }

  /// 同步后端数据并处理未上报流量
  Future<void> _syncWithBackend() async {
    try {
      final userProvider = context.read<UserProvider>();
      final vpnProvider = context.read<VpnProvider>();

      // 刷新后端用户数据
      await userProvider.fetchUserInfo();
      final user = userProvider.user;

      if (user != null) {
        final unreportedTraffic = await vpnProvider.getUnreportedTraffic();
        final backendTotal = user.u + user.d;

        // 如果后端已统计的流量 >= 本地未上报的流量，说明后端已同步
        if (backendTotal >= unreportedTraffic) {
          await vpnProvider.clearUnreportedTraffic();
          debugPrint('后端数据已同步，清空未上报流量');
        }
      }
    } catch (e) {
      debugPrint('同步后端数据失败: $e');
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    // 当应用从后台返回前台时自动刷新数据
    if (state == AppLifecycleState.resumed && mounted) {
      context.read<UserProvider>().refresh();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _syncTimer?.cancel();
    _pulseController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor:
          isDark ? AppColors.darkBackground : AppColors.lightBackground,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => context.read<UserProvider>().refresh(),
          color: isDark ? AppColors.primaryLight : AppColors.primary,
          child: Consumer<UserProvider>(
            builder: (context, userProvider, _) {
              if (userProvider.isLoading && userProvider.user == null) {
                return Center(
                  child: CircularProgressIndicator(
                    color: isDark ? AppColors.primaryLight : AppColors.primary,
                  ),
                );
              }

              if (userProvider.errorMessage != null &&
                  userProvider.user == null) {
                return _buildErrorView(isDark, userProvider);
              }

              final user = userProvider.user;
              if (user == null) {
                return Center(
                  child: Text(
                    '无用户数据',
                    style: AppTextStyles.bodyLarge.copyWith(
                      color: isDark
                          ? AppColors.darkTextTertiary
                          : AppColors.lightTextTertiary,
                    ),
                  ),
                );
              }

              final pagePadding = PlatformUtils.isDesktop
                  ? AppDimensions.pagePadding
                  : const EdgeInsets.fromLTRB(18, 12, 18, 106);

              return SingleChildScrollView(
                physics: const ClampingScrollPhysics(),
                padding: pagePadding,
                child: Consumer<VpnProvider>(
                  builder: (context, vpnProvider, _) {
                    final vpnState = vpnProvider.state;

                    // 桌面端：单列居中精致布局
                    if (PlatformUtils.isDesktop) {
                      return Center(
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(
                              maxWidth: 520, minWidth: 400),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              _buildUserStatusCard(user, isDark,
                                  vpnState.isConnected, vpnProvider),
                              const SizedBox(height: 20),
                              _buildTrafficCard(user, isDark),
                              const SizedBox(height: 20),
                              _buildConnectionCard(isDark),
                              const SizedBox(height: 60),
                            ],
                          ),
                        ),
                      );
                    }

                    // 移动端：原有单列布局
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Header
                        _buildHeader(isDark),
                        const SizedBox(height: 10),

                        // 用户状态卡片
                        _buildUserStatusCard(
                            user, isDark, vpnState.isConnected, vpnProvider),
                        const SizedBox(height: AppDimensions.spacingLarge),

                        // 流量统计卡片
                        _buildTrafficCard(user, isDark),
                        const SizedBox(height: AppDimensions.spacingLarge),

                        // 连接控制卡片
                        _buildConnectionCard(isDark),
                      ],
                    );
                  },
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  /// 错误视图
  Widget _buildErrorView(bool isDark, UserProvider userProvider) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.error_outline,
            color: isDark ? AppColors.errorLight : AppColors.error,
            size: 64,
          ),
          const SizedBox(height: 16),
          Text(
            userProvider.errorMessage!,
            style: AppTextStyles.bodyLarge.copyWith(
              color: isDark
                  ? AppColors.darkTextSecondary
                  : AppColors.lightTextSecondary,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: () => userProvider.refresh(),
            child: const Text('重试'),
          ),
        ],
      ),
    );
  }

  /// Header
  Widget _buildHeader(bool isDark) {
    return DashboardBrandHeader(
      appName: context.read<LanguageProvider>().translate('app_name'),
      slogan: context.read<LanguageProvider>().translate('slogan'),
      isDark: isDark,
    );
  }

  /// 用户状态卡片
  Widget _buildUserStatusCard(
      dynamic user, bool isDark, bool isConnected, VpnProvider vpnProvider) {
    final userProvider = context.watch<UserProvider>();
    final plan = userProvider.subscribeInfo?['plan'];
    final planName = plan?['name'];
    final hasPlan = plan != null; // 简单判断是否有套餐对象
    final resetDate = _formatOptionalDate(user.nextResetAt, emptyText: '未设置');
    final expireDate = _formatOptionalDate(user.expiredAt, emptyText: '无限');

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: AppDimensions.borderRadiusLarge,
        boxShadow: AppShadows.getCard(isDark),
        border: Border.all(
          color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
        ),
        gradient: isDark
            ? LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  AppColors.darkCard,
                  AppColors.darkCardBorder.withValues(alpha: 0.1),
                ],
              )
            : null,
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // 第一行：邮箱 (完整显示)
                Text(
                  user.email,
                  style: AppTextStyles.headlineMedium.copyWith(
                    color: isDark
                        ? AppColors.darkTextPrimary
                        : AppColors.lightTextPrimary,
                    fontSize: 16,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 6),

                // 第二行：套餐类型
                Row(
                  mainAxisAlignment: MainAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 9, vertical: 3),
                      decoration: BoxDecoration(
                        color: isConnected
                            ? (isDark
                                ? AppColors.primaryUltraDark
                                : AppColors.primaryUltraLight)
                            : (isDark
                                ? AppColors.darkCardSecondary
                                : AppColors.slate50),
                        borderRadius:
                            BorderRadius.circular(AppDimensions.radiusFull),
                        border: Border.all(
                          color: isConnected
                              ? (isDark
                                  ? AppColors.primaryDark.withValues(alpha: 0.3)
                                  : AppColors.primaryLight)
                              : Colors.transparent,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.verified_user_rounded,
                            size: 12,
                            color: isDark
                                ? AppColors.primaryLight
                                : AppColors.primary,
                          ),
                          const SizedBox(width: 4),
                          Consumer2<UserProvider, LanguageProvider>(
                            builder:
                                (context, userProvider, languageProvider, _) {
                              String displayText;
                              if (!hasPlan) {
                                displayText = '未订阅';
                              } else {
                                displayText = planName ??
                                    languageProvider.translate('pro_member');
                              }

                              return Text(
                                displayText,
                                style: AppTextStyles.labelMedium.copyWith(
                                  color: isDark
                                      ? AppColors.primaryLight
                                      : AppColors.primary,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                _buildAccountDateLine(
                  label: '流量重置',
                  value: resetDate,
                  isDark: isDark,
                ),
                const SizedBox(height: 2),
                _buildAccountDateLine(
                  label: '套餐有效期',
                  value: expireDate,
                  isDark: isDark,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _formatOptionalDate(int? timestamp, {required String emptyText}) {
    if (timestamp == null || timestamp <= 0) {
      return emptyText;
    }
    return formatTimestamp(timestamp);
  }

  Widget _buildAccountDateLine({
    required String label,
    required String value,
    required bool isDark,
  }) {
    return Text(
      '$label $value',
      style: AppTextStyles.labelTiny.copyWith(
        color:
            isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
        fontSize: 11,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.2,
      ),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }

  /// 流量统计卡片
  Widget _buildTrafficCard(User user, bool isDark) {
    final vpnProvider = context.watch<VpnProvider>();

    return FutureBuilder<int>(
      future: vpnProvider.getUnreportedTraffic(),
      builder: (context, snapshot) {
        // 本次连接的实时流量
        final currentSession =
            vpnProvider.state.totalUp + vpnProvider.state.totalDown;

        // 未上报到后端的历史流量（从本地存储读取）
        final unreportedTraffic = snapshot.data ?? 0;

        final pendingTraffic = unreportedTraffic + currentSession;
        final breakdown = user.trafficBreakdown(pendingTraffic: pendingTraffic);
        final totalBytes = breakdown.effectiveTransferEnable;
        final usedBytes = (totalBytes - breakdown.effectiveRemainingTraffic)
            .clamp(0, totalBytes);
        final usedPercentage = breakdown.usedPercentage;
        final hasTrafficPackage = breakdown.trafficPackageRemaining > 0;

        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: isDark ? AppColors.darkCard : AppColors.lightCard,
            borderRadius: BorderRadius.circular(AppDimensions.radiusMedium),
            boxShadow: AppShadows.getCard(isDark),
            border: Border.all(
              color:
                  isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '流量资产',
                          style: AppTextStyles.labelMedium.copyWith(
                            color: isDark
                                ? AppColors.darkTextSecondary
                                : AppColors.lightTextSecondary,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          '剩余 ${formatBytes(breakdown.effectiveRemainingTraffic)}',
                          style: AppTextStyles.displaySmall.copyWith(
                            color: isDark
                                ? AppColors.darkTextPrimary
                                : AppColors.lightTextPrimary,
                            fontSize: 24,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: usedPercentage > 90
                          ? AppColors.error.withValues(alpha: 0.1)
                          : AppColors.primary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      '${usedPercentage.toStringAsFixed(1)}%',
                      style: TextStyle(
                        color: usedPercentage > 90
                            ? AppColors.error
                            : (isDark
                                ? AppColors.primaryLight
                                : AppColors.primary),
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                '优先使用套餐流量，套餐用完后自动使用流量包',
                style: AppTextStyles.bodySmall.copyWith(
                  color: isDark
                      ? AppColors.darkTextTertiary
                      : AppColors.lightTextSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 12),
              _buildSegmentedTrafficBar(breakdown, isDark),
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: _buildTrafficLegend(isDark)),
                  const SizedBox(width: 10),
                  _buildCompactTrafficTotals(
                    usedBytes: usedBytes,
                    totalBytes: totalBytes,
                    isDark: isDark,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _buildTrafficAssetTile(
                      title: '套餐流量',
                      value:
                          '剩余 ${formatBytes(breakdown.planRemainingTraffic)}',
                      caption:
                          '已用 ${formatBytes(breakdown.planUsedTraffic)} / ${formatBytes(breakdown.planTransferEnable)}',
                      color:
                          isDark ? AppColors.primaryLight : AppColors.primary,
                      icon: Icons.bolt_rounded,
                      isDark: isDark,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _buildTrafficAssetTile(
                      title: '流量包',
                      value: hasTrafficPackage
                          ? '剩余 ${formatBytes(breakdown.trafficPackageRemaining)}'
                          : '购买流量包',
                      caption: hasTrafficPackage
                          ? '已购 ${formatBytes(breakdown.trafficPackageTotal)}'
                          : '流量不够？',
                      color: AppColors.info,
                      icon: hasTrafficPackage
                          ? Icons.inventory_2_rounded
                          : Icons.add_shopping_cart_rounded,
                      isDark: isDark,
                      onTap: hasTrafficPackage
                          ? null
                          : () => context
                              .read<NavigationProvider>()
                              .setPage(NavigationPage.shop),
                      isCallToAction: !hasTrafficPackage,
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildSegmentedTrafficBar(TrafficBreakdown breakdown, bool isDark) {
    final total = breakdown.effectiveTransferEnable;
    final hasTotal = total > 0;
    final planUsedRatio =
        hasTotal ? (breakdown.planUsedTraffic / total).clamp(0.0, 1.0) : 0.0;
    final planRemainingRatio = hasTotal
        ? (breakdown.planRemainingTraffic / total).clamp(0.0, 1.0)
        : 0.0;
    final packageRatio = hasTotal
        ? (breakdown.trafficPackageRemaining / total).clamp(0.0, 1.0)
        : 0.0;

    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        return ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: SizedBox(
            height: 14,
            child: Stack(
              children: [
                Container(
                  color: isDark
                      ? AppColors.darkInputBackground
                      : AppColors.slate100,
                ),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 700),
                  width: width * (planUsedRatio + planRemainingRatio),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        (isDark ? AppColors.primaryDark : AppColors.primary)
                            .withValues(alpha: 0.92),
                        (isDark ? AppColors.primaryLight : AppColors.primary)
                            .withValues(alpha: 0.62),
                      ],
                    ),
                  ),
                ),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 700),
                  width: width * planUsedRatio,
                  color: isDark ? AppColors.primaryDark : AppColors.primary,
                ),
                Positioned(
                  left: width * (planUsedRatio + planRemainingRatio),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 700),
                    width: width * packageRatio,
                    height: 14,
                    color:
                        AppColors.info.withValues(alpha: isDark ? 0.85 : 0.7),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildTrafficLegend(bool isDark) {
    return Wrap(
      spacing: 12,
      runSpacing: 4,
      children: [
        _buildLegendDot('套餐优先', AppColors.primary, isDark),
        _buildLegendDot('流量包备用', AppColors.info, isDark),
      ],
    );
  }

  Widget _buildCompactTrafficTotals({
    required int usedBytes,
    required int totalBytes,
    required bool isDark,
  }) {
    final labelColor =
        isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary;
    final valueColor =
        isDark ? AppColors.darkTextSecondary : AppColors.lightTextSecondary;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Text(
          '总已用 ${formatBytes(usedBytes)}',
          style: AppTextStyles.labelTiny.copyWith(
            color: labelColor,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          '可用总量 ${formatBytes(totalBytes)}',
          style: AppTextStyles.labelTiny.copyWith(
            color: valueColor,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }

  Widget _buildLegendDot(String label, Color color, bool isDark) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(
          label,
          style: AppTextStyles.labelTiny.copyWith(
            color: isDark
                ? AppColors.darkTextTertiary
                : AppColors.lightTextSecondary,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }

  Widget _buildTrafficAssetTile({
    required String title,
    required String value,
    required String caption,
    required Color color,
    required IconData icon,
    required bool isDark,
    VoidCallback? onTap,
    bool isCallToAction = false,
  }) {
    final content = Container(
      constraints: const BoxConstraints(minHeight: 82),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: isDark
            ? AppColors.darkCardSecondary.withValues(alpha: 0.38)
            : AppColors.slate50,
        borderRadius: BorderRadius.circular(AppDimensions.radiusSmall),
        border: Border.all(
          color: isDark
              ? AppColors.darkCardBorder.withValues(alpha: 0.65)
              : AppColors.slate100,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 15, color: color),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  title,
                  style: AppTextStyles.labelSmall.copyWith(
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.lightTextSecondary,
                    fontWeight: FontWeight.w800,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            value,
            style: AppTextStyles.bodyLarge.copyWith(
              color: isCallToAction
                  ? color
                  : (isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.lightTextPrimary),
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 4),
          Text(
            caption,
            style: AppTextStyles.labelTiny.copyWith(
              color: isDark
                  ? AppColors.darkTextTertiary
                  : AppColors.lightTextTertiary,
              fontWeight: FontWeight.w600,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );

    if (onTap == null) {
      return content;
    }

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: content,
    );
  }

  /// 连接控制卡片 ⭐⭐⭐ 最核心的组件
  Widget _buildConnectionCard(bool isDark) {
    return Consumer<VpnProvider>(
      builder: (context, vpnProvider, _) {
        final vpnState = vpnProvider.state;
        final isConnected = vpnState.isConnected;
        final isProcessing = vpnState.isProcessing || _isPowerActionPending;

        return Container(
          constraints: const BoxConstraints(minHeight: 162),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: _getConnectionCardColor(isConnected, isDark),
            borderRadius: BorderRadius.circular(AppDimensions.radiusLarge),
            border: Border.all(
              color: _getConnectionCardBorderColor(isConnected, isDark),
              width: 1.4,
            ),
            boxShadow: _getConnectionCardShadow(isConnected, isDark),
            gradient: isConnected && isDark
                ? const RadialGradient(
                    center: Alignment(0, -0.4),
                    radius: 1.5,
                    colors: [
                      Color.fromRGBO(16, 185, 129, 0.08),
                      Colors.transparent,
                    ],
                  )
                : null,
          ),
          child: Stack(
            alignment: Alignment.center,
            children: [
              Positioned(
                top: -20,
                right: -20,
                child: Icon(
                  Icons.flash_on,
                  size: 96,
                  color: _getDecorationIconColor(isConnected, isDark),
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Container(
                                  width: 8,
                                  height: 8,
                                  decoration: BoxDecoration(
                                    color: isConnected
                                        ? (isDark
                                            ? AppColors.primaryLight
                                            : AppColors.primary)
                                        : AppColors.primaryLight,
                                    shape: BoxShape.circle,
                                    boxShadow: isConnected
                                        ? [
                                            BoxShadow(
                                              color: (isDark
                                                      ? AppColors.primaryLight
                                                      : AppColors.primary)
                                                  .withValues(alpha: 0.4),
                                              blurRadius: 6,
                                            ),
                                          ]
                                        : null,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  _isPowerActionPending
                                      ? '正在准备...'
                                      : _statusTitle(vpnProvider.state),
                                  style: AppTextStyles.displaySmall.copyWith(
                                    color:
                                        _getCardTitleColor(isConnected, isDark),
                                    fontSize: 20,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _isPowerActionPending
                                  ? '正在检查后台 TUN 网络组件，请稍候...'
                                  : _statusDescription(vpnProvider.state),
                              style: AppTextStyles.bodySmall.copyWith(
                                color: _getCardDescriptionColor(
                                    isConnected, isDark),
                              ),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 7),
                            Align(
                              alignment: Alignment.centerLeft,
                              child: _buildTunModeBadge(isDark),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),
                      _buildPowerButton(
                          isConnected, isProcessing, isDark, vpnProvider),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Align(
                    alignment: Alignment.center,
                    child: _buildNodeSelectionButton(isConnected, isDark),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildTunModeBadge(bool isDark) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: isDark
            ? AppColors.primary.withValues(alpha: 0.12)
            : AppColors.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: isDark
              ? AppColors.primaryLight.withValues(alpha: 0.22)
              : AppColors.primary.withValues(alpha: 0.18),
        ),
      ),
      child: Text(
        'TUN 模式',
        style: AppTextStyles.labelSmall.copyWith(
          color: isDark ? AppColors.primaryLight : AppColors.primaryDark,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  String _statusTitle(VpnState state) {
    switch (state.status) {
      case VpnStatus.connecting:
        return '正在连接...';
      case VpnStatus.coreStarting:
        return '内核启动中...';
      case VpnStatus.applyingProxy:
        return '正在应用代理...';
      case VpnStatus.disconnecting:
        return '正在断开...';
      case VpnStatus.restoreFailed:
        return '恢复失败';
      case VpnStatus.error:
        return '连接失败';
      case VpnStatus.connected:
        return '正在加速';
      case VpnStatus.disconnected:
        return '开启加速';
    }
  }

  String _statusDescription(VpnState state) {
    switch (state.status) {
      case VpnStatus.connecting:
      case VpnStatus.coreStarting:
      case VpnStatus.applyingProxy:
        return '正在处理网络配置，请稍候...';
      case VpnStatus.disconnecting:
        return '正在恢复本机网络设置...';
      case VpnStatus.restoreFailed:
        return state.errorMessage ?? '系统代理恢复失败，请到诊断页手动恢复。';
      case VpnStatus.error:
        return state.errorMessage ?? '连接未成功，请检查订阅、权限与本地网络。';
      case VpnStatus.connected:
        return '网络已加密，尽情畅游';
      case VpnStatus.disconnected:
        return '一键开启全球高速无界网络';
    }
  }

  Future<void> _handlePowerButtonTap(VpnProvider vpnProvider) async {
    if (_isPowerActionPending || vpnProvider.state.isProcessing) {
      return;
    }

    setState(() => _isPowerActionPending = true);
    if (vpnProvider.state.isConnected) {
      try {
        await vpnProvider.toggle();
      } finally {
        if (mounted) {
          setState(() => _isPowerActionPending = false);
        }
      }
      return;
    }

    try {
      final updateProvider = context.read<AppUpdateProvider>();
      final update = await updateProvider.checkForUpdate(silent: true);
      if (update != null && updateProvider.forceUpdateRequired) {
        if (mounted) {
          await showAppUpdateDialog(context, update);
        }
        return;
      }

      if (PlatformUtils.isMacOS) {
        final ready = await _ensureMacTunHelperReady();
        if (!ready) return;
      }

      await vpnProvider.toggle();
    } finally {
      if (mounted) {
        setState(() => _isPowerActionPending = false);
      }
    }
  }

  Future<bool> _ensureMacTunHelperReady() async {
    final runtime = MacRuntimeService.instance;
    final status = await runtime.getTunHelperStatus();
    if (status['status'] == 'enabled' && status['code'] == 'OK') {
      final result = await runtime.ensureTunHelper();
      if (result['status'] != 'enabled') {
        if (!mounted) return false;
        final message = (result['message'] as String?) ??
            (result['error'] as String?) ??
            '后台网络组件未启用，请在系统设置中允许后重试';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message)),
        );
        return false;
      }
      return true;
    }

    final bundledPlistExists = status['bundledPlistExists'] == true;
    final bundledHelperExists = status['bundledHelperExists'] == true;
    if (status['status'] == 'notFound' &&
        (!bundledPlistExists || !bundledHelperExists)) {
      if (!mounted) return false;
      final message =
          (status['message'] as String?) ?? '未在应用包中找到后台网络组件，请重新安装客户端';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
      return false;
    }

    if (status['status'] == 'requiresApproval') {
      if (!mounted) return false;
      final openSettings = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('允许后台网络组件'),
          content: Text(
            (status['message'] as String?) ?? '请在“登录项与扩展”中允许大象网络后台组件，然后返回重试。',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('取消'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('打开系统设置'),
            ),
          ],
        ),
      );
      if (openSettings == true) {
        await runtime.openSystemSettingsLoginItems();
      }
      return false;
    }

    if (status['status'] == 'refreshRequired') {
      final refreshed = await runtime.refreshTunHelper();
      return refreshed['status'] == 'enabled';
    }

    if (!mounted) return false;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('启用后台网络组件'),
        content: const Text(
          '首次使用 TUN 加速需要启用后台网络组件，用于接管本机全局流量。启用后，之后开关加速将尽量不再打扰你。',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('取消'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('启用'),
          ),
        ],
      ),
    );

    if (confirmed != true) return false;

    final result = await runtime.ensureTunHelper();
    if (result['status'] == 'enabled') {
      return true;
    }

    if (!mounted) return false;
    final message = (result['message'] as String?) ??
        (result['error'] as String?) ??
        '后台网络组件未启用，请在系统设置中允许后重试';
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
    return false;
  }

  /// 电源按钮 ⭐⭐⭐ 最核心的元素
  Widget _buildPowerButton(bool isConnected, bool isProcessing, bool isDark,
      VpnProvider vpnProvider) {
    return SizedBox(
      width: 86,
      height: 48,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTapDown: (_) => setState(() => _isPowerButtonPressed = true),
        onTapUp: (_) {
          setState(() => _isPowerButtonPressed = false);
        },
        onTap: () {
          if (!isProcessing) {
            _handlePowerButtonTap(vpnProvider);
          }
        },
        onTapCancel: () => setState(() => _isPowerButtonPressed = false),
        child: AnimatedScale(
          scale: _isPowerButtonPressed ? 0.94 : 1.0,
          duration: const Duration(milliseconds: 100),
          curve: Curves.easeOut,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            curve: Curves.easeOutCubic,
            padding: const EdgeInsets.all(5),
            decoration: BoxDecoration(
              color: isConnected
                  ? AppColors.primary
                  : (isDark ? AppColors.darkCardSecondary : AppColors.slate200),
              borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
              border: Border.all(
                color: isConnected
                    ? AppColors.primary.withValues(alpha: 0.2)
                    : (isDark ? AppColors.darkCardBorder : AppColors.slate100),
              ),
              boxShadow: [
                BoxShadow(
                  color: isConnected
                      ? AppColors.primary.withValues(alpha: 0.22)
                      : Colors.black.withValues(alpha: isDark ? 0.18 : 0.08),
                  blurRadius: isConnected ? 14 : 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: AnimatedAlign(
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOutCubic,
              alignment:
                  isConnected ? Alignment.centerRight : Alignment.centerLeft,
              child: Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.14),
                      blurRadius: 8,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: isProcessing
                    ? Center(
                        child: SizedBox(
                          width: 17,
                          height: 17,
                          child: CircularProgressIndicator(
                            color: isConnected
                                ? AppColors.primary
                                : AppColors.slate400,
                            strokeWidth: 2,
                          ),
                        ),
                      )
                    : null,
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// 节点选择按钮
  Widget _buildNodeSelectionButton(bool isConnected, bool isDark) {
    return Consumer<NodeProvider>(
      builder: (context, nodeProvider, _) {
        final isAutoMode = nodeProvider.isAutoMode;
        final selectedNodeName = nodeProvider.selectedNode?.name ?? '自动选择节点';
        final effectiveName = nodeProvider.effectiveNodeName;
        // 确定显示的国旗：自动模式用实际节点的国旗，手动模式用选中节点的国旗
        final flagName = isAutoMode ? effectiveName : selectedNodeName;
        final flagEmoji = getFlagEmoji(flagName);

        return GestureDetector(
          onTap: () {
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const NodeSelectionScreen()),
            );
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: _getNodeButtonBgColor(isConnected, isDark),
              border: Border.all(
                color: _getNodeButtonBorderColor(isConnected, isDark),
              ),
              borderRadius: BorderRadius.circular(AppDimensions.radiusMedium),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                // 国旗 / 自动模式图标
                Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    color: _getNodeIconBgColor(isConnected, isDark),
                    borderRadius: AppDimensions.borderRadiusSmall,
                  ),
                  child: Center(
                    child: isAutoMode
                        ? Text(
                            effectiveName.isNotEmpty ? flagEmoji : '⚡',
                            style: const TextStyle(fontSize: 18),
                          )
                        : Text(
                            flagEmoji,
                            style: const TextStyle(fontSize: 18),
                          ),
                  ),
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        isAutoMode ? '自动选择' : '当前节点',
                        style: AppTextStyles.labelTiny.copyWith(
                          color: _getNodeLabelColor(isConnected, isDark),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        isAutoMode
                            ? (effectiveName.isNotEmpty
                                ? effectiveName
                                : '等待测速...')
                            : selectedNodeName,
                        style: AppTextStyles.bodySmall.copyWith(
                          color: _getNodeNameColor(isConnected, isDark),
                          fontWeight: FontWeight.w700,
                        ),
                        overflow: TextOverflow.ellipsis,
                        maxLines: 1,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Icon(
                  Icons.chevron_right,
                  size: 16,
                  color: _getNodeChevronColor(isConnected, isDark),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // ==================== 颜色辅助方法 ====================

  Color _getConnectionCardColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppColors.darkBackground
          : AppColors.lightCard; // 浅色模式连接后背景保持不变
    }
    return isDark ? AppColors.darkCard : AppColors.lightCard;
  }

  Color _getConnectionCardBorderColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppColors.primary.withValues(alpha: 0.4)
          : AppColors.primaryLight;
    }
    return isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder;
  }

  List<BoxShadow> _getConnectionCardShadow(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppShadows.connectionConnectedDark
          : AppShadows.connectionConnectedLight;
    }
    return isDark
        ? AppShadows.connectionDisconnectedDark
        : AppShadows.connectionDisconnectedLight;
  }

  Color _getDecorationIconColor(bool isConnected, bool isDark) {
    if (isConnected && isDark) {
      return AppColors.primaryLight.withValues(alpha: 0.15);
    } else if (isConnected && !isDark) {
      // 修复：浅色模式下背景本身是白色，图标不能是白色，改为淡主题色
      return AppColors.primary.withValues(alpha: 0.15);
    } else if (!isConnected && isDark) {
      return AppColors.primaryLight.withValues(alpha: 0.1);
    } else {
      return AppColors.primaryDark.withValues(alpha: 0.04);
    }
  }

  Color _getCardTitleColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark ? Colors.white : AppColors.lightTextPrimary;
    }
    return isDark ? Colors.white : AppColors.lightTextPrimary;
  }

  Color _getCardDescriptionColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppColors.primaryLight.withValues(alpha: 0.6)
          : AppColors.lightTextSecondary;
    }
    return isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary;
  }

  Color _getNodeButtonBgColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppColors.darkCardSecondary.withValues(alpha: 0.5)
          : AppColors.slate50;
    }
    return isDark ? AppColors.darkCardSecondary : Colors.white;
  }

  Color _getNodeButtonBorderColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppColors.primary.withValues(alpha: 0.2)
          : AppColors.primaryLight.withValues(alpha: 0.2);
    }
    return isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder;
  }

  Color _getNodeIconBgColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppColors.primary.withValues(alpha: 0.2)
          : AppColors.primaryUltraLight;
    }
    return isDark ? AppColors.darkCard : AppColors.primaryUltraLight;
  }

  Color _getNodeLabelColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppColors.primaryLight.withValues(alpha: 0.4)
          : AppColors.lightTextSecondary;
    }
    return isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary;
  }

  Color _getNodeNameColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark ? AppColors.primaryLight : AppColors.lightTextPrimary;
    }
    return isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary;
  }

  Color _getNodeChevronColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark
          ? AppColors.primaryLight.withValues(alpha: 0.4)
          : AppColors.primary.withValues(alpha: 0.4);
    }
    return isDark ? AppColors.darkTextTertiary : AppColors.slate300;
  }
}
