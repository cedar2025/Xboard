import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/node_provider.dart';
import '../../providers/navigation_provider.dart';
import '../../providers/vpn_provider.dart';
import '../../models/proxy_node.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';
import '../../utils/flag_helper.dart';
import '../../utils/platform_utils.dart';
import '../../utils/toast_utils.dart';

class NodeSelectionScreen extends StatefulWidget {
  const NodeSelectionScreen({super.key});

  @override
  State<NodeSelectionScreen> createState() => _NodeSelectionScreenState();
}

class _NodeSelectionScreenState extends State<NodeSelectionScreen> {
  final TextEditingController _searchController = TextEditingController();
  // ignore: unused_field
  final String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<NodeProvider>();
      if (provider.nodes.isEmpty) {
        provider.fetchNodes();
      }
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor:
          isDark ? AppColors.darkBackground : AppColors.lightBackground,
      body: SafeArea(
        child: Column(
          children: [
            // Header
            _buildHeader(isDark),

            // 节点列表
            Expanded(
              child: Consumer<NodeProvider>(
                builder: (context, provider, child) {
                  if (provider.isLoading && provider.nodes.isEmpty) {
                    return Center(
                      child: CircularProgressIndicator(
                        color:
                            isDark ? AppColors.primaryLight : AppColors.primary,
                      ),
                    );
                  }

                  if (provider.errorMessage != null && provider.nodes.isEmpty) {
                    return _buildErrorView(isDark, provider);
                  }

                  if (provider.nodes.isEmpty) {
                    return _buildEmptyView(isDark);
                  }

                  final nodes = provider.nodes;
                  final isDesktop = PlatformUtils.isDesktop;

                  // 分离自动节点和普通节点
                  final autoNodes =
                      nodes.where((n) => n.type == 'auto').toList();
                  final regularNodes =
                      nodes.where((n) => n.type != 'auto').toList();

                  return RefreshIndicator(
                    onRefresh: () async {
                      await provider.fetchNodes();
                    },
                    color: isDark ? AppColors.primaryLight : AppColors.primary,
                    child: SingleChildScrollView(
                      padding:
                          EdgeInsets.fromLTRB(24, 0, 24, isDesktop ? 24 : 120),
                      child: Column(
                        children: [
                          // 自动选择节点始终全宽显示
                          for (final node in autoNodes)
                            _buildAutoNodeCard(
                              node,
                              provider.selectedNode?.name == node.name,
                              isDark,
                              provider,
                            ),

                          // 桌面端：网格布局
                          if (isDesktop && regularNodes.isNotEmpty)
                            GridView.builder(
                              shrinkWrap: true,
                              physics: const NeverScrollableScrollPhysics(),
                              gridDelegate:
                                  SliverGridDelegateWithMaxCrossAxisExtent(
                                maxCrossAxisExtent: 300,
                                mainAxisExtent: 76,
                                mainAxisSpacing: 10,
                                crossAxisSpacing: 10,
                              ),
                              itemCount: regularNodes.length,
                              itemBuilder: (context, index) {
                                final node = regularNodes[index];
                                final isSelected =
                                    provider.selectedNode?.name == node.name;
                                return _buildNodeCard(
                                    node, isSelected, isDark, provider, index);
                              },
                            ),

                          // 移动端：列表布局
                          if (!isDesktop)
                            for (int index = 0;
                                index < regularNodes.length;
                                index++)
                              _buildNodeCard(
                                regularNodes[index],
                                provider.selectedNode?.name ==
                                    regularNodes[index].name,
                                isDark,
                                provider,
                                index,
                              ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: Consumer2<NodeProvider, VpnProvider>(
        builder: (context, provider, vpnProvider, _) {
          if (provider.nodes.isEmpty) {
            return const SizedBox.shrink();
          }

          final requiresConnectedVpn = !kIsWeb &&
              (Platform.isAndroid || Platform.isWindows || Platform.isMacOS);
          final canTestLatency =
              !requiresConnectedVpn || vpnProvider.isConnected;
          final isTesting = provider.isLoading;

          return Container(
            margin: EdgeInsets.only(bottom: PlatformUtils.isDesktop ? 16 : 90),
            child: FloatingActionButton.extended(
              onPressed: isTesting
                  ? null
                  : () {
                      if (!canTestLatency) {
                        ToastUtils.show(context, '请先开启加速后再测速');
                        return;
                      }
                      provider.testAllLatencies(context);
                    },
              backgroundColor:
                  isDark ? AppColors.primaryLight : AppColors.primary,
              elevation: 4,
              icon: Icon(
                Icons.flash_on,
                color: Colors.white,
              ),
              label: Text(
                isTesting ? '测速中' : (canTestLatency ? '一键测速' : '先开启加速'),
                style: AppTextStyles.buttonSmall.copyWith(color: Colors.white),
              ),
            ),
          );
        },
      ),
    );
  }

  /// Header
  Widget _buildHeader(bool isDark) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      child: Row(
        children: [
          // 返回按钮
          if (Navigator.canPop(context))
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: isDark ? AppColors.darkCard : AppColors.lightCard,
                borderRadius: AppDimensions.borderRadiusMedium,
                border: Border.all(
                  color: isDark
                      ? AppColors.darkCardBorder
                      : AppColors.lightCardBorder,
                ),
              ),
              child: IconButton(
                padding: EdgeInsets.zero,
                icon: Icon(
                  Icons.arrow_back,
                  size: 20,
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.lightTextPrimary,
                ),
                onPressed: () => Navigator.pop(context),
              ),
            ),
          if (Navigator.canPop(context)) const SizedBox(width: 16),

          // 标题
          Expanded(
            child: Text(
              '选择节点',
              style: AppTextStyles.titleMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.lightTextPrimary,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }

  bool _isCardPressed = false;
  int? _pressedIndex;

  /// 自动选择节点卡片（特殊样式）
  Widget _buildAutoNodeCard(
      ProxyNode node, bool isSelected, bool isDark, NodeProvider provider) {
    final autoRealNode = provider.autoSelectedRealNode;
    final autoLatency = autoRealNode?.latency;
    final autoNodeName = autoRealNode?.name;

    return GestureDetector(
      onTap: () {
        provider.selectNode(node);
        if (Navigator.canPop(context)) {
          Navigator.pop(context);
        }
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          gradient: isSelected
              ? LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: isDark
                      ? [
                          AppColors.primaryDark.withValues(alpha: 0.3),
                          AppColors.primaryUltraDark.withValues(alpha: 0.2)
                        ]
                      : [
                          AppColors.primaryUltraLight,
                          AppColors.primaryLight.withValues(alpha: 0.15)
                        ],
                )
              : null,
          color: isSelected
              ? null
              : (isDark ? AppColors.darkCard : AppColors.lightCard),
          borderRadius: AppDimensions.borderRadiusMedium,
          border: Border.all(
            color: isSelected
                ? (isDark ? AppColors.primaryDark : AppColors.primary)
                : (isDark
                    ? AppColors.darkCardBorder
                    : AppColors.lightCardBorder),
            width: isSelected ? 2 : 1,
          ),
          boxShadow: isSelected
              ? (isDark ? AppShadows.glowSmall : AppShadows.lightSmall)
              : AppShadows.getCard(isDark),
        ),
        child: Row(
          children: [
            // 闪电图标
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: isSelected
                      ? [
                          isDark ? AppColors.primaryDark : AppColors.primary,
                          isDark ? AppColors.primary : AppColors.primaryLight,
                        ]
                      : [
                          isDark
                              ? AppColors.darkCardSecondary
                              : AppColors.slate50,
                          isDark
                              ? AppColors.darkCardSecondary
                              : AppColors.slate50,
                        ],
                ),
                borderRadius: AppDimensions.borderRadiusSmall,
              ),
              child: Center(
                child: Text(
                  '⚡',
                  style: TextStyle(fontSize: isSelected ? 20 : 18),
                ),
              ),
            ),
            const SizedBox(width: 12),

            // 节点信息
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    kAutoSelectNodeName,
                    style: AppTextStyles.headlineMedium.copyWith(
                      color: isDark
                          ? AppColors.darkTextPrimary
                          : AppColors.lightTextPrimary,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Row(
                    children: [
                      Icon(
                        Icons.auto_awesome,
                        size: 14,
                        color:
                            isDark ? AppColors.primaryLight : AppColors.primary,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          autoNodeName != null
                              ? '当前: $autoNodeName'
                              : '智能选择最快节点',
                          style: AppTextStyles.labelTiny.copyWith(
                            color: isDark
                                ? AppColors.primaryLight.withValues(alpha: 0.7)
                                : AppColors.primary.withValues(alpha: 0.8),
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // 延迟和选中状态
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                if (isSelected)
                  Icon(
                    Icons.check_circle,
                    size: 22,
                    color: isDark ? AppColors.primaryLight : AppColors.primary,
                  ),
                if (autoLatency != null && autoLatency > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      '${autoLatency}ms',
                      style: AppTextStyles.labelTiny.copyWith(
                        color: Colors.green,
                        fontWeight: FontWeight.w900,
                        fontSize: 10,
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  /// 普通节点卡片
  Widget _buildNodeCard(ProxyNode node, bool isSelected, bool isDark,
      NodeProvider provider, int index) {
    final isPressed = _isCardPressed && _pressedIndex == index;

    return GestureDetector(
      onTapDown: (_) => setState(() {
        _isCardPressed = true;
        _pressedIndex = index;
      }),
      onTapUp: (_) {
        setState(() {
          _isCardPressed = false;
          _pressedIndex = null;
        });
        provider.selectNode(node);
        if (Navigator.canPop(context)) {
          Navigator.pop(context);
        }
      },
      onTapCancel: () => setState(() {
        _isCardPressed = false;
        _pressedIndex = null;
      }),
      child: AnimatedScale(
        scale: isPressed ? 0.98 : 1.0,
        duration: const Duration(milliseconds: 100),
        child: Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: isSelected
                ? (isDark
                    ? AppColors.primaryUltraDark.withValues(alpha: 0.3)
                    : AppColors.primaryUltraLight)
                : (isDark ? AppColors.darkCard : AppColors.lightCard),
            borderRadius: AppDimensions.borderRadiusMedium,
            border: Border.all(
              color: isSelected
                  ? (isDark ? AppColors.primaryDark : AppColors.primary)
                  : (isDark
                      ? AppColors.darkCardBorder
                      : AppColors.lightCardBorder),
              width: isSelected ? 2 : 1,
            ),
            boxShadow: isSelected
                ? (isDark ? AppShadows.glowSmall : AppShadows.lightSmall)
                : AppShadows.getCard(isDark),
          ),
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Row(
                children: [
                  // 国旗图标
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: isSelected
                          ? (isDark
                              ? AppColors.primaryDark.withValues(alpha: 0.2)
                              : AppColors.primaryUltraLight)
                          : (isDark
                              ? AppColors.darkCardSecondary
                              : AppColors.slate50),
                      borderRadius: AppDimensions.borderRadiusSmall,
                    ),
                    child: Center(
                      child: Text(
                        getFlagEmoji(node.name),
                        style: const TextStyle(fontSize: 18),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),

                  // 节点信息
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          node.name,
                          style: AppTextStyles.headlineMedium.copyWith(
                            color: isDark
                                ? AppColors.darkTextPrimary
                                : AppColors.lightTextPrimary,
                            fontWeight: FontWeight.bold,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          node.type.toUpperCase(),
                          style: AppTextStyles.labelTiny.copyWith(
                            color: isDark
                                ? AppColors.darkTextTertiary
                                : AppColors.lightTextSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),

                  // 选中状态
                  if (isSelected)
                    Padding(
                      padding: const EdgeInsets.only(left: 8.0),
                      child: Icon(
                        Icons.check_circle,
                        size: 22,
                        color:
                            isDark ? AppColors.primaryLight : AppColors.primary,
                      ),
                    ),
                ],
              ),

              // 右下角延迟显示
              if (node.latency != null)
                Positioned(
                  right: 0,
                  bottom: -4,
                  child: Text(
                    (node.latency! <= 0) ? '超时' : '${node.latency}ms',
                    style: AppTextStyles.labelTiny.copyWith(
                      color: _getLatencyColor(node.latency!, isDark),
                      fontWeight: FontWeight.w900,
                      fontSize: 10,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  /// 错误视图
  Widget _buildErrorView(bool isDark, NodeProvider provider) {
    final errorMsg = provider.errorMessage ?? '';
    debugPrint('DEBUG: NodeSelectionScreen Error Message: "$errorMsg"');

    final isSubscriptionError =
        errorMsg.contains('订阅') || errorMsg.contains('套餐');

    if (isSubscriptionError) {
      return _buildNoSubscriptionView(isDark);
    }

    return Center(
      child: Padding(
        padding: AppDimensions.pagePadding,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.error_outline,
              size: 64,
              color: isDark ? AppColors.errorLight : AppColors.error,
            ),
            const SizedBox(height: 16),
            Text(
              provider.errorMessage!,
              style: AppTextStyles.bodyLarge.copyWith(
                color: isDark
                    ? AppColors.darkTextSecondary
                    : AppColors.lightTextSecondary,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () => provider.fetchNodes(),
              child: const Text('重试'),
            ),
          ],
        ),
      ),
    );
  }

  /// 无订阅引导视图
  Widget _buildNoSubscriptionView(bool isDark) {
    return Center(
      child: Padding(
        padding: AppDimensions.pagePadding,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: isDark
                    ? AppColors.primaryDark.withValues(alpha: 0.1)
                    : AppColors.primaryUltraLight,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.rocket_launch_rounded,
                size: 64,
                color: isDark ? AppColors.primaryLight : AppColors.primary,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              '开启您的极速之旅',
              style: AppTextStyles.headlineMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.lightTextPrimary,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              '暂时没有可用的节点信息\n订阅套餐以获取全球极速网络加速服务',
              style: AppTextStyles.bodyMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextSecondary
                    : AppColors.lightTextSecondary,
                height: 1.5,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 32),
            ElevatedButton(
              onPressed: () {
                if (Navigator.canPop(context)) {
                  Navigator.pop(context);
                }
                context.read<NavigationProvider>().setPage(NavigationPage.shop);
              },
              style: ElevatedButton.styleFrom(
                padding:
                    const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
              ),
              child: const Text('立即去订阅'),
            ),
          ],
        ),
      ),
    );
  }

  /// 空视图
  Widget _buildEmptyView(bool isDark) {
    return Center(
      child: Text(
        '暂无可用节点',
        style: AppTextStyles.bodyLarge.copyWith(
          color:
              isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
        ),
      ),
    );
  }

  /// 获取延迟颜色
  Color _getLatencyColor(int latency, bool isDark) {
    if (latency <= 0) {
      return isDark ? AppColors.errorLight : AppColors.error;
    } else {
      return Colors.green;
    }
  }
}
