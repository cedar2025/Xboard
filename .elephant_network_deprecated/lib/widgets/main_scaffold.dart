import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../screens/home/dashboard_screen.dart';
import '../screens/plan/plan_selection_screen.dart';
import '../screens/profile/profile_screen.dart';
import '../providers/navigation_provider.dart';
import '../providers/app_update_provider.dart';
import '../utils/platform_utils.dart';
import 'app_navigation_bar.dart';
import 'app_update_dialog.dart';
import 'desktop_sidebar.dart';

class MainScaffold extends StatefulWidget {
  const MainScaffold({super.key});

  @override
  State<MainScaffold> createState() => _MainScaffoldState();
}

class _MainScaffoldState extends State<MainScaffold> {
  bool _checkedUpdate = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_checkedUpdate) return;
    _checkedUpdate = true;

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      final provider = context.read<AppUpdateProvider>();
      final update = await provider.checkForUpdate(silent: true);
      if (!mounted || update == null || !provider.shouldPrompt) return;
      await showAppUpdateDialog(context, update);
    });
  }

  Widget _buildPage(NavigationPage page) {
    switch (page) {
      case NavigationPage.dashboard:
        return const DashboardScreen();
      case NavigationPage.shop:
        return const PlanSelectionScreen();
      case NavigationPage.profile:
        return const ProfileScreen();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Consumer<NavigationProvider>(
        builder: (context, nav, child) {
          // 桌面端：侧边栏 + 内容区
          if (PlatformUtils.isDesktop) {
            return Row(
              children: [
                // 侧边栏导航
                DesktopSidebar(
                  currentPage: nav.currentPage,
                  onPageChanged: (page) {
                    nav.setPage(page);
                  },
                ),
                // 主内容区
                Expanded(
                  child: _buildPage(nav.currentPage),
                ),
              ],
            );
          }

          // 移动端：浮动底部导航栏
          return Stack(
            children: [
              // 主内容
              _buildPage(nav.currentPage),

              // 浮动导航栏
              AppNavigationBar(
                currentPage: nav.currentPage,
                onPageChanged: (page) {
                  nav.setPage(page);
                },
              ),
            ],
          );
        },
      ),
    );
  }
}
