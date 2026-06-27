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
    builder: (context) => AlertDialog(
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
            if (update.force) ...[
              const SizedBox(height: 14),
              Text(
                '当前版本已不再支持，请更新后继续使用。',
                style: TextStyle(color: AppColors.error),
              ),
            ],
          ],
        ),
      ),
      actions: [
        if (!update.force)
          TextButton(
            onPressed: () async {
              await context.read<AppUpdateProvider>().dismissCurrentUpdate();
              if (context.mounted) Navigator.pop(context);
            },
            child: const Text('稍后再说'),
          ),
        FilledButton(
          onPressed: () async {
            final opened =
                await context.read<AppUpdateProvider>().openDownloadPage();
            if (!context.mounted) return;
            if (!opened) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('无法打开下载地址')),
              );
              return;
            }
            if (!update.force) Navigator.pop(context);
          },
          child: const Text('立即下载'),
        ),
      ],
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
