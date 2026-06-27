import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_markdown/flutter_markdown.dart';
import '../../providers/user_provider.dart';
import '../../providers/auth_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';
import '../../widgets/custom_webview.dart';
import '../../utils/helpers.dart';
import '../../utils/constants.dart';
import '../../utils/platform_utils.dart';

class PlanSelectionScreen extends StatefulWidget {
  const PlanSelectionScreen({super.key});

  @override
  State<PlanSelectionScreen> createState() => _PlanSelectionScreenState();
}

class _PlanSelectionScreenState extends State<PlanSelectionScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      context.read<UserProvider>().fetchPlans();
    });
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
            _buildHeader(context, isDark),

            // Plans List
            Expanded(
              child: Consumer<UserProvider>(
                builder: (context, provider, _) {
                  if (provider.isLoading && provider.plans.isEmpty) {
                    return Center(
                      child: CircularProgressIndicator(
                        color:
                            isDark ? AppColors.primaryLight : AppColors.primary,
                      ),
                    );
                  }

                  if (provider.plans.isEmpty) {
                    return Center(
                      child: Text(
                        '暂无可用套餐',
                        style: AppTextStyles.bodyLarge.copyWith(
                          color: isDark
                              ? AppColors.darkTextSecondary
                              : AppColors.lightTextSecondary,
                        ),
                      ),
                    );
                  }

                  return RefreshIndicator(
                    onRefresh: () async {
                      await provider.fetchPlans();
                    },
                    color: isDark ? AppColors.primaryLight : AppColors.primary,
                    child: PlatformUtils.isDesktop
                        ? _buildDesktopPlanGrid(provider, isDark)
                        : ListView.builder(
                            padding: const EdgeInsets.fromLTRB(24, 0, 24, 120),
                            itemCount: provider.plans.length,
                            itemBuilder: (context, index) {
                              return _buildPlanItem(
                                  provider.plans[index], isDark);
                            },
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

  /// 解析套餐价格和单位
  Map<String, String> _parsePlanPriceUnit(dynamic plan) {
    String displayPrice = '0';
    String displayUnit = '/月';
    final priceKeys = [
      'month_price',
      'quarter_price',
      'half_year_price',
      'year_price',
      'two_year_price',
      'three_year_price',
      'onetime_price',
      'reset_price'
    ];
    final unitMap = {
      'month_price': '/月',
      'quarter_price': '/季',
      'half_year_price': '/半年',
      'year_price': '/年',
      'two_year_price': '/两年',
      'three_year_price': '/三年',
      'onetime_price': '一次性',
      'reset_price': '重置包',
    };
    for (var k in priceKeys) {
      if (plan[k] != null && (double.tryParse(plan[k].toString()) ?? 0) > 0) {
        displayPrice =
            (double.parse(plan[k].toString()) / 100).toStringAsFixed(2);
        displayUnit = unitMap[k] ?? '/月';
        break;
      }
    }
    return {'price': '¥$displayPrice', 'unit': displayUnit};
  }

  /// 桌面端套餐网格布局
  Widget _buildDesktopPlanGrid(UserProvider provider, bool isDark) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 1000),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
          child: LayoutBuilder(
            builder: (context, constraints) {
              int columns = (constraints.maxWidth / 320).floor();
              if (columns < 1) columns = 1;
              if (columns > 3) columns = 3;

              return GridView.builder(
                shrinkWrap: true,
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  mainAxisSpacing: 24,
                  crossAxisSpacing: 24,
                  childAspectRatio: 0.65, // 相对较大的空间
                ),
                itemCount: provider.plans.length,
                itemBuilder: (context, index) {
                  return _buildPlanItem(provider.plans[index], isDark);
                },
              );
            },
          ),
        ),
      ),
    );
  }

  /// 通用套餐卡片构建
  Widget _buildPlanItem(dynamic plan, bool isDark) {
    final parsed = _parsePlanPriceUnit(plan);
    return Padding(
      padding: PlatformUtils.isDesktop
          ? EdgeInsets.zero
          : const EdgeInsets.only(bottom: 16),
      child: _buildPlanCard(
        context,
        plan,
        plan['name'] ?? '未知套餐',
        parsed['price']!,
        parsed['unit']!,
        plan['content'] as String? ?? '',
        isDark,
        planId: plan['id'] ?? 0,
        isHot: false,
      ),
    );
  }

  Widget _buildHeader(BuildContext context, bool isDark) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      child: Row(
        children: [
          Expanded(
            child: Text(
              '选择套餐',
              style: AppTextStyles.titleMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.lightTextPrimary,
                fontSize: 20,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPlanCard(
    BuildContext context,
    dynamic plan,
    String title,
    String price,
    String unit,
    String content,
    bool isDark, {
    required int planId,
    bool isHot = false,
  }) {
    final detailScrollController = ScrollController();
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: isHot
            ? (isDark
                ? AppColors.primaryUltraDark.withOpacity(0.3)
                : AppColors.primaryUltraLight)
            : (isDark ? AppColors.darkCard : AppColors.lightCard),
        borderRadius: AppDimensions.borderRadiusLarge,
        border: Border.all(
          color: isHot
              ? (isDark ? AppColors.primaryDark : AppColors.primary)
              : (isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder),
          width: isHot ? 2 : 1,
        ),
        boxShadow: isHot
            ? (isDark ? AppShadows.glowSmall : AppShadows.lightLarge)
            : AppShadows.getCard(isDark),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Title & Hot Tag
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  title,
                  style: AppTextStyles.titleSmall.copyWith(
                    color: isDark
                        ? AppColors.darkTextPrimary
                        : AppColors.lightTextPrimary,
                    fontWeight: FontWeight.bold,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (isHot)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(
                    color: isDark ? AppColors.primaryLight : AppColors.primary,
                    borderRadius:
                        BorderRadius.circular(AppDimensions.radiusFull),
                  ),
                  child: Text(
                    '推荐',
                    style:
                        AppTextStyles.labelTiny.copyWith(color: Colors.white),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),

          // Price
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                price,
                style: AppTextStyles.price.copyWith(
                  color: isDark ? AppColors.primaryLight : AppColors.primary,
                  fontSize: 28,
                ),
              ),
              const SizedBox(width: 4),
              Text(
                unit,
                style: AppTextStyles.bodySmall.copyWith(
                  color: isDark
                      ? AppColors.darkTextSecondary
                      : AppColors.lightTextSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Divider(color: isDark ? Colors.white12 : Colors.black12),
          const SizedBox(height: 20),

          // Features
          if (PlatformUtils.isDesktop)
            Expanded(
              child: RawScrollbar(
                controller: detailScrollController,
                thumbVisibility: true,
                trackVisibility: false,
                thickness: 6,
                radius: const Radius.circular(999),
                thumbColor: isDark
                    ? AppColors.darkTextTertiary.withOpacity(0.55)
                    : AppColors.lightTextTertiary.withOpacity(0.55),
                trackColor: Colors.transparent,
                child: ScrollConfiguration(
                  behavior: ScrollConfiguration.of(context).copyWith(
                    scrollbars: false,
                  ),
                  child: SingleChildScrollView(
                    controller: detailScrollController,
                    padding: const EdgeInsets.only(right: 14),
                    physics: const ClampingScrollPhysics(),
                    child: MarkdownBody(
                      data: content,
                      styleSheet: MarkdownStyleSheet(
                        p: AppTextStyles.bodySmall.copyWith(
                          color: isDark
                              ? AppColors.darkTextSecondary
                              : AppColors.lightTextSecondary,
                          height: 1.5,
                        ),
                        h2: AppTextStyles.titleSmall.copyWith(
                          color: isDark
                              ? AppColors.darkTextPrimary
                              : AppColors.lightTextPrimary,
                          fontWeight: FontWeight.bold,
                        ),
                        h3: AppTextStyles.titleSmall.copyWith(
                          color: isDark
                              ? AppColors.darkTextPrimary
                              : AppColors.lightTextPrimary,
                          fontWeight: FontWeight.bold,
                        ),
                        listBullet: TextStyle(
                          color: isDark
                              ? AppColors.primaryLight
                              : AppColors.primary,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            )
          else
            MarkdownBody(
              data: content,
              styleSheet: MarkdownStyleSheet(
                p: AppTextStyles.bodySmall.copyWith(
                  color: isDark
                      ? AppColors.darkTextSecondary
                      : AppColors.lightTextSecondary,
                  height: 1.5,
                ),
                h2: AppTextStyles.titleSmall.copyWith(
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.lightTextPrimary,
                  fontWeight: FontWeight.bold,
                ),
                h3: AppTextStyles.titleSmall.copyWith(
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.lightTextPrimary,
                  fontWeight: FontWeight.bold,
                ),
                listBullet: TextStyle(
                  color: isDark ? AppColors.primaryLight : AppColors.primary,
                ),
              ),
            ),
          const SizedBox(height: 16),

          // Subscribe Button
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton(
              onPressed: () async {
                if (planId == 0) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('错误：无效的套餐ID')),
                  );
                  return;
                }

                // 统一逻辑：模仿 ProfileScreen 的 _openWebPage 实现
                // 1. 显示加载
                showDialog(
                  context: context,
                  barrierDismissible: false,
                  builder: (context) =>
                      const Center(child: CircularProgressIndicator()),
                );

                try {
                  final authProvider = context.read<AuthProvider>();
                  // 使用相对路径，不带前缀 / (参考 'order', 'ticket')
                  final path = 'plan/$planId';

                  String? quickLoginUrl =
                      await authProvider.getQuickLoginUrl(path);

                  if (context.mounted) Navigator.of(context).pop(); // 关闭加载

                  if (quickLoginUrl != null && context.mounted) {
                    debugPrint(
                        'DEBUG: Original QuickLoginUrl = $quickLoginUrl');

                    // 仅在本地开发/内网环境下替换 host 和 port（与 ProfileScreen 保持一致）
                    if (ApiConstants.baseUrl.contains('192.168.')) {
                      final baseUri = Uri.parse(ApiConstants.baseUrl);
                      final quickUri = Uri.parse(quickLoginUrl);
                      quickLoginUrl = quickUri
                          .replace(
                            scheme: baseUri.scheme,
                            host: baseUri.host,
                            port: baseUri.port,
                          )
                          .toString();
                    }

                    debugPrint(
                        '🚀 [ACTION] Navigating to CustomWebView with URL: $quickLoginUrl');

                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => CustomWebView(
                          title: '套餐详情',
                          url: quickLoginUrl!,
                          injectAuth: true,
                          enableHiding: true,
                        ),
                      ),
                    );
                  } else if (context.mounted) {
                    final errorMsg = authProvider.errorMessage ?? '获取授权失败';
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(errorMsg)),
                    );
                  }
                } catch (e) {
                  debugPrint('ERROR: $e');
                  if (context.mounted) {
                    Navigator.of(context).pop();
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text('错误: $e')),
                    );
                  }
                }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: isHot
                    ? (isDark ? AppColors.primaryLight : AppColors.primary)
                    : (isDark
                        ? AppColors.darkCardSecondary
                        : AppColors.primaryUltraLight),
                foregroundColor: isHot
                    ? Colors.white
                    : (isDark ? AppColors.primaryLight : AppColors.primary),
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: AppDimensions.borderRadiusMedium,
                  side: isHot
                      ? BorderSide.none
                      : BorderSide(
                          color: isDark
                              ? AppColors.primaryLight
                              : AppColors.primary),
                ),
              ),
              child: Text(
                '立即订阅',
                style: AppTextStyles.button.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
