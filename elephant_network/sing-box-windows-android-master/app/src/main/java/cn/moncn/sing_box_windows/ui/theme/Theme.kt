package cn.moncn.sing_box_windows.ui.theme

import android.app.Activity
import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

// ==================== 深色主题配色方案 ====================

private val DarkColorScheme = darkColorScheme(
    // 主色
    primary = Primary,
    onPrimary = Color(0xFFFFFFFF),
    primaryContainer = PrimaryContainerDark,
    onPrimaryContainer = PrimaryLight,

    // 次要色
    secondary = Secondary,
    onSecondary = Color(0xFFFFFFFF),
    secondaryContainer = SecondaryContainerDark,
    onSecondaryContainer = SecondaryLight,

    // 第三色（用于强调）
    tertiary = Info,
    onTertiary = Color(0xFFFFFFFF),
    tertiaryContainer = InfoContainer,
    onTertiaryContainer = InfoLight,

    // 背景
    background = BackgroundDark,
    onBackground = OnBackgroundDark,

    // 表面
    surface = SurfaceDark,
    onSurface = OnSurfaceDark,
    surfaceVariant = SurfaceVariantDark,
    onSurfaceVariant = OnSurfaceVariantDark,

    // 边框
    outline = OutlineDark,
    outlineVariant = OutlineVariantDark,

    // 错误
    error = Error,
    onError = Color(0xFFFFFFFF),
    errorContainer = ErrorContainer,
    onErrorContainer = ErrorDim,

    // 反色表面
    inverseSurface = Color(0xFFE2E8F0),
    inverseOnSurface = Color(0xFF1E293B),

    // 反色主色
    inversePrimary = PrimaryDim
)

// ==================== 浅色主题配色方案 ====================

private val LightColorScheme = lightColorScheme(
    // 主色
    primary = PrimaryDim,
    onPrimary = Color(0xFFFFFFFF),
    primaryContainer = PrimaryContainer,
    onPrimaryContainer = PrimaryDim,

    // 次要色
    secondary = SecondaryDim,
    onSecondary = Color(0xFFFFFFFF),
    secondaryContainer = SecondaryContainer,
    onSecondaryContainer = SecondaryDim,

    // 第三色（用于强调）
    tertiary = InfoDim,
    onTertiary = Color(0xFFFFFFFF),
    tertiaryContainer = InfoContainer,
    onTertiaryContainer = InfoDim,

    // 背景
    background = BackgroundLight,
    onBackground = OnBackgroundLight,

    // 表面
    surface = SurfaceLight,
    onSurface = OnSurfaceLight,
    surfaceVariant = SurfaceVariantLight,
    onSurfaceVariant = OnSurfaceVariantLight,

    // 边框
    outline = OutlineLight,
    outlineVariant = OutlineVariantLight,

    // 错误
    error = ErrorDim,
    onError = Color(0xFFFFFFFF),
    errorContainer = ErrorContainer,
    onErrorContainer = Error,

    // 反色表面
    inverseSurface = Color(0xFF1E293B),
    inverseOnSurface = Color(0xFFE2E8F0),

    // 反色主色
    inversePrimary = PrimaryLight
)

// ==================== 主题入口 ====================

@Composable
fun SingboxwindowsTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    // 动态配色（Android 12+）
    dynamicColor: Boolean = false,
    content: @Composable () -> Unit
) {
    val colorScheme = when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            val context = LocalContext.current
            if (darkTheme) {
                androidx.compose.material3.dynamicDarkColorScheme(context)
            } else {
                androidx.compose.material3.dynamicLightColorScheme(context)
            }
        }
        darkTheme -> DarkColorScheme
        else -> LightColorScheme
    }

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            val controller = WindowCompat.getInsetsController(window, view)
            // 根据主题深浅设置状态栏和导航栏图标颜色
            controller.isAppearanceLightStatusBars = !darkTheme
            controller.isAppearanceLightNavigationBars = !darkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        content = content
    )
}
