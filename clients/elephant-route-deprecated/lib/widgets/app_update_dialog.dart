import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/theme/app_colors.dart';
import '../models/app_update.dart';
import '../providers/app_update_provider.dart';

Future<void> showAppUpdateDialog(
  BuildContext context,
  AppUpdateInfo update,
) {
  return showDialog<void>(
    context: context,
    barrierDismissible: !update.force,
    builder: (context) => Consumer<AppUpdateProvider>(
      builder: (context, provider, _) {
        final progress = (provider.downloadProgress * 100).round();
        return AlertDialog(
          title: Text(update.force ? '需要更新客户端' : '发现新版本'),
          content: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('最新版本：v${update.version} (${update.buildNumber})'),
                if (update.fileSize != null) ...[
                  const SizedBox(height: 6),
                  Text('安装包大小：${_formatFileSize(update.fileSize!)}'),
                ],
                if (update.sha256 != null && update.sha256!.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    'SHA256：${update.sha256}',
                    style: const TextStyle(fontSize: 12),
                  ),
                ],
                if (update.releaseNotes != null &&
                    update.releaseNotes!.trim().isNotEmpty) ...[
                  const SizedBox(height: 14),
                  const Text(
                    '更新说明',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 6),
                  ConstrainedBox(
                    constraints: const BoxConstraints(maxHeight: 220),
                    child: SingleChildScrollView(
                      child: Text(update.releaseNotes!.trim()),
                    ),
                  ),
                ],
                if (provider.isDownloading) ...[
                  const SizedBox(height: 14),
                  LinearProgressIndicator(
                    value: provider.downloadProgress > 0
                        ? provider.downloadProgress
                        : null,
                  ),
                  const SizedBox(height: 6),
                  Text('正在下载 $progress%'),
                ],
                if (provider.downloadErrorMessage != null) ...[
                  const SizedBox(height: 14),
                  Text(
                    provider.downloadErrorMessage!,
                    style: TextStyle(color: AppColors.error),
                  ),
                ],
                if (update.force) ...[
                  const SizedBox(height: 14),
                  Text(
                    '当前版本已不再支持，请更新后继续使用。',
                    style: TextStyle(color: AppColors.error),
                  ),
                ],
                if (Platform.isMacOS) ...[
                  const SizedBox(height: 14),
                  const Text(
                    '下载完成后会打开 DMG。请退出当前版本，将“大象网络”拖到“应用程序”并选择替换，然后重新打开。',
                    style: TextStyle(fontSize: 13),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            if (!update.force)
              TextButton(
                onPressed: provider.isDownloading
                    ? null
                    : () async {
                        await context
                            .read<AppUpdateProvider>()
                            .dismissCurrentUpdate();
                        if (context.mounted) Navigator.pop(context);
                      },
                child: const Text('稍后再说'),
              ),
            FilledButton(
              onPressed: provider.isDownloading
                  ? null
                  : () async {
                      final installed = await context
                          .read<AppUpdateProvider>()
                          .downloadAndInstallUpdate();
                      if (!context.mounted) return;
                      if (!installed) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('下载或安装失败')),
                        );
                        return;
                      }
                      if (!update.force) Navigator.pop(context);
                    },
              child: Text(
                provider.isDownloading
                    ? '正在下载 $progress%'
                    : Platform.isMacOS
                        ? '下载并打开 DMG'
                        : '立即下载',
              ),
            ),
          ],
        );
      },
    ),
  );
}

String _formatFileSize(int bytes) {
  if (bytes <= 0) return '未知';
  const mb = 1024 * 1024;
  if (bytes >= mb) {
    return '${(bytes / mb).toStringAsFixed(1)} MB';
  }
  return '${(bytes / 1024).toStringAsFixed(1)} KB';
}
