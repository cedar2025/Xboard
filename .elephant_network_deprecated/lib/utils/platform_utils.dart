import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

/// 平台检测工具类
/// 统一管理所有平台相关的判断逻辑
class PlatformUtils {
  PlatformUtils._();

  /// 是否为桌面端 (macOS / Windows / Linux)
  static bool get isDesktop {
    if (kIsWeb) return false;
    return Platform.isMacOS || Platform.isWindows || Platform.isLinux;
  }

  /// 是否为移动端 (Android / iOS)
  static bool get isMobile {
    if (kIsWeb) return false;
    return Platform.isAndroid || Platform.isIOS;
  }

  /// 是否为 macOS
  static bool get isMacOS {
    if (kIsWeb) return false;
    return Platform.isMacOS;
  }

  /// 是否为 Web
  static bool get isWeb => kIsWeb;

  /// 基于屏幕宽度的响应式判断
  /// 宽度 >= breakpoint 视为桌面宽度
  static bool isDesktopWidth(BuildContext context, {double breakpoint = 800.0}) {
    return MediaQuery.of(context).size.width >= breakpoint;
  }

  /// 获取当前窗口宽度
  static double getScreenWidth(BuildContext context) {
    return MediaQuery.of(context).size.width;
  }

  /// 获取当前窗口高度
  static double getScreenHeight(BuildContext context) {
    return MediaQuery.of(context).size.height;
  }

  /// 根据宽度计算 Grid 列数
  /// 用于桌面端节点列表、套餐卡片等网格布局
  static int getGridColumns(BuildContext context, {double minItemWidth = 280.0}) {
    final width = MediaQuery.of(context).size.width;
    // 桌面端需要减去侧边栏宽度
    final contentWidth = isDesktop ? width - 220.0 : width;
    final columns = (contentWidth / minItemWidth).floor();
    return columns.clamp(1, 4);
  }
}
