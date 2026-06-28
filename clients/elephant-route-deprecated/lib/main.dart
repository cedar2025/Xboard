import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:window_manager/window_manager.dart';
import 'core/api/dio_client.dart';
import 'core/theme/app_colors.dart';
import 'core/theme/app_text_styles.dart';
import 'core/theme/app_dimensions.dart';
import 'providers/auth_provider.dart';
import 'providers/user_provider.dart';
import 'providers/vpn_provider.dart';
import 'providers/theme_provider.dart';
import 'providers/node_provider.dart';
import 'providers/language_provider.dart';
import 'providers/navigation_provider.dart';
import 'providers/config_provider.dart';
import 'providers/app_update_provider.dart';
import 'core/services/tray_service.dart';
import 'core/services/app_logger.dart';
import 'core/singbox/vpn_manager.dart';
import 'core/singbox/mock_vpn_service.dart';
import 'core/singbox/real_vpn_service.dart';
import 'core/singbox/macos_vpn_service.dart';
import 'core/singbox/windows_vpn_service.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/auth/forgot_password_screen.dart';
import 'widgets/main_scaffold.dart';
import 'screens/profile/profile_screen.dart';
import 'utils/platform_utils.dart';
import 'widgets/tray_controller.dart';

import 'package:launch_at_startup/launch_at_startup.dart';
import 'package:package_info_plus/package_info_plus.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AppLogger.instance.initialize();

  // 桌面端：初始化窗口管理器与开机自启
  if (PlatformUtils.isDesktop) {
    if (Platform.isWindows) {
      try {
        PackageInfo packageInfo = await PackageInfo.fromPlatform();
        launchAtStartup.setup(
          appName: packageInfo.appName,
          appPath: Platform.resolvedExecutable,
        );
        // 默认开启自启（可转移到偏好设置）
        await launchAtStartup.enable();
      } catch (e) {
        debugPrint('Failed to setup launchAtStartup: $e');
      }
    }

    await windowManager.ensureInitialized();

    const windowOptions = WindowOptions(
      size: Size(1000, 700),
      minimumSize: Size(1000, 700),
      center: true,
      backgroundColor: Colors.transparent,
      skipTaskbar: false,
      titleBarStyle: TitleBarStyle.normal,
      title: '',
    );

    await windowManager.waitUntilReadyToShow(windowOptions, () async {
      await windowManager.show();
      await windowManager.focus();
    });
  }

  // 初始化托盘(桌面端)
  await TrayService().init();

  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        // 核心依赖 - DioClient 必须最先注册
        Provider<DioClient>(
          create: (_) => DioClient(),
        ),
        // 提供 VpnManager
        // 桌面端（macOS/Windows）使用对应 Service，其他桌面端降级为 MockVpnService
        // 移动端使用 RealVpnService（对接原生 sing-box SDK）
        Provider<VpnManager>(
          create: (_) {
            if (Platform.isMacOS) {
              return MacosVpnService();
            } else if (Platform.isWindows) {
              return WindowsVpnService();
            } else if (PlatformUtils.isDesktop) {
              return MockVpnService();
            } else {
              return RealVpnService();
            }
          },
          dispose: (_, vpn) => vpn.dispose(),
        ),
        // 提供 AuthProvider
        ChangeNotifierProvider<AuthProvider>(
          create: (context) => AuthProvider(context.read<DioClient>()),
        ),
        // 提供 UserProvider
        ChangeNotifierProvider<UserProvider>(
          create: (context) => UserProvider(context.read<DioClient>()),
        ),
        // 提供 ConfigProvider (前置, 核心网络依赖)
        ChangeNotifierProvider<ConfigProvider>(
          create: (_) => ConfigProvider(),
        ),
        ChangeNotifierProvider<AppUpdateProvider>(
          create: (_) => AppUpdateProvider(),
        ),
        // 提供 NodeProvider
        ChangeNotifierProvider<NodeProvider>(
          create: (context) => NodeProvider(
            context.read<DioClient>(),
            context.read<VpnManager>(),
            context.read<ConfigProvider>(),
          ),
        ),
        // 提供 VpnProvider
        ChangeNotifierProvider<VpnProvider>(
          create: (context) => VpnProvider(
            context.read<DioClient>(),
            context.read<VpnManager>(),
            context.read<ConfigProvider>(),
          ),
        ),
        // 提供 ThemeProvider
        ChangeNotifierProvider<ThemeProvider>(
          create: (_) => ThemeProvider(),
        ),
        // 提供 LanguageProvider
        ChangeNotifierProvider<LanguageProvider>(
          create: (_) => LanguageProvider(),
        ),
        // 提供 NavigationProvider
        ChangeNotifierProvider<NavigationProvider>(
          create: (_) => NavigationProvider(),
        ),
      ],
      child: Consumer2<ThemeProvider, LanguageProvider>(
        builder: (context, themeProvider, languageProvider, _) {
          return TrayController(
            child: MaterialApp(
              title: languageProvider.translate('app_name'),
              locale: languageProvider.locale,
              scrollBehavior: AppScrollBehavior(),
              themeMode: themeProvider.themeMode,
              theme: ThemeData(
                useMaterial3: true,
                brightness: Brightness.light,
                scaffoldBackgroundColor: AppColors.lightBackground,
                colorScheme: ColorScheme.light(
                  primary: AppColors.primary,
                  secondary: AppColors.primaryLight,
                  surface: AppColors.lightCard,
                  error: AppColors.error,
                ),
                appBarTheme: AppBarTheme(
                  backgroundColor: AppColors.lightBackground,
                  foregroundColor: AppColors.lightTextPrimary,
                  elevation: 0,
                  systemOverlayStyle: SystemUiOverlayStyle.dark,
                  titleTextStyle: AppTextStyles.titleMedium.copyWith(
                    color: AppColors.lightTextPrimary,
                  ),
                ),
                cardTheme: CardThemeData(
                  color: AppColors.lightCard,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: AppDimensions.borderRadiusLarge,
                    side: BorderSide(
                      color: AppColors.lightCardBorder,
                      width: 1,
                    ),
                  ),
                ),
                elevatedButtonTheme: ElevatedButtonThemeData(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: AppDimensions.buttonPadding,
                    shape: RoundedRectangleBorder(
                      borderRadius: AppDimensions.borderRadiusMedium,
                    ),
                    textStyle: AppTextStyles.button,
                  ),
                ),
                inputDecorationTheme: InputDecorationTheme(
                  filled: true,
                  fillColor: AppColors.lightInputBackground,
                  border: OutlineInputBorder(
                    borderRadius: AppDimensions.borderRadiusMedium,
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: AppDimensions.borderRadiusMedium,
                    borderSide: BorderSide(
                      color: AppColors.primary,
                      width: 2,
                    ),
                  ),
                  contentPadding: AppDimensions.inputPadding,
                ),
                textTheme: TextTheme(
                  displayLarge: AppTextStyles.displayLarge.copyWith(
                    color: AppColors.lightTextPrimary,
                  ),
                  titleLarge: AppTextStyles.titleLarge.copyWith(
                    color: AppColors.lightTextPrimary,
                  ),
                  bodyLarge: AppTextStyles.bodyLarge.copyWith(
                    color: AppColors.lightTextPrimary,
                  ),
                  bodyMedium: AppTextStyles.bodyMedium.copyWith(
                    color: AppColors.lightTextSecondary,
                  ),
                ),
              ),
              darkTheme: ThemeData(
                useMaterial3: true,
                brightness: Brightness.dark,
                scaffoldBackgroundColor: AppColors.darkBackground,
                colorScheme: ColorScheme.dark(
                  primary: AppColors.primaryLight,
                  secondary: AppColors.primary,
                  surface: AppColors.darkCard,
                  error: AppColors.errorLight,
                ),
                appBarTheme: AppBarTheme(
                  backgroundColor: Colors.transparent,
                  foregroundColor: AppColors.darkTextPrimary,
                  elevation: 0,
                  systemOverlayStyle: SystemUiOverlayStyle.light,
                  titleTextStyle: AppTextStyles.titleMedium.copyWith(
                    color: AppColors.darkTextPrimary,
                  ),
                ),
                cardTheme: CardThemeData(
                  color: AppColors.darkCard,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: AppDimensions.borderRadiusLarge,
                    side: BorderSide(
                      color: AppColors.darkCardBorder,
                      width: 1,
                    ),
                  ),
                ),
                elevatedButtonTheme: ElevatedButtonThemeData(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primaryDark,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: AppDimensions.buttonPadding,
                    shape: RoundedRectangleBorder(
                      borderRadius: AppDimensions.borderRadiusMedium,
                    ),
                    textStyle: AppTextStyles.button,
                  ),
                ),
                inputDecorationTheme: InputDecorationTheme(
                  filled: true,
                  fillColor: AppColors.darkInputBackground,
                  border: OutlineInputBorder(
                    borderRadius: AppDimensions.borderRadiusMedium,
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: AppDimensions.borderRadiusMedium,
                    borderSide: BorderSide(
                      color: AppColors.primaryDark,
                      width: 2,
                    ),
                  ),
                  contentPadding: AppDimensions.inputPadding,
                ),
                textTheme: TextTheme(
                  displayLarge: AppTextStyles.displayLarge.copyWith(
                    color: AppColors.darkTextPrimary,
                  ),
                  titleLarge: AppTextStyles.titleLarge.copyWith(
                    color: AppColors.darkTextPrimary,
                  ),
                  bodyLarge: AppTextStyles.bodyLarge.copyWith(
                    color: AppColors.darkTextPrimary,
                  ),
                  bodyMedium: AppTextStyles.bodyMedium.copyWith(
                    color: AppColors.darkTextSecondary,
                  ),
                ),
              ),
              home: const SplashScreen(),
              routes: {
                '/login': (context) => const LoginScreen(),
                '/register': (context) => const RegisterScreen(),
                '/forget': (context) => const ForgotPasswordScreen(),
                '/home': (context) => const MainScaffold(),
                '/profile': (context) => const ProfileScreen(),
              },
            ),
          );
        },
      ),
    );
  }
}

// 启动页面 - 检查登录状态
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _checkLoginStatus();
  }

  Future<void> _checkLoginStatus() async {
    try {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);

      final isLoggedIn = await authProvider.restoreLoginStatus();

      if (!mounted) return;

      // 延迟一小段时间显示启动页动画
      await Future.delayed(const Duration(milliseconds: 1500));

      if (!mounted) return;

      // 检查登录状态并跳转
      if (isLoggedIn) {
        Navigator.of(context).pushReplacementNamed('/home');
      } else {
        Navigator.of(context).pushReplacementNamed('/login');
      }
    } catch (e) {
      // 如果出错，默认跳转到登录页
      if (mounted) {
        await Future.delayed(const Duration(milliseconds: 1500));
        if (mounted) {
          Navigator.of(context).pushReplacementNamed('/login');
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0E27),
      body: Container(
        decoration: const BoxDecoration(
          gradient: RadialGradient(
            center: Alignment.center,
            radius: 1.5,
            colors: [
              Color(0xFF1E2A4A),
              Color(0xFF0A0E27),
            ],
          ),
        ),
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Logo
              ClipRRect(
                borderRadius: BorderRadius.circular(24),
                child: Image.asset(
                  'assets/images/logo_icon_macos.png',
                  width: 120,
                  height: 120,
                ),
              ),
              const SizedBox(height: 32),
              const Text(
                '大象网络',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w900,
                  color: Colors.white,
                  letterSpacing: 4,
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.greenAccent,
                  borderRadius: BorderRadius.circular(4),
                ),
                child: const Text(
                  'CONNECT THE UNSEEN',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF0A0E27),
                    letterSpacing: 2,
                  ),
                ),
              ),
              const SizedBox(height: 48),
              const Text(
                '极速 · 隐秘 · 无界',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: Colors.white,
                  letterSpacing: 4,
                ),
              ),
              const SizedBox(height: 16),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 40),
                child: Text(
                  '专为极客打造的下一代全球网络加速服务\n突破物理边界，重塑数字自由',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.white70,
                    height: 1.5,
                    letterSpacing: 1,
                  ),
                ),
              ),
              const SizedBox(height: 100),
              const SizedBox(
                width: 40,
                height: 40,
                child: CircularProgressIndicator(
                  color: Colors.greenAccent,
                  strokeWidth: 2,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// 自定义滚动行为：在所有平台（包括 Android）上强制使用 iOS 风格的回弹效果
class AppScrollBehavior extends MaterialScrollBehavior {
  @override
  ScrollPhysics getScrollPhysics(BuildContext context) {
    return const BouncingScrollPhysics();
  }
}
