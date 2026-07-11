import 'dart:io';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/services/mac_runtime_service.dart';

const _macSetupGuideSeenKey = 'macos_unsigned_setup_guide_seen_v2';

Future<void> showMacSetupGuideIfNeeded(BuildContext context) async {
  if (!Platform.isMacOS) return;
  final prefs = await SharedPreferences.getInstance();
  if (prefs.getBool(_macSetupGuideSeenKey) == true) return;

  final setup = await MacRuntimeService.instance.getSetupStatus();
  if (!context.mounted) return;
  await showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (_) => _MacSetupGuideDialog(setup: setup),
  );
  await prefs.setBool(_macSetupGuideSeenKey, true);
}

class _MacSetupGuideDialog extends StatefulWidget {
  const _MacSetupGuideDialog({required this.setup});

  final Map<String, dynamic> setup;

  @override
  State<_MacSetupGuideDialog> createState() => _MacSetupGuideDialogState();
}

class _MacSetupGuideDialogState extends State<_MacSetupGuideDialog> {
  bool _working = false;
  String? _message;

  Map<String, dynamic> get _helper {
    final value = widget.setup['helper'];
    return value is Map ? Map<String, dynamic>.from(value) : const {};
  }

  bool get _installedInApplications =>
      widget.setup['installedInApplications'] == true;

  Future<void> _enableHelper() async {
    setState(() {
      _working = true;
      _message = null;
    });
    final result = await MacRuntimeService.instance.ensureTunHelper();
    if (!mounted) return;
    setState(() {
      _working = false;
      _message = result['message']?.toString();
    });
  }

  @override
  Widget build(BuildContext context) {
    final helperEnabled = _helper['status'] == 'enabled';
    return AlertDialog(
      title: const Text('完成 macOS 首次设置'),
      content: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 460),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              '此版本不使用 Apple Developer ID。首次从官网下载后，需要在“隐私与安全性”中选择“仍要打开”。',
            ),
            const SizedBox(height: 14),
            _SetupLine(
              complete: _installedInApplications,
              text: _installedInApplications
                  ? '应用已位于“应用程序”目录'
                  : '请先把大象网络拖入“应用程序”目录后重新打开',
            ),
            const SizedBox(height: 8),
            _SetupLine(
              complete: helperEnabled,
              text: helperEnabled
                  ? '后台网络组件已启用'
                  : '需要管理员授权安装后台网络组件，安装后即可使用全局 TUN 加速',
            ),
            if (_message != null) ...[
              const SizedBox(height: 12),
              Text(_message!, style: const TextStyle(fontSize: 13)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _working ? null : () => Navigator.pop(context),
          child: const Text('稍后设置'),
        ),
        if (_installedInApplications && !helperEnabled)
          FilledButton(
            onPressed: _working ? null : _enableHelper,
            child: Text(_working ? '正在安装...' : '管理员授权安装'),
          ),
        if (helperEnabled)
          FilledButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('完成'),
          ),
      ],
    );
  }
}

class _SetupLine extends StatelessWidget {
  const _SetupLine({required this.complete, required this.text});

  final bool complete;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          complete ? Icons.check_circle_rounded : Icons.info_outline_rounded,
          size: 20,
          color:
              complete ? Colors.green : Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(width: 8),
        Expanded(child: Text(text)),
      ],
    );
  }
}
