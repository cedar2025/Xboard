import 'package:flutter/material.dart';

/// 大象网络 Premium UI 尺寸系统
/// 包括圆角、间距、尺寸等
class AppDimensions {
  AppDimensions._();

  // ==================== Border Radius ====================

  /// 超小圆角 - 8px
  static const double radiusXS = 8.0;

  /// 小圆角 - 12px (rounded-xl)
  static const double radiusSmall = 12.0;

  /// 中圆角 - 16px (rounded-2xl)
  static const double radiusMedium = 16.0;

  /// 大圆角 - 24px (rounded-3xl)
  static const double radiusLarge = 24.0;

  /// 超大圆角 - 32px
  static const double radiusXL = 32.0;

  /// 巨大圆角 - 56px (连接卡片专用)
  static const double radiusXXL = 56.0;

  /// 完全圆角 - 999px
  static const double radiusFull = 999.0;

  // ==================== BorderRadius Objects ====================

  static const BorderRadius borderRadiusXS =
      BorderRadius.all(Radius.circular(radiusXS));
  static const BorderRadius borderRadiusSmall =
      BorderRadius.all(Radius.circular(radiusSmall));
  static const BorderRadius borderRadiusMedium =
      BorderRadius.all(Radius.circular(radiusMedium));
  static const BorderRadius borderRadiusLarge =
      BorderRadius.all(Radius.circular(radiusLarge));
  static const BorderRadius borderRadiusXL =
      BorderRadius.all(Radius.circular(radiusXL));
  static const BorderRadius borderRadiusXXL =
      BorderRadius.all(Radius.circular(radiusXXL));
  static const BorderRadius borderRadiusFull =
      BorderRadius.all(Radius.circular(radiusFull));

  // ==================== Spacing ====================

  /// 超超小间距 - 2px
  static const double spacingXXS = 2.0;

  /// 超小间距 - 4px
  static const double spacingXS = 4.0;

  /// 小间距 - 8px
  static const double spacingSmall = 8.0;

  /// 中小间距 - 12px
  static const double spacingSM = 12.0;

  /// 中间距 - 16px
  static const double spacingMedium = 16.0;

  /// 中大间距 - 20px
  static const double spacingML = 20.0;

  /// 大间距 - 24px
  static const double spacingLarge = 24.0;

  /// 超大间距 - 32px
  static const double spacingXL = 32.0;

  /// 超超大间距 - 48px
  static const double spacingXXL = 48.0;

  /// 巨大间距 - 64px
  static const double spacingHuge = 64.0;

  // ==================== Padding ====================

  /// 页面水平边距 (Premium UI 标准)
  static const EdgeInsets pagePadding = EdgeInsets.all(spacingLarge);

  /// 页面水平边距 (仅左右)
  static const EdgeInsets pagePaddingHorizontal =
      EdgeInsets.symmetric(horizontal: spacingLarge);

  /// 卡片内边距
  static const EdgeInsets cardPadding = EdgeInsets.all(spacingMedium);

  /// 卡片大内边距
  static const EdgeInsets cardPaddingLarge = EdgeInsets.all(spacingLarge);

  /// 按钮内边距
  static const EdgeInsets buttonPadding = EdgeInsets.symmetric(
    vertical: spacingMedium,
    horizontal: spacingLarge,
  );

  /// 输入框内边距
  static const EdgeInsets inputPadding = EdgeInsets.symmetric(
    vertical: spacingMedium,
    horizontal: spacingMedium,
  );

  // ==================== Icon Sizes ====================

  /// 超小图标 - 12px
  static const double iconXS = 12.0;

  /// 小图标 - 16px
  static const double iconSmall = 16.0;

  /// 中图标 - 18px (Premium UI 输入框图标)
  static const double iconMedium = 18.0;

  /// 大图标 - 20px
  static const double iconLarge = 20.0;

  /// 超大图标 - 24px (导航栏)
  static const double iconXL = 24.0;

  /// 巨大图标 - 40px (Logo)
  static const double iconXXL = 40.0;

  /// 电源按钮图标 - 48px
  static const double iconPower = 48.0;

  /// 装饰图标 - 200px (背景 Zap)
  static const double iconDecoration = 200.0;

  // ==================== Button Sizes ====================

  /// 按钮高度 - 标准
  static const double buttonHeight = 56.0;

  /// 按钮高度 - 中等
  static const double buttonHeightMedium = 48.0;

  /// 按钮高度 - 小
  static const double buttonHeightSmall = 40.0;

  /// 电源按钮尺寸 - 112px (28 * 4)
  static const double powerButtonSize = 112.0;

  // ==================== Avatar Sizes ====================

  /// 小头像 - 32px
  static const double avatarSmall = 32.0;

  /// 中头像 - 48px (用户卡片)
  static const double avatarMedium = 48.0;

  /// 大头像 - 80px (Logo)
  static const double avatarLarge = 80.0;

  /// 超大头像 - 96px (个人中心)
  static const double avatarXL = 96.0;

  // ==================== Component Sizes ====================

  /// 导航栏高度 - 72px
  static const double navigationBarHeight = 72.0;

  /// 导航栏底部间距 - 24px
  static const double navigationBarBottomPadding = 24.0;

  /// 连接卡片最小高度 - 380px
  static const double connectionCardMinHeight = 380.0;

  /// 进度条高度 - 8px
  static const double progressBarHeight = 8.0;

  /// 指示点尺寸 - 6px
  static const double indicatorDotSize = 6.0;

  /// 小指示点尺寸 - 4px (导航栏激活指示)
  static const double indicatorDotSmall = 6.0;

  // ==================== Border Width ====================

  /// 细边框 - 1px
  static const double borderThin = 1.0;

  /// 中边框 - 2px
  static const double borderMedium = 2.0;

  /// 粗边框 - 3px
  static const double borderThick = 3.0;

  // ==================== Elevation (Shadow) ====================

  /// 无阴影
  static const double elevationNone = 0.0;

  /// 小阴影
  static const double elevationSmall = 2.0;

  /// 中阴影
  static const double elevationMedium = 4.0;

  /// 大阴影
  static const double elevationLarge = 8.0;

  /// 超大阴影
  static const double elevationXL = 16.0;

  // ==================== Desktop Specific ====================

  /// 桌面端侧边栏宽度（展开）
  static const double sidebarWidth = 220.0;

  /// 桌面端侧边栏宽度（折叠）
  static const double sidebarWidthCollapsed = 72.0;

  /// 桌面端内容区最大宽度
  static const double contentMaxWidth = 1200.0;

  /// 桌面端卡片最小宽度（Grid 布局用）
  static const double cardMinWidth = 280.0;

  /// 桌面端登录卡片最大宽度
  static const double loginCardMaxWidth = 480.0;

  /// 桌面端顶部安全区域高度（macOS 标题栏）
  static const double desktopTopPadding = 32.0;

  // ==================== Helper Methods ====================

  /// 创建对称间距
  static EdgeInsets symmetric({double? horizontal, double? vertical}) {
    return EdgeInsets.symmetric(
      horizontal: horizontal ?? 0,
      vertical: vertical ?? 0,
    );
  }

  /// 创建只有某一侧的间距
  static EdgeInsets only({
    double left = 0,
    double top = 0,
    double right = 0,
    double bottom = 0,
  }) {
    return EdgeInsets.only(
      left: left,
      top: top,
      right: right,
      bottom: bottom,
    );
  }
}
