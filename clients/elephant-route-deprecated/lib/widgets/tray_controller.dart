import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/services/tray_service.dart';
import '../providers/vpn_provider.dart';
import '../providers/auth_provider.dart';
import 'package:window_manager/window_manager.dart';

class TrayController extends StatefulWidget {
  final Widget child;

  const TrayController({super.key, required this.child});

  @override
  State<TrayController> createState() => _TrayControllerState();
}

class _TrayControllerState extends State<TrayController> with WindowListener {
  @override
  void initState() {
    super.initState();
    if (Platform.isWindows) windowManager.addListener(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initTrayService();
    });
  }

  void _initTrayService() {
    final trayService = TrayService();

    // Bind the toggle callback
    trayService.onToggleVpn = () async {
      debugPrint('TRAY_CTRL: Received toggle VPN request');
      final authProvider = context.read<AuthProvider>();
      final vpnProvider = context.read<VpnProvider>();

      // Ensure user is logged in
      if (!authProvider.isLoggedIn) {
        debugPrint('TRAY_CTRL: User not logged in, ignoring toggle.');
        // Pop window to login screen and ask them to log in ideally?
        return;
      }

      // Prevent rapid tapping
      if (vpnProvider.isProcessing) {
        debugPrint('TRAY_CTRL: VPN is processing, ignoring toggle.');
        return;
      }

      debugPrint('TRAY_CTRL: Invoking vpnProvider.toggle()...');
      await vpnProvider.toggle();
    };
    trayService.onExitApp = () async {
      final vpnProvider = context.read<VpnProvider>();
      if (vpnProvider.isConnected || vpnProvider.isProcessing) {
        await vpnProvider.disconnect();
      }
    };

    // Add listener to update tray menu when VPN state changes
    final vpnProvider = context.read<VpnProvider>();
    _lastIsConnected = vpnProvider.isConnected;
    trayService.updateMenu(_lastIsConnected!); // Initial state
    vpnProvider.addListener(_onVpnStateChanged);
  }

  bool? _lastIsConnected;

  void _onVpnStateChanged() {
    if (!mounted) return;
    final isConnected = context.read<VpnProvider>().isConnected;
    if (_lastIsConnected != isConnected) {
      _lastIsConnected = isConnected;
      TrayService().updateMenu(isConnected);
    }
  }

  @override
  void dispose() {
    if (Platform.isWindows) windowManager.removeListener(this);
    TrayService().onToggleVpn = null;
    TrayService().onExitApp = null;
    if (mounted) {
      context.read<VpnProvider>().removeListener(_onVpnStateChanged);
    }
    super.dispose();
  }

  @override
  void onWindowClose() async {
    await windowManager.hide();
  }

  @override
  Widget build(BuildContext context) {
    return widget.child;
  }
}
