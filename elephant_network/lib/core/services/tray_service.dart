import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:tray_manager/tray_manager.dart';

class TrayService with TrayListener {
  static final TrayService _instance = TrayService._internal();
  factory TrayService() => _instance;
  TrayService._internal();

  Future<void> init() async {
    if (kIsWeb || (!Platform.isMacOS && !Platform.isWindows && !Platform.isLinux)) {
      return;
    }

    trayManager.addListener(this);
    
    // 设置托盘图标 (需要准备图标文件，目前先使用默认或占位)
    // 注意: 这里路径需要实际存在
    // await trayManager.setIcon(Platform.isWindows ? 'assets/images/app_icon.ico' : 'assets/images/app_icon.png');
    
    final Menu menu = Menu(
      items: [
        MenuItem(
          key: 'show_window',
          label: '显示窗口',
        ),
        MenuItem.separator(),
        MenuItem(
          key: 'toggle_vpn',
          label: '开启代理',
        ),
        MenuItem.separator(),
        MenuItem(
          key: 'exit_app',
          label: '退出',
        ),
      ],
    );
    await trayManager.setContextMenu(menu);
  }

  @override
  void onTrayIconMouseDown() {
    // 鼠标点击托盘
    trayManager.popUpContextMenu();
  }

  @override
  void onTrayMenuItemClick(MenuItem menuItem) {
    if (menuItem.key == 'show_window') {
      // TODO: 显示窗口逻辑 (需配合 window_manager)
    } else if (menuItem.key == 'exit_app') {
      exit(0);
    }
  }
}
