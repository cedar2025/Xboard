import 'package:flutter/material.dart';

/// 大象网络 Premium UI 色彩系统
/// 基于 TailwindCSS Slate + Emerald 配色方案
class AppColors {
  AppColors._();

  // ==================== Light Theme ====================
  
  /// 主背景色 - slate-50
  static const Color lightBackground = Color(0xFFF8FAFC);
  
  /// 卡片背景色 - white
  static const Color lightCard = Color(0xFFFFFFFF);
  
  /// 卡片边框 - slate-100
  static const Color lightCardBorder = Color(0xFFF1F5F9);
  
  /// 次级边框 - slate-200
  static const Color lightBorderSecondary = Color(0xFFE2E8F0);
  
  /// 主文字颜色 - slate-900
  static const Color lightTextPrimary = Color(0xFF0F172A);
  
  /// 次要文字颜色 - slate-500
  static const Color lightTextSecondary = Color(0xFF64748B);
  
  /// 三级文字颜色 - slate-400
  static const Color lightTextTertiary = Color(0xFF94A3B8);
  
  /// 输入框背景 - slate-50
  static const Color lightInputBackground = Color(0xFFF8FAFC);
  
  /// 输入框 Focus 背景 - white
  static const Color lightInputFocusBackground = Color(0xFFFFFFFF);

  // ==================== Dark Theme ====================
  
  /// 主背景色 - 自定义深蓝黑
  static const Color darkBackground = Color(0xFF0F172A);
  
  /// 卡片背景色 - slate-900
  static const Color darkCard = Color(0xFF1E293B);
  
  /// 次级卡片背景 - slate-800
  static const Color darkCardSecondary = Color(0xFF334155);
  
  /// 卡片边框 - slate-800
  static const Color darkCardBorder = Color(0xFF334155);
  
  /// 次级边框 - slate-700
  static const Color darkBorderSecondary = Color(0xFF475569);
  
  /// 主文字颜色 - white
  static const Color darkTextPrimary = Color(0xFFFFFFFF);
  
  /// 次要文字颜色 - slate-400
  static const Color darkTextSecondary = Color(0xFF94A3B8);
  
  /// 三级文字颜色 - slate-500
  static const Color darkTextTertiary = Color(0xFF64748B);
  
  /// 极淡文字 - slate-600
  static const Color darkTextQuaternary = Color(0xFF475569);
  
  /// 输入框背景 - slate-900
  static const Color darkInputBackground = Color(0xFF1E293B);

  // ==================== Primary Colors (Emerald) ====================
  
  /// 主色 - emerald-500 (Light 模式主要使用)
  static const Color primary = Color(0xFF10B981);
  
  /// 主色浅色 - emerald-400 (Dark 模式主要使用)
  static const Color primaryLight = Color(0xFF34D399);
  
  /// 主色深色 - emerald-600 (Dark 模式按钮等)
  static const Color primaryDark = Color(0xFF059669);
  
  /// 主色超浅 - emerald-50
  static const Color primaryUltraLight = Color(0xFFECFDF5);
  
  /// 主色极深 - emerald-950
  static const Color primaryUltraDark = Color(0xFF022C22);

  // ==================== Semantic Colors ====================
  
  /// 成功色 - emerald-500
  static const Color success = Color(0xFF10B981);
  
  /// 成功色浅色 - emerald-400
  static const Color successLight = Color(0xFF34D399);
  
  /// 警告色 - amber-500
  static const Color warning = Color(0xFFF59E0B);
  
  /// 警告色浅色 - amber-400
  static const Color warningLight = Color(0xFFFBBF24);
  
  /// 错误色 - rose-500
  static const Color error = Color(0xFFF43F5E);
  
  /// 错误色浅色 - rose-400
  static const Color errorLight = Color(0xFFFB7185);
  
  /// 信息色 - blue-500
  static const Color info = Color(0xFF3B82F6);

  // ==================== Special Colors ====================
  
  /// Slate 色系 - 用于各种中性场景
  static const Color slate50 = Color(0xFFF8FAFC);
  static const Color slate100 = Color(0xFFF1F5F9);
  static const Color slate200 = Color(0xFFE2E8F0);
  static const Color slate300 = Color(0xFFCBD5E1);
  static const Color slate400 = Color(0xFF94A3B8);
  static const Color slate500 = Color(0xFF64748B);
  static const Color slate600 = Color(0xFF475569);
  static const Color slate700 = Color(0xFF334155);
  static const Color slate800 = Color(0xFF1E293B);
  static const Color slate900 = Color(0xFF0F172A);

  // ==================== Helper Methods ====================
  
  /// 根据主题模式获取背景色
  static Color getBackground(bool isDark) {
    return isDark ? darkBackground : lightBackground;
  }
  
  /// 根据主题模式获取卡片背景色
  static Color getCardBackground(bool isDark) {
    return isDark ? darkCard : lightCard;
  }
  
  /// 根据主题模式获取卡片边框色
  static Color getCardBorder(bool isDark) {
    return isDark ? darkCardBorder : lightCardBorder;
  }
  
  /// 根据主题模式获取主文字颜色
  static Color getTextPrimary(bool isDark) {
    return isDark ? darkTextPrimary : lightTextPrimary;
  }
  
  /// 根据主题模式获取次要文字颜色
  static Color getTextSecondary(bool isDark) {
    return isDark ? darkTextSecondary : lightTextSecondary;
  }
  
  /// 根据主题模式获取三级文字颜色
  static Color getTextTertiary(bool isDark) {
    return isDark ? darkTextTertiary : lightTextTertiary;
  }
  
  /// 根据主题模式获取主色
  static Color getPrimary(bool isDark) {
    return isDark ? primaryLight : primary;
  }
  
  /// 根据主题模式获取按钮主色
  static Color getPrimaryButton(bool isDark) {
    return isDark ? primaryDark : primary;
  }
  
  /// 根据主题模式获取输入框背景色
  static Color getInputBackground(bool isDark) {
    return isDark ? darkInputBackground : lightInputBackground;
  }
}
