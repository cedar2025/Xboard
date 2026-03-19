import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
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
  late final WebViewController _controller = WebViewController();
  bool _isLoading = false;
  double _progress = 0;

  @override
  void initState() {
    super.initState();
    _setupController();
  }

  Future<void> _setupController() async {
    debugPrint('CustomWebView: _setupController started for ${widget.url}');

    try {
      _controller.setJavaScriptMode(JavaScriptMode.unrestricted);

      // macOS does not support setBackgroundColor (throws UnimplementedError: opaque is not implemented)
      if (!PlatformUtils.isMacOS) {
        _controller.setBackgroundColor(Colors.white);
      }

      _controller.setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {
            if (mounted) {
              setState(() {
                _progress = progress / 100;
              });
            }
          },
          onPageStarted: (String url) {
            debugPrint('WebView Page Started: $url');
          },
          onPageFinished: (String url) {
            debugPrint('WebView Page Finished: $url');
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
            }
            // Inject CSS to hide sidebar and header
            _injectHideLayoutCSS();
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint(
                'WebView Error: ${error.description} (Code: ${error.errorCode})');
          },
        ),
      );
    } catch (e) {
      debugPrint('Controller config error: $e');
    }

    // Fix URL path: Xboard SPA is deployed under /app, but backend app_url omits this
    var finalUrl = widget.url;
    if (finalUrl.contains('/#/') && !finalUrl.contains('/app#/')) {
      finalUrl = finalUrl.replaceFirst('/#/', '/app#/');
      debugPrint('WebView URL fix: $finalUrl');
    }

    try {
      debugPrint('WebView loadRequest: $finalUrl');
      await _controller.loadRequest(Uri.parse(finalUrl));
    } catch (e) {
      debugPrint('WebView loadRequest error: $e');
    }
  }

  /// Inject CSS to hide the sidebar and header in the Xboard admin panel
  void _injectHideLayoutCSS() {
    const js = """
      (function() {
        if (document.getElementById('flutter-hide-layout-css')) return;
        var style = document.createElement('style');
        style.id = 'flutter-hide-layout-css';
        style.innerHTML = `
          .n-layout-sider,
          .sidebar,
          aside,
          nav.n-menu,
          .n-layout > .n-layout-sider-scroll-container {
            display: none !important;
          }

          header,
          .n-layout-header,
          .main-header,
          .navbar,
          .n-layout > div:first-child > header {
            display: none !important;
          }

          .n-layout,
          .n-layout-content,
          .content-wrapper,
          .n-layout .n-layout-scroll-container,
          main,
          .v-main {
            margin-left: 0 !important;
            padding-top: 0 !important;
            padding-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
          }

          #ripple-canvas {
            display: none !important;
          }
        `;
        (document.head || document.documentElement).appendChild(style);
      })();
    """;
    _controller.runJavaScript(js);
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor:
          isDark ? AppColors.darkBackground : AppColors.lightBackground,
      appBar: AppBar(
        title: Text(
          widget.title,
          style: AppTextStyles.titleMedium.copyWith(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
        ),
        backgroundColor: isDark ? AppColors.darkCard : AppColors.lightCard,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: Icon(
            Icons.close,
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          onPressed: () => Navigator.of(context).pop(),
        ),
        bottom: _progress < 1.0
            ? PreferredSize(
                preferredSize: const Size.fromHeight(2),
                child: LinearProgressIndicator(
                  value: _progress,
                  backgroundColor: Colors.transparent,
                  valueColor: AlwaysStoppedAnimation<Color>(
                    isDark ? AppColors.primaryLight : AppColors.primary,
                  ),
                ),
              )
            : null,
      ),
      body: WebViewWidget(controller: _controller),
    );
  }
}
