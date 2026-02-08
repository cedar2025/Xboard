package cn.moncn.sing_box_windows.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import cn.moncn.sing_box_windows.ui.theme.BackgroundDark
import cn.moncn.sing_box_windows.ui.theme.BackgroundLight
import cn.moncn.sing_box_windows.ui.theme.DarkBackgroundGradient
import cn.moncn.sing_box_windows.ui.theme.GlowPrimary
import cn.moncn.sing_box_windows.ui.theme.LightBackgroundGradient
import cn.moncn.sing_box_windows.ui.theme.OutlineDark
import cn.moncn.sing_box_windows.ui.theme.OutlineLight
import cn.moncn.sing_box_windows.ui.theme.Primary
import cn.moncn.sing_box_windows.ui.theme.PrimaryGradient
import cn.moncn.sing_box_windows.ui.theme.Secondary
import cn.moncn.sing_box_windows.ui.theme.SurfaceDark
import cn.moncn.sing_box_windows.ui.theme.SurfaceVariantDark
import cn.moncn.sing_box_windows.ui.theme.SurfaceVariantLight

// ==================== 形状定义 ====================

/**
 * 超大圆角 - 用于页面级卡片
 */
val ShapeUltraLarge = RoundedCornerShape(28.dp)

/**
 * 大圆角 - 用于主要卡片
 */
val ShapeLarge = RoundedCornerShape(24.dp)

/**
 * 中圆角 - 用于常规卡片和按钮
 */
val ShapeMedium = RoundedCornerShape(20.dp)

/**
 * 小圆角 - 用于小型组件
 */
val ShapeSmall = RoundedCornerShape(16.dp)

/**
 * 微小圆角 - 用于标签和徽章
 */
val ShapeTiny = RoundedCornerShape(12.dp)

/**
 * 完全圆形 - 用于头像和图标按钮
 */
val ShapeCircle = CircleShape

// ==================== 全局背景 ====================

/**
 * 现代化全局背景
 * 带有渐变和装饰性模糊光斑
 */
@Composable
fun AppBackground(
    modifier: Modifier = Modifier
) {
    val scheme = MaterialTheme.colorScheme
    val isDark = isSystemInDarkTheme()

    Box(modifier = modifier) {
        // 主渐变背景
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    brush = Brush.verticalGradient(
                        colors = if (isDark) DarkBackgroundGradient else LightBackgroundGradient
                    )
                )
        )

        // 顶部装饰光斑 - 主色调
        Box(
            modifier = Modifier
                .size(280.dp)
                .offset(x = (-100).dp, y = (-100).dp)
                .shadow(
                    elevation = 80.dp,
                    shape = CircleShape,
                    ambientColor = Primary.copy(alpha = 0.3f),
                    spotColor = Primary.copy(alpha = 0.3f)
                )
                .background(
                    brush = Brush.radialGradient(
                        colors = listOf(
                            Primary.copy(alpha = if (isDark) 0.15f else 0.08f),
                            Color.Transparent
                        )
                    ),
                    shape = CircleShape
                )
        )

        // 右侧装饰光斑 - 次色调
        Box(
            modifier = Modifier
                .size(240.dp)
                .align(Alignment.CenterEnd)
                .offset(y = (-150).dp)
                .background(
                    brush = Brush.radialGradient(
                        colors = listOf(
                            Secondary.copy(alpha = if (isDark) 0.12f else 0.06f),
                            Color.Transparent
                        )
                    ),
                    shape = CircleShape
                )
        )

        // 底部装饰光斑
        Box(
            modifier = Modifier
                .size(320.dp)
                .align(Alignment.BottomStart)
                .offset(x = (-80).dp, y = 100.dp)
                .background(
                    brush = Brush.radialGradient(
                        colors = listOf(
                            Primary.copy(alpha = if (isDark) 0.1f else 0.05f),
                            Color.Transparent
                        )
                    ),
                    shape = CircleShape
                )
        )
    }
}

// ==================== 卡片组件 ====================

/**
 * 现代化卡片
 * 支持点击交互和自定义样式
 */
@Composable
fun NeoCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    containerColor: Color = MaterialTheme.colorScheme.surface,
    borderColor: Color = Color.Transparent,
    borderWidth: Float = 0.5f,
    elevation: Float = 0f,
    content: @Composable () -> Unit
) {
    val scheme = MaterialTheme.colorScheme
    val isDark = isSystemInDarkTheme()

    Card(
        modifier = modifier,
        shape = ShapeMedium,
        colors = CardDefaults.cardColors(containerColor = containerColor),
        elevation = CardDefaults.cardElevation(defaultElevation = elevation.dp),
        border = if (borderWidth > 0) {
            BorderStroke(
                width = borderWidth.dp,
                color = borderColor
            )
        } else {
            val defaultBorderColor = if (isDark) {
                scheme.outline.copy(alpha = 0.1f)
            } else {
                scheme.outline.copy(alpha = 0.3f)
            }
            BorderStroke(0.5.dp, defaultBorderColor)
        }
    ) {
        Box(
            modifier = Modifier
                .then(
                    if (onClick != null) {
                        Modifier.clickable(onClick = onClick)
                    } else {
                        Modifier
                    }
                )
                .padding(16.dp)
        ) {
            content()
        }
    }
}

/**
 * 玻璃态卡片
 * 带有毛玻璃效果的现代卡片
 */
@Composable
fun GlassCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    content: @Composable () -> Unit
) {
    val isDark = isSystemInDarkTheme()
    val scheme = MaterialTheme.colorScheme

    Surface(
        modifier = modifier.then(
            if (onClick != null) {
                Modifier.clickable(onClick = onClick)
            } else {
                Modifier
            }
        ),
        shape = ShapeMedium,
        color = if (isDark) {
            scheme.surface.copy(alpha = 0.8f)
        } else {
            scheme.surface.copy(alpha = 0.7f)
        },
        border = BorderStroke(
            1.dp,
            if (isDark) {
                Color.White.copy(alpha = 0.08f)
            } else {
                Color.White.copy(alpha = 0.5f)
            }
        ),
        shadowElevation = 8.dp
    ) {
        Box(modifier = Modifier.padding(16.dp)) {
            content()
        }
    }
}

// ==================== 数据展示组件 ====================

/**
 * 现代化数据瓷砖
 * 用于展示统计信息
 */
@Composable
fun MetricTile(
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    icon: (@Composable () -> Unit)? = null,
    accent: Boolean = false,
    onClick: (() -> Unit)? = null
) {
    val scheme = MaterialTheme.colorScheme
    val isDark = isSystemInDarkTheme()

    NeoCard(
        modifier = modifier,
        onClick = onClick,
        containerColor = if (accent) {
            if (isDark) {
                scheme.primaryContainer.copy(alpha = 0.5f)
            } else {
                scheme.primaryContainer.copy(alpha = 0.7f)
            }
        } else {
            scheme.surface
        }
    ) {
        Column(
            horizontalAlignment = Alignment.Start,
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            if (icon != null) {
                icon()
            }
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = scheme.onSurfaceVariant
            )
            Text(
                text = value,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                color = if (accent) scheme.primary else scheme.onSurface
            )
        }
    }
}

/**
 * 渐变数据瓷砖
 * 带有渐变背景的强调型数据卡片
 */
@Composable
fun GradientMetricTile(
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    icon: (@Composable () -> Unit)? = null,
    gradient: List<Color> = PrimaryGradient,
    onClick: (() -> Unit)? = null
) {
    Card(
        modifier = modifier.then(
            if (onClick != null) {
                Modifier.clickable(onClick = onClick)
            } else {
                Modifier
            }
        ),
        shape = ShapeMedium
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(brush = Brush.horizontalGradient(colors = gradient))
                .padding(16.dp)
        ) {
            Column(
                horizontalAlignment = Alignment.Start,
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                if (icon != null) {
                    icon()
                }
                Text(
                    text = label,
                    style = MaterialTheme.typography.labelMedium,
                    color = Color.White.copy(alpha = 0.85f)
                )
                Text(
                    text = value,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.SemiBold,
                    color = Color.White
                )
            }
        }
    }
}

// ==================== 状态徽章组件 ====================

/**
 * 现代化状态徽章
 * 用于显示状态信息
 */
@Composable
fun StatusBadge(
    text: String,
    color: Color,
    modifier: Modifier = Modifier,
    size: StatusBadgeSize = StatusBadgeSize.Medium
) {
    val scheme = MaterialTheme.colorScheme
    val isDark = isSystemInDarkTheme()

    val bgColor = color.copy(alpha = 0.12f)
    val textColor = color

    val (horizontalPadding, verticalPadding, textStyle) = when (size) {
        StatusBadgeSize.Small -> Triple(8.dp, 4.dp, MaterialTheme.typography.labelSmall)
        StatusBadgeSize.Medium -> Triple(10.dp, 5.dp, MaterialTheme.typography.labelMedium)
        StatusBadgeSize.Large -> Triple(12.dp, 6.dp, MaterialTheme.typography.labelLarge)
    }

    Surface(
        modifier = modifier,
        shape = ShapeTiny,
        color = bgColor,
        border = BorderStroke(
            width = if (isDark) 0.5.dp else 1.dp,
            color = color.copy(alpha = if (isDark) 0.3f else 0.2f)
        )
    ) {
        Row(
            modifier = Modifier
                .padding(horizontal = horizontalPadding, vertical = verticalPadding),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(6.dp)
                    .background(textColor, ShapeCircle)
            )
            Text(
                text = text,
                style = textStyle,
                color = textColor,
                fontWeight = FontWeight.Medium
            )
        }
    }
}

/**
 * 状态徽章尺寸
 */
enum class StatusBadgeSize {
    Small,
    Medium,
    Large
}

// ==================== 区块组件 ====================

/**
 * 现代化区块组件
 * 用于分组相关内容
 */
@Composable
fun Section(
    title: @Composable () -> Unit,
    modifier: Modifier = Modifier,
    action: (@Composable () -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit
) {
    Column(
        modifier = modifier.padding(horizontal = 4.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(modifier = Modifier.weight(1f)) {
                title()
            }
            if (action != null) {
                action()
            }
        }
        content()
    }
}

// ==================== 按钮组件 ====================

/**
 * 主按钮
 * 带有渐变效果的现代化按钮
 */
@Composable
fun PrimaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    gradient: List<Color> = PrimaryGradient
) {
    val interactionSource = remember { MutableInteractionSource() }
    val isPressed by interactionSource.collectIsPressedAsState()

    Card(
        onClick = onClick,
        modifier = modifier.height(52.dp),
        enabled = enabled,
        interactionSource = interactionSource,
        shape = ShapeMedium,
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        border = null
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    brush = Brush.horizontalGradient(colors = gradient),
                    shape = ShapeMedium
                )
                .then(
                    if (isPressed) {
                        Modifier.shadow(
                            elevation = 2.dp,
                            shape = ShapeMedium,
                            ambientColor = Color.Black.copy(alpha = 0.1f),
                            spotColor = Color.Black.copy(alpha = 0.1f)
                        )
                    } else {
                        Modifier.shadow(
                            elevation = 8.dp,
                            shape = ShapeMedium,
                            ambientColor = Primary.copy(alpha = 0.3f),
                            spotColor = Primary.copy(alpha = 0.3f)
                        )
                    }
                ),
            contentAlignment = Alignment.Center
        ) {
            Text(
                text = text,
                style = MaterialTheme.typography.labelLarge,
                color = Color.White,
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

/**
 * 次要按钮
 * 带边框的次要操作按钮
 */
@Composable
fun SecondaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true
) {
    val scheme = MaterialTheme.colorScheme
    val isDark = isSystemInDarkTheme()

    Card(
        onClick = onClick,
        modifier = modifier.height(52.dp),
        enabled = enabled,
        shape = ShapeMedium,
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        border = BorderStroke(
            1.5.dp,
            if (isDark) {
                scheme.outline.copy(alpha = 0.5f)
            } else {
                scheme.outline.copy(alpha = 0.8f)
            }
        )
    ) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            Text(
                text = text,
                style = MaterialTheme.typography.labelLarge,
                color = scheme.onSurface,
                fontWeight = FontWeight.Medium
            )
        }
    }
}

// ==================== 信息行组件 ====================

/**
 * 信息展示行
 * 用于键值对信息的展示
 */
@Composable
fun InfoRow(
    label: String,
    value: String,
    modifier: Modifier = Modifier
) {
    val scheme = MaterialTheme.colorScheme

    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            color = scheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            color = scheme.onSurface,
            fontWeight = FontWeight.Medium
        )
    }
}

// ==================== 分割线组件 ====================

/**
 * 现代化分割线
 */
@Composable
fun NeoDivider(
    modifier: Modifier = Modifier
) {
    val scheme = MaterialTheme.colorScheme
    val isDark = isSystemInDarkTheme()

    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(0.5.dp)
            .background(
                if (isDark) {
                    scheme.outline.copy(alpha = 0.15f)
                } else {
                    scheme.outline.copy(alpha = 0.3f)
                }
            )
    )
}
