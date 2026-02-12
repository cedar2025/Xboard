import 'package:flutter/material.dart';

/// 大象网络 Premium UI 文字样式系统
class AppTextStyles {
  AppTextStyles._();

  // ==================== Display Styles ====================
  
  /// 超大标题 - 32px, 黑体
  static const TextStyle displayLarge = TextStyle(
    fontSize: 32,
    fontWeight: FontWeight.w900,
    letterSpacing: -0.5,
    height: 1.2,
  );
  
  /// 大标题 - 28px, 黑体
  static const TextStyle displayMedium = TextStyle(
    fontSize: 28,
    fontWeight: FontWeight.w900,
    letterSpacing: -0.3,
    height: 1.2,
  );
  
  /// 中标题 - 24px, 超粗
  static const TextStyle displaySmall = TextStyle(
    fontSize: 24,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.2,
    height: 1.3,
  );

  // ==================== Title Styles ====================
  
  /// 大号标题 - 24px, 超粗
  static const TextStyle titleLarge = TextStyle(
    fontSize: 24,
    fontWeight: FontWeight.w800,
    height: 1.3,
  );
  
  /// 中号标题 - 20px, 粗体
  static const TextStyle titleMedium = TextStyle(
    fontSize: 20,
    fontWeight: FontWeight.w700,
    height: 1.4,
  );
  
  /// 小号标题 - 18px, 粗体
  static const TextStyle titleSmall = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w700,
    height: 1.4,
  );

  // ==================== Headline Styles ====================
  
  /// 大号副标题 - 16px, 粗体
  static const TextStyle headlineLarge = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w700,
    height: 1.5,
  );
  
  /// 中号副标题 - 14px, 粗体
  static const TextStyle headlineMedium = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    height: 1.5,
  );
  
  /// 小号副标题 - 12px, 粗体
  static const TextStyle headlineSmall = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w700,
    height: 1.5,
  );

  // ==================== Body Styles ====================
  
  /// 大号正文 - 16px, 中等
  static const TextStyle bodyLarge = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w500,
    height: 1.5,
  );
  
  /// 中号正文 - 14px, 常规
  static const TextStyle bodyMedium = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );
  
  /// 小号正文 - 12px, 常规
  static const TextStyle bodySmall = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  // ==================== Label Styles ====================
  
  /// 大号标签 - 14px, 中等
  static const TextStyle labelLarge = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w500,
    height: 1.4,
  );
  
  /// 中号标签 - 12px, 中等
  static const TextStyle labelMedium = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    height: 1.4,
  );
  
  /// 小号标签 - 10px, 粗体 (Premium UI 特色)
  static const TextStyle labelSmall = TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w700,
    letterSpacing: 1.5,
    height: 1.4,
  );
  
  /// 超小标签 - 9px, 粗体
  static const TextStyle labelTiny = TextStyle(
    fontSize: 9,
    fontWeight: FontWeight.w700,
    letterSpacing: 1.2,
    height: 1.3,
  );

  // ==================== Special Styles ====================
  
  /// 按钮文字 - 16px, 粗体
  static const TextStyle button = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w700,
    height: 1.2,
  );
  
  /// 大号按钮文字 - 18px, 粗体
  static const TextStyle buttonLarge = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w700,
    height: 1.2,
  );
  
  /// 小号按钮文字 - 14px, 粗体
  static const TextStyle buttonSmall = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    height: 1.2,
  );
  
  /// 品牌名称 - 24px, 黑体, 紧凑
  static const TextStyle brandName = TextStyle(
    fontSize: 24,
    fontWeight: FontWeight.w900,
    letterSpacing: -0.5,
    height: 1.2,
  );
  
  /// 品牌 Slogan - 10px, 粗体, 超大间距
  static const TextStyle brandSlogan = TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w700,
    letterSpacing: 2.0,
    height: 1.2,
  );
  
  /// 价格数字 - 36px, 黑体
  static const TextStyle price = TextStyle(
    fontSize: 36,
    fontWeight: FontWeight.w900,
    height: 1.1,
  );
  
  /// 链接文字 - 14px, 半粗
  static const TextStyle link = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w600,
    decoration: TextDecoration.none,
    height: 1.5,
  );

  // ==================== Helper Methods ====================
  
  /// 创建带颜色的文字样式
  static TextStyle withColor(TextStyle style, Color color) {
    return style.copyWith(color: color);
  }
  
  /// 创建全大写样式
  static TextStyle uppercase(TextStyle style) {
    return style.copyWith(
      letterSpacing: (style.letterSpacing ?? 0) + 1.0,
    );
  }
  
  /// 创建斜体样式
  static TextStyle italic(TextStyle style) {
    return style.copyWith(fontStyle: FontStyle.italic);
  }
}
