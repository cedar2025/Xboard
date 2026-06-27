import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';

import '../../core/services/app_logger.dart';
import '../../core/services/mac_runtime_service.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';
import '../../core/theme/app_text_styles.dart';
import '../../providers/app_update_provider.dart';
import '../../utils/constants.dart';
import '../../widgets/app_update_dialog.dart';

class AboutScreen extends StatefulWidget {
  const AboutScreen({super.key});

  @override
  State<AboutScreen> createState() => _AboutScreenState();
}

class _AboutScreenState extends State<AboutScreen> {
  late final Future<_AboutData> _dataFuture = _loadData();

  Future<_AboutData> _loadData() async {
    final package = await PackageInfo.fromPlatform();
    final runtime = await MacRuntimeService.instance.getRuntimeStatus();
    final logPath = await AppLogger.instance.getLogPath();
    return _AboutData(
      packageInfo: package,
      runtimeStatus: runtime,
      logPath: logPath,
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor:
          isDark ? AppColors.darkBackground : AppColors.lightBackground,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: Text(
          '关于与诊断',
          style: AppTextStyles.titleMedium.copyWith(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      body: FutureBuilder<_AboutData>(
        future: _dataFuture,
        builder: (context, snapshot) {
          if (!snapshot.hasData) {
            return Center(
              child: CircularProgressIndicator(
                color: isDark ? AppColors.primaryLight : AppColors.primary,
              ),
            );
          }

          final data = snapshot.data!;
          return SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 560),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _buildCard(
                      isDark: isDark,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'ElephantRoute macOS Beta',
                            style: AppTextStyles.titleLarge.copyWith(
                              color: isDark
                                  ? AppColors.darkTextPrimary
                                  : AppColors.lightTextPrimary,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'v${data.packageInfo.version} (${data.packageInfo.buildNumber})',
                            style: AppTextStyles.bodyMedium.copyWith(
                              color: isDark
                                  ? AppColors.darkTextSecondary
                                  : AppColors.lightTextSecondary,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _buildInfoRow('版本', data.packageInfo.version, isDark),
                          _buildInfoRow(
                              '构建号', data.packageInfo.buildNumber, isDark),
                          _buildInfoRow(
                              '包名', data.packageInfo.packageName, isDark),
                          _buildInfoRow('后端地址', ApiConstants.baseUrl, isDark),
                          _buildInfoRow(
                            'TLS 校验',
                            ApiConstants.allowInsecureCertificates
                                ? '不安全模式已开启'
                                : '严格校验',
                            isDark,
                          ),
                          _buildInfoRow(
                            '运行模式',
                            (data.runtimeStatus['mode'] as String?) ??
                                'unknown',
                            isDark,
                          ),
                          _buildInfoRow(
                            '运行状态',
                            (data.runtimeStatus['status'] as String?) ??
                                'unknown',
                            isDark,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    _buildCard(
                      isDark: isDark,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '诊断工具',
                            style: AppTextStyles.titleMedium.copyWith(
                              color: isDark
                                  ? AppColors.darkTextPrimary
                                  : AppColors.lightTextPrimary,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _buildInfoRow(
                              'Dart 日志', data.logPath ?? '未初始化', isDark),
                          _buildInfoRow(
                            '最后错误',
                            (data.runtimeStatus['lastError'] as String?) ?? '无',
                            isDark,
                          ),
                          const SizedBox(height: 16),
                          Wrap(
                            spacing: 12,
                            runSpacing: 12,
                            children: [
                              _buildActionButton(
                                label: '检查更新',
                                icon: Icons.system_update_alt_rounded,
                                isDark: isDark,
                                onPressed: _checkForUpdate,
                              ),
                              _buildActionButton(
                                label: '导出诊断',
                                icon: Icons.download_rounded,
                                isDark: isDark,
                                onPressed: () async {
                                  final path = await MacRuntimeService.instance
                                      .exportDiagnostics();
                                  if (!mounted) return;
                                  _showCopiedMessage(
                                      path == null ? '导出失败' : '诊断已导出到: $path');
                                },
                              ),
                              _buildActionButton(
                                label: '恢复系统代理',
                                icon: Icons.restore_rounded,
                                isDark: isDark,
                                onPressed: () async {
                                  final result = await MacRuntimeService
                                      .instance
                                      .restoreSystemProxy();
                                  if (!mounted) return;
                                  _showCopiedMessage(
                                    result['restored'] == true
                                        ? '系统代理已恢复'
                                        : '恢复失败，请查看日志',
                                  );
                                },
                              ),
                              _buildActionButton(
                                label: '复制日志路径',
                                icon: Icons.copy_all_rounded,
                                isDark: isDark,
                                onPressed: () async {
                                  if (data.logPath == null) return;
                                  await Clipboard.setData(
                                      ClipboardData(text: data.logPath!));
                                  if (!mounted) return;
                                  _showCopiedMessage('日志路径已复制');
                                },
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildCard({required bool isDark, required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: AppDimensions.borderRadiusLarge,
        border: Border.all(
          color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
        ),
        boxShadow: AppShadows.getCard(isDark),
      ),
      child: child,
    );
  }

  Widget _buildInfoRow(String label, String value, bool isDark) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 92,
            child: Text(
              label,
              style: AppTextStyles.labelMedium.copyWith(
                color: isDark
                    ? AppColors.darkTextTertiary
                    : AppColors.lightTextSecondary,
              ),
            ),
          ),
          Expanded(
            child: SelectableText(
              value,
              style: AppTextStyles.bodySmall.copyWith(
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.lightTextPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActionButton({
    required String label,
    required IconData icon,
    required bool isDark,
    required Future<void> Function() onPressed,
  }) {
    return FilledButton.tonalIcon(
      onPressed: onPressed,
      icon: Icon(icon),
      label: Text(label),
      style: FilledButton.styleFrom(
        backgroundColor: isDark
            ? AppColors.darkInputBackground
            : AppColors.primaryUltraLight,
        foregroundColor:
            isDark ? AppColors.darkTextPrimary : AppColors.primaryDark,
      ),
    );
  }

  void _showCopiedMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  Future<void> _checkForUpdate() async {
    final provider = context.read<AppUpdateProvider>();
    final update = await provider.checkForUpdate();
    if (!mounted) return;

    if (update != null) {
      await showAppUpdateDialog(context, update);
      return;
    }

    final message = provider.errorMessage ?? '当前已是最新版本';
    _showCopiedMessage(message);
  }
}

class _AboutData {
  const _AboutData({
    required this.packageInfo,
    required this.runtimeStatus,
    required this.logPath,
  });

  final PackageInfo packageInfo;
  final Map<String, dynamic> runtimeStatus;
  final String? logPath;
}
