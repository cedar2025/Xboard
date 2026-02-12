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
      backgroundColor: isDark ? AppColors.darkBackground : AppColors.lightBackground,
      body: SafeArea(
        child: Column(
          children: [
            // Header
            _buildHeader(context, isDark),
            
            // Plans List
            Expanded(
              child: Consumer<UserProvider>(
                builder: (context, provider, _) {
                  print('DEBUG: [Build] provider.plans.length = ${provider.plans.length}');
                  if (provider.isLoading && provider.plans.isEmpty) {
                    return Center(
                      child: CircularProgressIndicator(
                        color: isDark ? AppColors.primaryLight : AppColors.primary,
                      ),
                    );
                  }

                  if (provider.plans.isEmpty) {
                    return Center(
                      child: Text(
                        '暂无可用套餐',
                        style: AppTextStyles.bodyLarge.copyWith(
                          color: isDark ? AppColors.darkTextSecondary : AppColors.lightTextSecondary,
                        ),
                      ),
                    );
                  }

                  return RefreshIndicator(
                    onRefresh: () async {
                      await provider.fetchPlans();
                    },
                    color: isDark ? AppColors.primaryLight : AppColors.primary,
                    child: ListView.builder(
                      padding: const EdgeInsets.fromLTRB(24, 0, 24, 120),
                      itemCount: provider.plans.length,
                      itemBuilder: (context, index) {
                        final plan = provider.plans[index];
                        
                        // 动态寻找第一个有效价格用于展示
                        String displayPrice = '0';
                        String displayUnit = '/月';
                        final priceKeys = ['month_price', 'quarter_price', 'half_year_price', 'year_price', 'two_year_price', 'three_year_price', 'onetime_price', 'reset_price'];
                        for (var k in priceKeys) {
                          if (plan[k] != null && (double.tryParse(plan[k].toString()) ?? 0) > 0) {
                            displayPrice = (double.parse(plan[k].toString()) / 100).toStringAsFixed(2);
                            // 简单的单位映射
                            if (k == 'onetime_price') displayUnit = '一次性';
                            else if (k == 'reset_price') displayUnit = '重置包';
                            else if (k == 'year_price') displayUnit = '/年';
                            break;
                          }
                        }

                        return Padding(
                          padding: const EdgeInsets.only(bottom: 16),
                          child: _buildPlanCard(
                            context,
                            plan, 
                            plan['name'] ?? '未知套餐',
                            '¥$displayPrice',
                            displayUnit,
                            (plan['content'] as String? ?? '').split('\n').where((s) => s.isNotEmpty).toList(),
                            isDark,
                            planId: plan['id'] ?? 0,
                            isHot: false,
                          ),
                        );
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

  Widget _buildHeader(BuildContext context, bool isDark) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      child: Row(
        children: [
          Expanded(
            child: Text(
              '选择套餐',
              style: AppTextStyles.titleMedium.copyWith(
                color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
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
    List<String> features,
    bool isDark, {
    required int planId,
    bool isHot = false,
  }) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: isHot
            ? (isDark ? AppColors.primaryUltraDark.withOpacity(0.3) : AppColors.primaryUltraLight)
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
              Text(
                title,
                style: AppTextStyles.titleSmall.copyWith(
                  color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                  fontWeight: FontWeight.bold,
                ),
              ),
              if (isHot)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(
                    color: isDark ? AppColors.primaryDark : AppColors.primary,
                    borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
                  ),
                  child: Text(
                    '推荐',
                    style: AppTextStyles.labelTiny.copyWith(
                      color: Colors.white,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 16),
          
          // Price
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                price,
                style: AppTextStyles.price.copyWith(
                  color: isDark ? AppColors.primaryLight : AppColors.primary,
                  fontSize: 32,
                ),
              ),
              const SizedBox(width: 4),
              Text(
                unit,
                style: AppTextStyles.bodyMedium.copyWith(
                  color: isDark ? AppColors.darkTextSecondary : AppColors.lightTextSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          
          // Features
          ...features.take(10).map((feature) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: MarkdownBody(
                  data: feature,
                  shrinkWrap: true,
                  styleSheet: MarkdownStyleSheet(
                    p: AppTextStyles.bodyMedium.copyWith(
                      color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                      fontSize: 14,
                      height: 1.5,
                    ),
                    strong: AppTextStyles.bodyMedium.copyWith(
                      color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      height: 1.5,
                    ),
                    em: AppTextStyles.bodyMedium.copyWith(
                      color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                      fontSize: 14,
                      fontStyle: FontStyle.italic,
                      height: 1.5,
                    ),
                    code: AppTextStyles.bodyMedium.copyWith(
                      color: isDark ? AppColors.primaryLight : AppColors.primary,
                      fontSize: 13,
                      fontFamily: 'monospace',
                      backgroundColor: isDark ? AppColors.darkCardSecondary : AppColors.lightCardBorder,
                    ),
                    a: AppTextStyles.bodyMedium.copyWith(
                      color: isDark ? AppColors.primaryLight : AppColors.primary,
                      fontSize: 14,
                      decoration: TextDecoration.underline,
                    ),
                    // 移除列表的默认样式（点点）
                    listBullet: AppTextStyles.bodyMedium.copyWith(
                      fontSize: 0, // 设置为 0 隐藏 bullet
                      height: 0,
                    ),
                    listIndent: 0, // 移除列表缩进
                  ),
                ),
              )).toList(),
          const SizedBox(height: 24),
          
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
                  builder: (context) => const Center(child: CircularProgressIndicator()),
                );
                
                try {
                  final authProvider = context.read<AuthProvider>();
                  // 使用相对路径，不带前缀 / (参考 'order', 'ticket')
                  final path = 'plan/$planId';
                  
                  String? quickLoginUrl = await authProvider.getQuickLoginUrl(path);
                  
                  if (context.mounted) Navigator.of(context).pop(); // 关闭加载
                  
                  if (quickLoginUrl != null && context.mounted) {
                    debugPrint('DEBUG: Original QuickLoginUrl = $quickLoginUrl');
                    
                    // 关键修复：应用 ProfileScreen 中的 Host 替换逻辑
                    // 确保 URL 使用当前 ApiConstants 配置的 Host 和 Port
                    // 这对于解决本地开发/内网环境的跨域或连接问题至关重要
                    final baseUri = Uri.parse(ApiConstants.baseUrl);
                    final quickUri = Uri.parse(quickLoginUrl);
                    
                    // 强制替换 host 和 port
                    if (baseUri.host.isNotEmpty) {
                       quickLoginUrl = quickUri.replace(
                        scheme: baseUri.scheme,
                        host: baseUri.host,
                        port: baseUri.port,
                      ).toString();
                    }
                    
                    debugPrint('DEBUG: Refined QuickLoginUrl = $quickLoginUrl');

                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => CustomWebView(
                          title: '套餐详情',
                          url: quickLoginUrl!,
                          // 恢复默认行为：注入 Auth 和 启用隐藏
                          // 因为 '我的订单' 能正常工作，说明这个配置是没问题的
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
                    : (isDark ? AppColors.darkCardSecondary : AppColors.primaryUltraLight),
                foregroundColor: isHot ? Colors.white : (isDark ? AppColors.primaryLight : AppColors.primary),
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: AppDimensions.borderRadiusMedium,
                  side: isHot ? BorderSide.none : BorderSide(color: isDark ? AppColors.primaryLight : AppColors.primary),
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
