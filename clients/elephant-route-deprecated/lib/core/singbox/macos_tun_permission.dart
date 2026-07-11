class MacosTunPermission {
  const MacosTunPermission({
    required this.granted,
    required this.status,
    required this.code,
    required this.message,
    required this.requiresSettings,
    required this.details,
  });

  factory MacosTunPermission.fromNative(Map<String, dynamic> value) {
    final status = value['status']?.toString() ?? 'connectionFailed';
    final explicitlyRejected = value['ok'] == false;
    final granted = status == 'enabled' && !explicitlyRejected;
    return MacosTunPermission(
      granted: granted,
      status: status,
      code: value['code']?.toString() ??
          (granted ? 'OK' : 'HELPER_CONNECTION_FAILED'),
      message: value['message']?.toString() ??
          value['error']?.toString() ??
          _defaultMessage(status),
      requiresSettings: status == 'requiresApproval',
      details: Map<String, dynamic>.unmodifiable(value),
    );
  }

  final bool granted;
  final String status;
  final String code;
  final String message;
  final bool requiresSettings;
  final Map<String, dynamic> details;

  static String _defaultMessage(String status) {
    switch (status) {
      case 'requiresApproval':
        return '请在系统设置的“登录项与扩展”中允许大象网络后台组件';
      case 'notRegistered':
        return '后台网络组件尚未注册';
      case 'notFound':
        return '应用包缺少后台网络组件，请重新安装客户端';
      case 'refreshRequired':
        return '后台网络组件需要重新注册';
      default:
        return '无法连接后台网络组件';
    }
  }
}
