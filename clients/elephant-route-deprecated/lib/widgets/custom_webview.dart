import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart' as mobile_webview;
import 'package:webview_windows/webview_windows.dart' as windows_webview;

import '../core/theme/app_colors.dart';
import '../core/theme/app_text_styles.dart';
import '../utils/platform_utils.dart';

class CustomWebView extends StatefulWidget {
  final String title;
  final String url;
  final bool injectAuth;
  final bool enableHiding;
  final List<String>? hiddenSelectors;

  const CustomWebView({
    super.key,
    required this.title,
    required this.url,
    this.injectAuth = true,
    this.enableHiding = true,
    this.hiddenSelectors,
  });

  @override
  State<CustomWebView> createState() => _CustomWebViewState();
}

class _CustomWebViewState extends State<CustomWebView> {
  mobile_webview.WebViewController? _mobileController;
  windows_webview.WebviewController? _windowsController;
  final List<StreamSubscription<Object?>> _windowsSubscriptions = [];
  double _progress = 0;
  String? _windowsError;

  String get _normalizedUrl {
    if (widget.url.contains('/#/') && !widget.url.contains('/app#/')) {
      return widget.url.replaceFirst('/#/', '/app#/');
    }
    return widget.url;
  }

  @override
  void initState() {
    super.initState();
    if (Platform.isWindows) {
      _setupWindowsController();
    } else {
      _setupMobileController();
    }
  }

  Future<void> _setupMobileController() async {
    final controller = mobile_webview.WebViewController();
    _mobileController = controller;
    try {
      await controller.setJavaScriptMode(
        mobile_webview.JavaScriptMode.unrestricted,
      );
      if (!PlatformUtils.isMacOS) {
        await controller.setBackgroundColor(Colors.white);
      }
      await controller.setNavigationDelegate(
        mobile_webview.NavigationDelegate(
          onProgress: (progress) {
            if (mounted) setState(() => _progress = progress / 100);
          },
          onPageFinished: (_) => _injectHideLayoutCss(),
          onWebResourceError: (error) {
            debugPrint('WebView error: ${error.description}');
          },
        ),
      );
      await controller.loadRequest(Uri.parse(_normalizedUrl));
    } catch (error) {
      debugPrint('WebView initialization failed: $error');
    }
  }

  Future<void> _setupWindowsController() async {
    final controller = windows_webview.WebviewController();
    _windowsController = controller;
    try {
      final version =
          await windows_webview.WebviewController.getWebViewVersion();
      if (version == null || version.isEmpty) {
        throw PlatformException(
          code: 'WEBVIEW2_MISSING',
          message: '未检测到 Microsoft Edge WebView2 Runtime',
        );
      }
      await controller.initialize();
      await controller.setPopupWindowPolicy(
        windows_webview.WebviewPopupWindowPolicy.sameWindow,
      );
      _windowsSubscriptions.add(
        controller.loadingState.listen((state) {
          if (!mounted) return;
          setState(() {
            _progress =
                state == windows_webview.LoadingState.loading ? 0.35 : 1;
          });
          if (state == windows_webview.LoadingState.navigationCompleted) {
            _injectHideLayoutCss();
          }
        }),
      );
      await controller.loadUrl(_normalizedUrl);
      if (mounted) setState(() {});
    } on PlatformException catch (error) {
      if (!mounted) return;
      setState(() {
        _windowsError = error.code == 'WEBVIEW2_MISSING'
            ? error.message
            : 'WebView2 初始化失败：${error.message ?? error.code}';
      });
    } catch (error) {
      if (mounted) setState(() => _windowsError = 'WebView2 初始化失败：$error');
    }
  }

  Future<void> _injectHideLayoutCss() async {
    if (!widget.enableHiding) return;
    final selectors = widget.hiddenSelectors ??
        const [
          '.n-layout-sider',
          '.sidebar',
          'aside',
          'nav.n-menu',
          'header',
          '.n-layout-header',
          '.main-header',
          '.navbar',
          '#ripple-canvas',
        ];
    final selectorText = selectors.join(',');
    final script = """
      (function() {
        if (document.getElementById('flutter-hide-layout-css')) return;
        var style = document.createElement('style');
        style.id = 'flutter-hide-layout-css';
        style.innerHTML = `$selectorText { display: none !important; }
          .n-layout, .n-layout-content, .content-wrapper, main, .v-main {
            margin-left: 0 !important; padding-top: 0 !important;
            padding-left: 0 !important; width: 100% !important;
            max-width: 100% !important;
          }`;
        (document.head || document.documentElement).appendChild(style);
      })();
    """;
    try {
      if (Platform.isWindows) {
        await _windowsController?.executeScript(script);
      } else {
        await _mobileController?.runJavaScript(script);
      }
    } catch (error) {
      debugPrint('WebView layout injection failed: $error');
    }
  }

  Future<void> _openExternally() async {
    final uri = Uri.parse(_normalizedUrl);
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('无法打开系统浏览器')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor:
          isDark ? AppColors.darkBackground : AppColors.lightBackground,
      appBar: AppBar(
        title: Text(widget.title, style: AppTextStyles.titleMedium),
        backgroundColor: isDark ? AppColors.darkCard : AppColors.lightCard,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).pop(),
        ),
        actions: [
          IconButton(
            tooltip: '在系统浏览器中打开',
            onPressed: _openExternally,
            icon: const Icon(Icons.open_in_browser_rounded),
          ),
        ],
        bottom: _progress < 1
            ? PreferredSize(
                preferredSize: const Size.fromHeight(2),
                child: LinearProgressIndicator(
                  value: _progress == 0 ? null : _progress,
                ),
              )
            : null,
      ),
      body: Platform.isWindows ? _buildWindowsBody(isDark) : _buildMobileBody(),
    );
  }

  Widget _buildMobileBody() {
    final controller = _mobileController;
    if (controller == null) {
      return const Center(child: CircularProgressIndicator());
    }
    return mobile_webview.WebViewWidget(controller: controller);
  }

  Widget _buildWindowsBody(bool isDark) {
    final error = _windowsError;
    if (error != null) {
      return Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 440),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.public_off_rounded, size: 52),
                const SizedBox(height: 16),
                Text(error, textAlign: TextAlign.center),
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: _openExternally,
                  icon: const Icon(Icons.open_in_browser_rounded),
                  label: const Text('使用系统浏览器打开'),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () => launchUrl(
                    Uri.parse(
                        'https://developer.microsoft.com/microsoft-edge/webview2/'),
                    mode: LaunchMode.externalApplication,
                  ),
                  child: const Text('安装 WebView2 Runtime'),
                ),
              ],
            ),
          ),
        ),
      );
    }
    final controller = _windowsController;
    if (controller == null || !controller.value.isInitialized) {
      return const Center(child: CircularProgressIndicator());
    }
    return windows_webview.Webview(
      controller,
      permissionRequested: (_, __, ___) async =>
          windows_webview.WebviewPermissionDecision.deny,
    );
  }

  @override
  void dispose() {
    for (final subscription in _windowsSubscriptions) {
      subscription.cancel();
    }
    _windowsController?.dispose();
    super.dispose();
  }
}
