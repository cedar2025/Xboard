import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/services/mac_runtime_service.dart';
import '../../providers/config_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';

class ConfigScreen extends StatelessWidget {
  const ConfigScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor:
          isDark ? AppColors.darkBackground : AppColors.lightBackground,
      body: SafeArea(
        child: Consumer<ConfigProvider>(
          builder: (context, config, _) {
            return SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(
                  parent: ClampingScrollPhysics()),
              padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Header
                  _buildHeader(isDark),
                  const SizedBox(height: 32),

                  // 配置列表卡片
                  _buildSettingsCard(context, config, isDark),
                  const SizedBox(height: 24),

                  _buildRuntimeToolsCard(context, isDark),
                  const SizedBox(height: 24),

                  // 恢复默认按钮
                  _buildResetButton(context, config, isDark),

                  const SizedBox(height: 100), // 底部导航栏留白
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  /// Header
  Widget _buildHeader(bool isDark) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '配置参数',
          style: AppTextStyles.titleMedium.copyWith(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
            fontSize: 20,
            fontWeight: FontWeight.bold,
          ),
        ),
      ],
    );
  }

  /// 设置列表卡片
  Widget _buildSettingsCard(
      BuildContext context, ConfigProvider config, bool isDark) {
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
          _buildConfigItem(
            context: context,
            icon: Icons.public,
            label: '国外 DNS',
            value: config.foreignDns,
            isDark: isDark,
            isFirst: true,
            onTap: () => _showEditDialog(
              context: context,
              title: '修改国外 DNS',
              currentValue: config.foreignDns,
              hintText: '输入 DNS 地址，如 8.8.8.8',
              onSave: (value) => config.setForeignDns(value),
            ),
          ),
          _buildDivider(isDark),
          _buildConfigItem(
            context: context,
            icon: Icons.dns_outlined,
            label: '国内 DNS',
            value: config.domesticDns,
            isDark: isDark,
            onTap: () => _showEditDialog(
              context: context,
              title: '修改国内 DNS',
              currentValue: config.domesticDns,
              hintText: '输入 DNS 地址，如 223.5.5.5',
              onSave: (value) => config.setDomesticDns(value),
            ),
          ),
          _buildDivider(isDark),
          _buildConfigItem(
            context: context,
            icon: Icons.settings_ethernet,
            label: '服务模式',
            value: config.serviceMode,
            isDark: isDark,
            onTap: () => _showServiceModeDialog(context, config, isDark),
          ),
          _buildDivider(isDark),
          _buildConfigItem(
            context: context,
            icon: Icons.language,
            label: '测试 URL',
            value: config.testUrl,
            isDark: isDark,
            isLast: true,
            onTap: () => _showEditDialog(
              context: context,
              title: '修改测试 URL',
              currentValue: config.testUrl,
              hintText: '输入 URL，如 http://cp.cloudflare.com/generate_204',
              onSave: (value) => config.setTestUrl(value),
            ),
          ),
        ],
      ),
    );
  }

  /// 配置子项
  Widget _buildConfigItem({
    required BuildContext context,
    required IconData icon,
    required String label,
    required String value,
    required bool isDark,
    required VoidCallback onTap,
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
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                value,
                style: AppTextStyles.bodySmall.copyWith(
                  color: isDark
                      ? AppColors.darkTextTertiary
                      : AppColors.lightTextSecondary,
                  fontFamily: 'monospace',
                ),
                textAlign: TextAlign.right,
                overflow: TextOverflow.ellipsis,
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

  Widget _buildRuntimeToolsCard(BuildContext context, bool isDark) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: AppDimensions.borderRadiusLarge,
        border: Border.all(
          color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
        ),
        boxShadow: AppShadows.getCard(isDark),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '运行时工具',
            style: AppTextStyles.titleSmall.copyWith(
              color: isDark
                  ? AppColors.darkTextPrimary
                  : AppColors.lightTextPrimary,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            '用于恢复系统代理和查看当前 mac 运行态。',
            style: AppTextStyles.bodySmall.copyWith(
              color: isDark
                  ? AppColors.darkTextSecondary
                  : AppColors.lightTextSecondary,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              FilledButton.tonalIcon(
                onPressed: () async {
                  final result =
                      await MacRuntimeService.instance.restoreSystemProxy();
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        result['restored'] == true ? '系统代理已恢复' : '恢复失败，请查看诊断页',
                      ),
                    ),
                  );
                },
                icon: const Icon(Icons.restore),
                label: const Text('恢复系统代理'),
              ),
              FilledButton.tonalIcon(
                onPressed: () async {
                  final status =
                      await MacRuntimeService.instance.getRuntimeStatus();
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        '状态: ${(status['status'] as String?) ?? 'unknown'} / 模式: ${(status['mode'] as String?) ?? 'unknown'}',
                      ),
                    ),
                  );
                },
                icon: const Icon(Icons.monitor_heart_outlined),
                label: const Text('查看运行状态'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// 恢复默认按钮
  Widget _buildResetButton(
      BuildContext context, ConfigProvider config, bool isDark) {
    return InkWell(
      borderRadius: AppDimensions.borderRadiusMedium,
      onTap: () {
        showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            backgroundColor: isDark ? AppColors.darkCard : AppColors.lightCard,
            title: Text(
              '恢复默认设置',
              style: TextStyle(
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.lightTextPrimary,
              ),
            ),
            content: Text(
              '确定要将所有配置恢复为默认值吗？',
              style: TextStyle(
                color: isDark
                    ? AppColors.darkTextSecondary
                    : AppColors.lightTextSecondary,
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: Text(
                  '取消',
                  style: TextStyle(
                    color: isDark
                        ? AppColors.darkTextTertiary
                        : AppColors.lightTextSecondary,
                  ),
                ),
              ),
              TextButton(
                onPressed: () {
                  config.resetToDefaults();
                  Navigator.pop(ctx);
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('已恢复默认设置'),
                      duration: Duration(seconds: 1),
                    ),
                  );
                },
                child: Text(
                  '确定',
                  style: TextStyle(
                    color: AppColors.error,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
        );
      },
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: isDark ? AppColors.darkCard : AppColors.lightCard,
          borderRadius: AppDimensions.borderRadiusMedium,
          border: Border.all(
            color:
                isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
          ),
        ),
        child: Center(
          child: Text(
            '恢复默认设置',
            style: AppTextStyles.bodyMedium.copyWith(
              color: AppColors.error,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  // ==================== 弹窗 ====================

  /// 文本编辑弹窗
  void _showEditDialog({
    required BuildContext context,
    required String title,
    required String currentValue,
    required String hintText,
    required Function(String) onSave,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final controller = TextEditingController(text: currentValue);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: isDark ? AppColors.darkCard : AppColors.lightCard,
        title: Text(
          title,
          style: TextStyle(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        content: TextField(
          controller: controller,
          autofocus: true,
          style: TextStyle(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
            fontFamily: 'monospace',
          ),
          decoration: InputDecoration(
            hintText: hintText,
            hintStyle: TextStyle(
              color: isDark
                  ? AppColors.darkTextTertiary
                  : AppColors.lightTextSecondary,
              fontSize: 13,
            ),
            filled: true,
            fillColor: isDark
                ? AppColors.darkInputBackground
                : AppColors.lightInputBackground,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: BorderSide(
                color: isDark ? AppColors.primaryLight : AppColors.primary,
                width: 2,
              ),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(
              '取消',
              style: TextStyle(
                color: isDark
                    ? AppColors.darkTextTertiary
                    : AppColors.lightTextSecondary,
              ),
            ),
          ),
          TextButton(
            onPressed: () {
              final value = controller.text.trim();
              if (value.isNotEmpty) {
                onSave(value);
              }
              Navigator.pop(ctx);
            },
            child: Text(
              '保存',
              style: TextStyle(
                color: isDark ? AppColors.primaryLight : AppColors.primary,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// 服务模式选择弹窗
  void _showServiceModeDialog(
      BuildContext context, ConfigProvider config, bool isDark) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: isDark ? AppColors.darkCard : AppColors.lightCard,
        title: Text(
          '更改服务模式',
          style: TextStyle(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: ConfigProvider.serviceModes.map((mode) {
            final isSelected = config.serviceMode == mode;
            return ListTile(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              tileColor: isSelected
                  ? (isDark
                      ? AppColors.primaryDark.withValues(alpha: 0.15)
                      : AppColors.primaryUltraLight)
                  : null,
              leading: Icon(
                isSelected ? Icons.check_circle : Icons.circle_outlined,
                color: isSelected
                    ? (isDark ? AppColors.primaryLight : AppColors.primary)
                    : (isDark
                        ? AppColors.darkTextTertiary
                        : AppColors.lightTextSecondary),
                size: 22,
              ),
              title: Text(
                mode,
                style: TextStyle(
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.lightTextPrimary,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                ),
              ),
              onTap: () {
                config.setServiceMode(mode);
                Navigator.pop(ctx);
              },
            );
          }).toList(),
        ),
      ),
    );
  }
}
