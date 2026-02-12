package cn.moncn.sing_box_windows.ui.theme

import androidx.compose.ui.graphics.Color

// ==================== 主色调 - 现代靛蓝体系 ====================

// 主色 - 靛蓝色
val Primary = Color(0xFF6366F1)           // 靛蓝主色
val PrimaryDim = Color(0xFF4F46E5)        // 深靛蓝
val PrimaryLight = Color(0xFF818CF8)      // 浅靛蓝
val PrimaryContainer = Color(0xFFEEF2FF)  // 靛蓝容器（浅色）
val PrimaryContainerDark = Color(0xFF312E81) // 靛蓝容器（深色）

// 次要色 - 紫罗兰
val Secondary = Color(0xFF8B5CF6)         // 紫罗兰
val SecondaryDim = Color(0xFF7C3AED)      // 深紫罗兰
val SecondaryLight = Color(0xFFA78BFA)    // 浅紫罗兰
val SecondaryContainer = Color(0xFFF3E8FF) // 紫罗兰容器（浅色）
val SecondaryContainerDark = Color(0xFF4C1D95) // 紫罗兰容器（深色）

// ==================== 功能色 ====================

// 成功色 - 翡翠绿
val Success = Color(0xFF10B981)           // 翡翠绿
val SuccessDim = Color(0xFF059669)        // 深翡翠
val SuccessContainer = Color(0xFFD1FAE5)  // 浅翡翠容器

// 警告色 - 琥珀
val Warning = Color(0xFFF59E0B)           // 琥珀
val WarningDim = Color(0xFFD97706)        // 深琥珀
val WarningContainer = Color(0xFFFEF3C7)  // 浅琥珀容器

// 错误色 - 玫瑰红
val Error = Color(0xFFEF4444)             // 玫瑰红
val ErrorDim = Color(0xFFDC2626)          // 深玫瑰
val ErrorContainer = Color(0xFFFEE2E2)    // 浅玫瑰容器

// 信息色 - 天蓝
val Info = Color(0xFF3B82F6)              // 天蓝
val InfoDim = Color(0xFF2563EB)           // 深天蓝
val InfoContainer = Color(0xFFDBEAFE)     // 浅天蓝容器
val InfoLight = Color(0xFF60A5FA)         // 浅天蓝

// ==================== 中性色 - 深色主题 ====================

// 背景色
val BackgroundDark = Color(0xFF0F0F1A)    // 深邃背景
val SurfaceDark = Color(0xFF1A1A2E)       // 表面背景
val SurfaceVariantDark = Color(0xFF252542) // 表面变体

// 文本色
val OnBackgroundDark = Color(0xFFE2E8F0)  // 背景上的文字
val OnSurfaceDark = Color(0xFFE2E8F0)     // 表面上的文字
val OnSurfaceVariantDark = Color(0xFF94A3B8) // 次要文字

// 边框/分割线
val OutlineDark = Color(0xFF334155)       // 边框色
val OutlineVariantDark = Color(0xFF1E293B) // 变体边框

// ==================== 中性色 - 浅色主题 ====================

// 背景色
val BackgroundLight = Color(0xFFFAFAFF)   // 浅色背景
val SurfaceLight = Color(0xFFFFFFFF)      // 纯白表面
val SurfaceVariantLight = Color(0xFFF1F5F9) // 表面变体

// 文本色
val OnBackgroundLight = Color(0xFF1E293B) // 背景上的文字
val OnSurfaceLight = Color(0xFF1E293B)    // 表面上的文字
val OnSurfaceVariantLight = Color(0xFF64748B) // 次要文字

// 边框/分割线
val OutlineLight = Color(0xFFE2E8F0)      // 边框色
val OutlineVariantLight = Color(0xFFF1F5F9) // 变体边框

// ==================== 渐变色 ====================

// 主渐变 - 靛蓝到紫罗兰
val PrimaryGradient = listOf(
    Color(0xFF6366F1),  // 靛蓝
    Color(0xFF8B5CF6)   // 紫罗兰
)

// 成功渐变
val SuccessGradient = listOf(
    Color(0xFF10B981),  // 翡翠绿
    Color(0xFF34D399)   // 浅翡翠
)

// 背景渐变 - 深色
val DarkBackgroundGradient = listOf(
    Color(0xFF0F0F1A),
    Color(0xFF1A1A2E),
    Color(0xFF252542)
)

// 背景渐变 - 浅色
val LightBackgroundGradient = listOf(
    Color(0xFFFAFAFF),
    Color(0xFFF8FAFC),
    Color(0xFFF1F5F9)
)

// ==================== 状态色映射 ====================

// 连接状态颜色
val ConnectedColor = Success           // 已连接
val ConnectingColor = Warning          // 连接中
val ErrorColor = Error                 // 错误
val IdleColor = OnSurfaceVariantDark   // 未连接

// 延迟颜色
val LatencyLow = Success               // <150ms
val LatencyMedium = Warning            // <500ms
val LatencyHigh = Error                // >=500ms
val LatencyUnknown = OutlineDark       // 未知

// ==================== 半透明变体 ====================

// 带透明度的叠加层
val OverlayDark = Color(0x80000000)    // 50% 黑色遮罩
val OverlayLight = Color(0x40FFFFFF)   // 25% 白色遮罩

// 玻璃态背景
val GlassDark = Color(0xCC1A1A2E)      // 80% 不透明深色
val GlassLight = Color(0xCCFFFFFF)     // 80% 不透明白色

// 发光效果
val GlowPrimary = Color(0x406366F1)    // 靛蓝发光
val GlowSuccess = Color(0x4010B981)    // 绿色发光
val GlowError = Color(0x40EF4444)      // 红色发光
