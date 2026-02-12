import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../screens/home/dashboard_screen.dart';
import '../screens/home/node_selection_screen.dart';
import '../screens/plan/plan_selection_screen.dart';
import '../screens/profile/profile_screen.dart';
import '../providers/navigation_provider.dart';
import 'app_navigation_bar.dart';

class MainScaffold extends StatelessWidget {
  const MainScaffold({super.key});

  Widget _buildPage(NavigationPage page) {
    switch (page) {
      case NavigationPage.dashboard:
        return const DashboardScreen();
      case NavigationPage.nodes:
        return const NodeSelectionScreen();
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
