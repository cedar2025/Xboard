import 'package:flutter/material.dart';
import 'app_colors.dart';

/// 大象网络 Premium UI 阴影系统
class AppShadows {
  AppShadows._();

  // ==================== Light Theme Shadows ====================
  
  /// 小阴影 - Light
  static List<BoxShadow> lightSmall = [
    BoxShadow(
      color: Colors.black.withOpacity(0.05),
      blurRadius: 10,
      offset: const Offset(0, 2),
    ),
  ];
  
  /// 中阴影 - Light
  static List<BoxShadow> lightMedium = [
    BoxShadow(
      color: Colors.black.withOpacity(0.08),
      blurRadius: 15,
      offset: const Offset(0, 4),
    ),
  ];
  
  /// 大阴影 - Light
  static List<BoxShadow> lightLarge = [
    BoxShadow(
      color: Colors.black.withOpacity(0.10),
      blurRadius: 20,
      offset: const Offset(0, 8),
    ),
  ];
  
  /// 超大阴影 - Light
  static List<BoxShadow> lightXL = [
    BoxShadow(
      color: Colors.black.withOpacity(0.12),
      blurRadius: 30,
      offset: const Offset(0, 15),
    ),
  ];
  
  /// 卡片阴影 - Light (Premium UI 标准)
  static List<BoxShadow> lightCard = [
    BoxShadow(
      color: AppColors.slate200.withOpacity(0.4),
      blurRadius: 10,
      offset: const Offset(0, 2),
      spreadRadius: 0,
    ),
  ];
  
  /// 按钮阴影 - Light
  static List<BoxShadow> lightButton = [
    BoxShadow(
      color: AppColors.primary.withOpacity(0.1),
      blurRadius: 10,
      offset: const Offset(0, 4),
    ),
  ];

  // ==================== Dark Theme Shadows ====================
  
  /// 小阴影 - Dark
  static List<BoxShadow> darkSmall = [
    BoxShadow(
      color: Colors.black.withOpacity(0.2),
      blurRadius: 10,
      offset: const Offset(0, 2),
    ),
  ];
  
  /// 中阴影 - Dark
  static List<BoxShadow> darkMedium = [
    BoxShadow(
      color: Colors.black.withOpacity(0.3),
      blurRadius: 15,
      offset: const Offset(0, 4),
    ),
  ];
  
  /// 大阴影 - Dark
  static List<BoxShadow> darkLarge = [
    BoxShadow(
      color: Colors.black.withOpacity(0.4),
      blurRadius: 30,
      offset: const Offset(0, 15),
    ),
  ];
  
  /// 超大阴影 - Dark
  static List<BoxShadow> darkXL = [
    BoxShadow(
      color: Colors.black.withOpacity(0.5),
      blurRadius: 40,
      offset: const Offset(0, 20),
    ),
  ];
  
  /// 卡片阴影 - Dark
  static List<BoxShadow> darkCard = [
    BoxShadow(
      color: Colors.black.withOpacity(0.4),
      blurRadius: 20,
      offset: const Offset(0, 10),
    ),
  ];
  
  /// 按钮阴影 - Dark
  static List<BoxShadow> darkButton = [
    BoxShadow(
      color: AppColors.primary.withOpacity(0.2),
      blurRadius: 10,
      offset: const Offset(0, 4),
    ),
  ];

  // ==================== Glow Effects ====================
  
  /// 绿色发光效果 - 小
  static List<BoxShadow> glowSmall = [
    BoxShadow(
      color: AppColors.primary.withOpacity(0.3),
      blurRadius: 20,
      spreadRadius: 0,
    ),
  ];
  
  /// 绿色发光效果 - 中
  static List<BoxShadow> glowMedium = [
    BoxShadow(
      color: AppColors.primary.withOpacity(0.3),
      blurRadius: 40,
      spreadRadius: 0,
    ),
  ];
  
  /// 绿色发光效果 - 大 (连接状态专用)
  static List<BoxShadow> glowLarge = [
    BoxShadow(
      color: AppColors.primary.withOpacity(0.4),
      blurRadius: 50,
      spreadRadius: 0,
    ),
  ];
  
  /// 多层发光效果 (最强)
  static List<BoxShadow> glowMulti = [
    // 外层大范围柔和发光
    BoxShadow(
      color: AppColors.primary.withOpacity(0.2),
      blurRadius: 50,
      spreadRadius: 0,
    ),
    // 内层强一点的发光
    BoxShadow(
      color: AppColors.primary.withOpacity(0.4),
      blurRadius: 20,
      spreadRadius: -5,
    ),
  ];

  // ==================== Connection Card Shadows ====================
  
  /// 连接卡片阴影 - 未连接 Light
  static List<BoxShadow> connectionDisconnectedLight = [
    BoxShadow(
      color: AppColors.slate200.withOpacity(0.4),
      blurRadius: 30,
      offset: const Offset(0, 10),
    ),
  ];
  
  /// 连接卡片阴影 - 未连接 Dark
  static List<BoxShadow> connectionDisconnectedDark = [
    BoxShadow(
      color: Colors.black.withOpacity(0.4),
      blurRadius: 40,
      offset: const Offset(0, 20),
    ),
  ];
  
  /// 连接卡片阴影 - 已连接 Light
  static List<BoxShadow> connectionConnectedLight = [
    BoxShadow(
      color: AppColors.primary.withOpacity(0.1),
      blurRadius: 40,
      offset: const Offset(0, 15),
    ),
  ];
  
  /// 连接卡片阴影 - 已连接 Dark (带发光)
  static List<BoxShadow> connectionConnectedDark = [
    BoxShadow(
      color: AppColors.primary.withOpacity(0.12),
      blurRadius: 50,
      spreadRadius: 0,
    ),
  ];

  // ==================== Power Button Shadows ====================
  
  /// 电源按钮 - 未连接 Light
  static List<BoxShadow> powerButtonDisconnectedLight = [
    BoxShadow(
      color: Colors.black.withOpacity(0.12),
      blurRadius: 30,
      offset: const Offset(0, 15),
    ),
  ];
  
  /// 电源按钮 - 未连接 Dark
  static List<BoxShadow> powerButtonDisconnectedDark = [
    BoxShadow(
      color: Colors.black.withOpacity(0.4),
      blurRadius: 40,
      offset: const Offset(0, 20),
    ),
  ];
  
  /// 电源按钮 - 已连接 Light
  static List<BoxShadow> powerButtonConnectedLight = [
    BoxShadow(
      color: Colors.black.withOpacity(0.1),
      blurRadius: 40,
      offset: const Offset(0, 15),
    ),
  ];
  
  /// 电源按钮 - 已连接 Dark (强发光)
  static List<BoxShadow> powerButtonConnectedDark = [
    BoxShadow(
      color: AppColors.primary.withOpacity(0.4),
      blurRadius: 40,
      spreadRadius: 0,
    ),
  ];

  // ==================== Navigation Bar Shadow ====================
  
  /// 导航栏阴影 - Light
  static List<BoxShadow> navigationLight = [
    BoxShadow(
      color: AppColors.slate200.withOpacity(0.5),
      blurRadius: 30,
      offset: const Offset(0, -5),
    ),
  ];
  
  /// 导航栏阴影 - Dark
  static List<BoxShadow> navigationDark = [
    BoxShadow(
      color: Colors.black.withOpacity(0.4),
      blurRadius: 30,
      offset: const Offset(0, -5),
    ),
  ];

  // ==================== Helper Methods ====================
  
  /// 根据主题获取小阴影
  static List<BoxShadow> getSmall(bool isDark) {
    return isDark ? darkSmall : lightSmall;
  }
  
  /// 根据主题获取中阴影
  static List<BoxShadow> getMedium(bool isDark) {
    return isDark ? darkMedium : lightMedium;
  }
  
  /// 根据主题获取大阴影
  static List<BoxShadow> getLarge(bool isDark) {
    return isDark ? darkLarge : lightLarge;
  }
  
  /// 根据主题获取卡片阴影
  static List<BoxShadow> getCard(bool isDark) {
    return isDark ? darkCard : lightCard;
  }
  
  /// 根据主题获取按钮阴影
  static List<BoxShadow> getButton(bool isDark) {
    return isDark ? darkButton : lightButton;
  }
  
  /// 自定义颜色的阴影
  static List<BoxShadow> custom({
    required Color color,
    double opacity = 0.3,
    double blurRadius = 20,
    Offset offset = Offset.zero,
    double spreadRadius = 0,
  }) {
    return [
      BoxShadow(
        color: color.withOpacity(opacity),
        blurRadius: blurRadius,
        offset: offset,
        spreadRadius: spreadRadius,
      ),
    ];
  }
}
