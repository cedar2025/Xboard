import 'dart:ui';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../../providers/user_provider.dart';
import '../../providers/vpn_provider.dart';
import '../../providers/node_provider.dart';
import '../../providers/language_provider.dart';
import '../../models/user.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';
import '../../utils/helpers.dart';
import '../../utils/avatar_helper.dart';
import '../../utils/flag_helper.dart';
import '../../utils/platform_utils.dart';
import '../../core/singbox/vpn_state.dart';
import 'node_selection_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  late AnimationController _pulseController;
  late Animation<double> _pulseAnimation;
  bool _isPowerButtonPressed = false;
  Timer? _syncTimer;

  @override
  void initState() {
    super.initState();
    // 添加生命周期监听器
    WidgetsBinding.instance.addObserver(this);
    
    // 加载用户数据
    Future.microtask(() {
      context.read<UserProvider>().refresh();
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
    _pulseAnimation = Tween<double>(begin: 0.3, end: 1.0).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
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
      backgroundColor: isDark ? AppColors.darkBackground : AppColors.lightBackground,
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

              if (userProvider.errorMessage != null && userProvider.user == null) {
                return _buildErrorView(isDark, userProvider);
              }

              final user = userProvider.user;
              if (user == null) {
                return Center(
                  child: Text(
                    '无用户数据',
                    style: AppTextStyles.bodyLarge.copyWith(
                      color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
                    ),
                  ),
                );
              }

              return SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(parent: ClampingScrollPhysics()),
                padding: AppDimensions.pagePadding,
                child: Consumer<VpnProvider>(
                  builder: (context, vpnProvider, _) {
                    final vpnState = vpnProvider.state;
                    
                    // 桌面端：单列居中精致布局
                    if (PlatformUtils.isDesktop) {
                      return Center(
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 520, minWidth: 400),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              _buildUserStatusCard(user, isDark, vpnState.isConnected, vpnProvider),
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
                        const SizedBox(height: 16),

                        // 用户状态卡片
                        _buildUserStatusCard(user, isDark, vpnState.isConnected, vpnProvider),
                        const SizedBox(height: 8),

                    // 流量统计卡片
                    _buildTrafficCard(user, isDark),
                    const SizedBox(height: 8),

                    // 连接控制卡片
                    _buildConnectionCard(isDark),
                    
                    const SizedBox(height: 80), // 底部导航栏留白
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
              color: isDark ? AppColors.darkTextSecondary : AppColors.lightTextSecondary,
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
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // 品牌名称
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.read<LanguageProvider>().translate('app_name'),
              style: AppTextStyles.brandName.copyWith(
                color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                fontSize: 24,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              context.read<LanguageProvider>().translate('slogan'),
              style: AppTextStyles.labelTiny.copyWith(
                color: isDark ? AppColors.primaryLight : AppColors.primary,
                letterSpacing: 1.2,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        // Shield 图标
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: isDark ? AppColors.darkCard : AppColors.lightCard,
            borderRadius: AppDimensions.borderRadiusMedium,
            boxShadow: AppShadows.getCard(isDark),
            border: Border.all(
              color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
            ),
          ),
          child: SvgPicture.asset(
            'assets/images/logo.svg',
            width: 24,
            height: 24,
          ),
        ),
      ],
    );
  }

  /// 用户状态卡片
  Widget _buildUserStatusCard(dynamic user, bool isDark, bool isConnected, VpnProvider vpnProvider) {
    final userProvider = context.watch<UserProvider>();
    final plan = userProvider.subscribeInfo?['plan'];
    final planName = plan?['name'];
    final hasPlan = plan != null; // 简单判断是否有套餐对象
    final daysRemaining = getDaysRemaining(user.expiredAt);
    final isExpired = daysRemaining <= 0;

    return Container(
      padding: const EdgeInsets.all(16),
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
                AppColors.darkCardBorder.withOpacity(0.1),
              ],
            )
          : null,
      ),
      child: Row(
        children: [
          // 头像
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  isDark ? AppColors.primaryDark : AppColors.primary,
                  isDark ? AppColors.primary : AppColors.primaryLight,
                ],
              ),
              borderRadius: AppDimensions.borderRadiusMedium,
              boxShadow: [
                BoxShadow(
                  color: AppColors.primary.withOpacity(0.3),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Consumer<UserProvider>(
              builder: (context, provider, _) {
                 final avatarUrl = provider.avatarUrl;
                 return ClipRRect(
                  borderRadius: AppDimensions.borderRadiusMedium,
                  child: Image.network(
                    avatarUrl,
                    fit: BoxFit.cover,
                    loadingBuilder: (context, child, loadingProgress) {
                      if (loadingProgress == null) return child;
                       return Center(
                        child: Text(
                          user.email[0].toUpperCase(),
                          style: AppTextStyles.titleLarge.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      );
                    },
                    errorBuilder: (context, error, stackTrace) {
                      return Center(
                        child: Text(
                          user.email[0].toUpperCase(),
                          style: AppTextStyles.titleLarge.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      );
                    },
                  ),
                 );
              },
            ),
          ),
          const SizedBox(width: 16),
          
          // 右侧信息区域
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // 第一行：邮箱 (完整显示)
                Text(
                  user.email,
                  style: AppTextStyles.headlineMedium.copyWith(
                    color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                    fontSize: 16,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 8),
                
                // 第二行：套餐类型 + 有效期 (靠左紧凑分布)
                Row(
                  mainAxisAlignment: MainAxisAlignment.start,
                  children: [
                    // 左侧：套餐类型 Badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: isConnected 
                          ? (isDark ? AppColors.primaryUltraDark : AppColors.primaryUltraLight)
                          : (isDark ? AppColors.darkCardSecondary : AppColors.slate50),
                        borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
                        border: Border.all(
                          color: isConnected
                            ? (isDark ? AppColors.primaryDark.withOpacity(0.3) : AppColors.primaryLight)
                            : Colors.transparent,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.verified_user_rounded,
                            size: 12,
                            color: isDark ? AppColors.primaryLight : AppColors.primary,
                          ),
                          const SizedBox(width: 4),
                          Consumer2<UserProvider, LanguageProvider>(
                            builder: (context, userProvider, languageProvider, _) {
                              String displayText;
                              if (!hasPlan) {
                                displayText = '未订阅';
                              } else {
                                displayText = planName ?? languageProvider.translate('pro_member');
                              }
                              
                              return Text(
                                displayText,
                                style: AppTextStyles.labelMedium.copyWith(
                                  color: isDark ? AppColors.primaryLight : AppColors.primary,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),

                    // 右侧：有效期至
                    if (!isExpired && hasPlan)
                      Expanded(
                        child: Text(
                          '有效期至 ${user.expiredAt != null ? formatTimestamp(user.expiredAt!) : "无限"}',
                          style: AppTextStyles.labelTiny.copyWith(
                            color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 0.5,
                          ),
                          textAlign: TextAlign.left,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// 流量统计卡片
  Widget _buildTrafficCard(User user, bool isDark) {
    final vpnProvider = context.watch<VpnProvider>();
    
    return FutureBuilder<int>(
      future: vpnProvider.getUnreportedTraffic(),
      builder: (context, snapshot) {
        // 本次连接的实时流量
        final currentSession = vpnProvider.state.totalUp + vpnProvider.state.totalDown;
        
        // 未上报到后端的历史流量（从本地存储读取）
        final unreportedTraffic = snapshot.data ?? 0;
        
        // 总已用流量 = 面板基线（user.u + user.d） + 未上报流量 + 本次会话流量
        final totalUsedBytes = user.u + user.d + unreportedTraffic + currentSession;
        final totalBytes = user.transferEnable;
        
        final usedPercentage = (totalUsedBytes / totalBytes * 100).clamp(0.0, 100.0);
        final totalGB = formatBytes(totalBytes);
        final usedGB = formatBytes(totalUsedBytes);
        final remainingGB = formatBytes((totalBytes - totalUsedBytes).clamp(0, totalBytes));

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: AppDimensions.borderRadiusLarge,
        boxShadow: AppShadows.getCard(isDark),
        border: Border.all(
          color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // 标题与百分比
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '流量使用统计',
                style: AppTextStyles.headlineMedium.copyWith(
                  color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                  fontWeight: FontWeight.bold,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: usedPercentage > 90 
                    ? AppColors.error.withOpacity(0.1) 
                    : AppColors.primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  '${usedPercentage.toStringAsFixed(1)}%',
                  style: TextStyle(
                    color: usedPercentage > 90 ? AppColors.error : (isDark ? AppColors.primaryLight : AppColors.primary),
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // 进度条
          Stack(
            children: [
              Container(
                height: 12,
                width: double.infinity,
                decoration: BoxDecoration(
                  color: isDark ? AppColors.darkInputBackground : AppColors.slate100,
                  borderRadius: BorderRadius.circular(6),
                ),
              ),
              AnimatedContainer(
                duration: const Duration(milliseconds: 1000),
                height: 12,
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    return Container(
                      width: constraints.maxWidth * (usedPercentage / 100).clamp(0.0, 1.0),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            isDark ? AppColors.primaryDark : AppColors.primary,
                            isDark ? AppColors.primary : AppColors.primaryLight,
                          ],
                        ),
                        borderRadius: BorderRadius.circular(6),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withOpacity(0.3),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // 数据统计
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _buildTrafficStat('已用', usedGB, isDark),
              _buildTrafficStat('总量', totalGB, isDark),
              _buildTrafficStat('剩余', remainingGB, isDark, isHighlight: true),
            ],
          ),

        ],
      ),
    );
      },
    );
  }

  Widget _buildTrafficStat(String label, String value, bool isDark, {bool isHighlight = false}) {
    return Column(
      crossAxisAlignment: isHighlight ? CrossAxisAlignment.end : CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTextStyles.labelSmall.copyWith(
            color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
            fontSize: 11,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: AppTextStyles.bodyLarge.copyWith(
            color: isHighlight
                ? (isDark ? AppColors.primaryLight : AppColors.primaryDark)
                : (isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary),
            fontWeight: FontWeight.w800,
            fontSize: 14,
          ),
        ),
      ],
    );
  }

  /// 连接控制卡片 ⭐⭐⭐ 最核心的组件
  Widget _buildConnectionCard(bool isDark) {
    return Consumer<VpnProvider>(
      builder: (context, vpnProvider, _) {
        final vpnState = vpnProvider.state;
        final isConnected = vpnState.isConnected;
        final isProcessing = vpnState.isProcessing;

        return Container(
          constraints: const BoxConstraints(minHeight: 260),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          decoration: BoxDecoration(
            color: _getConnectionCardColor(isConnected, isDark),
            borderRadius: BorderRadius.circular(AppDimensions.radiusXXL),
            border: Border.all(
              color: _getConnectionCardBorderColor(isConnected, isDark),
              width: 2,
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
                top: 0,
                right: 0,
                child: Icon(
                  Icons.flash_on,
                  size: 180,
                  color: _getDecorationIconColor(isConnected, isDark),
                ),
              ),

              Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(
                      color: isConnected 
                        ? (isDark ? AppColors.primaryLight : AppColors.primary)
                        : AppColors.primaryLight,
                      shape: BoxShape.circle,
                      boxShadow: isConnected
                        ? [
                            BoxShadow(
                              color: (isDark ? AppColors.primaryLight : AppColors.primary).withOpacity(0.4),
                              blurRadius: 6,
                            ),
                          ]
                        : null,
                    ),
                  ),

                  Text(
                    isProcessing 
                      ? (vpnProvider.state.status == VpnStatus.connecting ? '正在连接...' : '正在断开...') 
                      : (isConnected ? '正在加速' : '开启加速'),
                    style: AppTextStyles.displaySmall.copyWith(
                      color: _getCardTitleColor(isConnected, isDark),
                      fontSize: 24,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    isProcessing
                      ? '正在处理网络配置，请稍候...'
                      : (isConnected ? '网络已加密，尽情畅游' : '一键开启全球高速无界网络'),
                    style: AppTextStyles.bodySmall.copyWith(
                      color: _getCardDescriptionColor(isConnected, isDark),
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),

                  _buildPowerButton(isConnected, isProcessing, isDark, vpnProvider),
                  const SizedBox(height: 20),

                  _buildNodeSelectionButton(isConnected, isDark),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  /// 电源按钮 ⭐⭐⭐ 最核心的元素
  Widget _buildPowerButton(bool isConnected, bool isProcessing, bool isDark, VpnProvider vpnProvider) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _isPowerButtonPressed = true),
      onTapUp: (_) {
        setState(() => _isPowerButtonPressed = false);
        if (!isProcessing) {
          vpnProvider.toggle();
        }
      },
      onTapCancel: () => setState(() => _isPowerButtonPressed = false),
      child: AnimatedScale(
        scale: _isPowerButtonPressed ? 0.94 : 1.0, 
        duration: const Duration(milliseconds: 100),
        curve: Curves.easeOut,
        child: Stack(
          alignment: Alignment.center,
          children: [
            // 装饰外圈 (连接与未连接均显示，保持静态)
            Container(
              width: 140,
              height: 140,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: isConnected
                    ? (isDark ? AppColors.primaryLight.withOpacity(0.1) : AppColors.primary.withOpacity(0.05))
                    : (isDark ? AppColors.darkCardBorder : AppColors.slate50),
                  width: 1,
                ),
              ),
            ),

            // 主按钮
            Container(
              width: 112,
              height: 112,
              decoration: BoxDecoration(
                color: _getPowerButtonColor(isConnected, isDark),
                shape: BoxShape.circle,
                border: isConnected
                  ? null
                  : Border.all(
                      color: isDark ? AppColors.darkCardBorder : AppColors.slate50,
                      width: 3,
                    ),
                boxShadow: _getPowerButtonShadow(isConnected, isDark),
              ),
              child: isProcessing
                  ? Center(
                      child: SizedBox(
                        width: 32,
                        height: 32,
                        child: CircularProgressIndicator(
                          color: _getPowerButtonIconColor(isConnected, isDark),
                          strokeWidth: 3,
                        ),
                      ),
                    )
                  : Icon(
                      Icons.power_settings_new,
                      size: 48,
                      color: _getPowerButtonIconColor(isConnected, isDark),
                    ),
            ),
          ],
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
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
            decoration: BoxDecoration(
              color: _getNodeButtonBgColor(isConnected, isDark),
              border: Border.all(
                color: _getNodeButtonBorderColor(isConnected, isDark),
              ),
              borderRadius: AppDimensions.borderRadiusLarge,
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                // 国旗 / 自动模式图标
                Container(
                  width: 32,
                  height: 32,
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
                const SizedBox(width: 12),
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
                          ? (effectiveName.isNotEmpty ? effectiveName : '等待测速...')
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
                const SizedBox(width: 12),
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
      return isDark ? AppColors.darkBackground : AppColors.lightCard; // 浅色模式连接后背景保持不变
    }
    return isDark ? AppColors.darkCard : AppColors.lightCard;
  }

  Color _getConnectionCardBorderColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark 
        ? AppColors.primary.withOpacity(0.4) 
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
      return AppColors.primaryLight.withOpacity(0.15);
    } else if (isConnected && !isDark) {
      // 修复：浅色模式下背景本身是白色，图标不能是白色，改为淡主题色
      return AppColors.primary.withOpacity(0.15);
    } else if (!isConnected && isDark) {
      return AppColors.primaryLight.withOpacity(0.1);
    } else {
      return AppColors.primaryDark.withOpacity(0.04);
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
        ? AppColors.primaryLight.withOpacity(0.6) 
        : AppColors.lightTextSecondary;
    }
    return isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary;
  }

  Color _getStatusBgColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark 
        ? AppColors.primary.withOpacity(0.1) 
        : Colors.white.withOpacity(0.1);
    }
    return isDark ? AppColors.darkCardSecondary : AppColors.primaryUltraLight;
  }

  Color _getStatusBorderColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark 
        ? AppColors.primary.withOpacity(0.2) 
        : Colors.white.withOpacity(0.2);
    }
    return isDark ? AppColors.darkCardBorder : AppColors.primaryLight.withOpacity(0.3);
  }

  Color _getStatusTextColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark ? AppColors.primaryLight : AppColors.primary;
    }
    return isDark ? AppColors.primaryLight : AppColors.primaryDark;
  }

  Color _getPowerButtonColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return AppColors.primary; // 无论深浅模式，开启后都变绿色
    }
    return isDark ? AppColors.darkCardSecondary : Colors.white;
  }

  Color _getPowerButtonIconColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return Colors.white; // 开启后统一白图标
    }
    return isDark ? AppColors.primaryLight : AppColors.slate400;
  }

  List<BoxShadow> _getPowerButtonShadow(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark 
        ? AppShadows.powerButtonConnectedDark 
        : AppShadows.powerButtonConnectedLight;
    }
    return isDark 
      ? AppShadows.powerButtonDisconnectedDark 
      : AppShadows.powerButtonDisconnectedLight;
  }

  Color _getNodeButtonBgColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark 
        ? AppColors.darkCardSecondary.withOpacity(0.5) 
        : AppColors.slate50;
    }
    return isDark ? AppColors.darkCardSecondary : Colors.white;
  }

  Color _getNodeButtonBorderColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark 
        ? AppColors.primary.withOpacity(0.2) 
        : AppColors.primaryLight.withOpacity(0.2);
    }
    return isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder;
  }

  Color _getNodeIconBgColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark 
        ? AppColors.primary.withOpacity(0.2) 
        : AppColors.primaryUltraLight;
    }
    return isDark ? AppColors.darkCard : AppColors.primaryUltraLight;
  }

  Color _getNodeLabelColor(bool isConnected, bool isDark) {
    if (isConnected) {
      return isDark 
        ? AppColors.primaryLight.withOpacity(0.4) 
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
        ? AppColors.primaryLight.withOpacity(0.4) 
        : AppColors.primary.withOpacity(0.4);
    }
    return isDark ? AppColors.darkTextTertiary : AppColors.slate300;
  }
}
