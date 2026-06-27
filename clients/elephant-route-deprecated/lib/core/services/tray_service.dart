import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:tray_manager/tray_manager.dart';
import 'package:window_manager/window_manager.dart'; // 引入 window_manager

class TrayService with TrayListener {
  static final TrayService _instance = TrayService._internal();
  factory TrayService() => _instance;
  TrayService._internal();

  Future<void> init() async {
    if (!_isSupportedPlatform) {
      return;
    }

    trayManager.addListener(this);

    // 设置托盘图标
    final String iconPath = Platform.isWindows
        ? 'app_icon.ico' // Windows 原生应用图标
        : 'assets/images/logo_icon_tray.png';

    await trayManager.setIcon(
      iconPath,
      isTemplate: Platform.isMacOS, // 仅 macOS 下支持自适应黑白色（Template Image）
    );

    // 初始化时显示默认状态（未连接）
    await updateMenu(false);
  }

  // 记录外部传入的 Toggle 回调
  VoidCallback? onToggleVpn;

  bool get _isSupportedPlatform =>
      !kIsWeb && (Platform.isMacOS || Platform.isWindows || Platform.isLinux);

  Future<void> updateMenu(bool isConnected) async {
    if (!_isSupportedPlatform) {
      return;
    }

    final Menu menu = Menu(
      items: [
        MenuItem(
          key: 'toggle_vpn',
          label: isConnected ? '关闭 VPN' : '开启 VPN',
        ),
        MenuItem.separator(),
        MenuItem(
          key: 'show_window',
          label: '显示窗口',
        ),
        MenuItem.separator(),
        MenuItem(
          key: 'exit_app',
          label: '退出应用',
        ),
      ],
    );
    await trayManager.setContextMenu(menu);
  }

  @override
  void onTrayIconMouseDown() {
    // 鼠标点击托盘，也可以直接改为显示窗口：
    // windowManager.show();
    // windowManager.focus();
    trayManager.popUpContextMenu();
  }

  @override
  void onTrayIconRightMouseDown() {
    trayManager.popUpContextMenu();
  }

  @override
  void onTrayMenuItemClick(MenuItem menuItem) async {
    debugPrint('TRAY: Clicked ${menuItem.key}');
    if (menuItem.key == 'toggle_vpn') {
      if (onToggleVpn != null) {
        debugPrint('TRAY: Executing onToggleVpn callback');
        onToggleVpn!();
      } else {
        debugPrint('TRAY: onToggleVpn callback is NULL');
      }
    } else if (menuItem.key == 'show_window') {
      bool isMinimized = await windowManager.isMinimized();
      if (isMinimized) {
        await windowManager.restore();
      }
      await windowManager.show();
      await windowManager.focus();
    } else if (menuItem.key == 'exit_app') {
      await windowManager.destroy(); // 使用 window_manager 安全退出
      exit(0);
    }
  }
}
