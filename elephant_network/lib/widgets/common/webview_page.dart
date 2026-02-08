import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:provider/provider.dart';
import '../../core/api/dio_client.dart';
import 'dart:async';

class WebViewPage extends StatefulWidget {
  final String initialUrl;
  final String title;
  final bool hideElements; // 是否隐藏面板的 Header/Footer

  const WebViewPage({
    super.key,
    required this.initialUrl,
    required this.title,
    this.hideElements = true,
  });

  @override
  State<WebViewPage> createState() => _WebViewPageState();
}

class _WebViewPageState extends State<WebViewPage> {
  late final WebViewController _controller;
  bool _isLoading = true;
  double _progress = 0;

  @override
  void initState() {
    super.initState();
    
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFF0A0E27))
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {
            setState(() {
              _progress = progress / 100;
            });
          },
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
            });
          },
          onPageFinished: (String url) {
            setState(() {
              _isLoading = false;
            });
            if (widget.hideElements) {
              _injectCSS();
            }
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint('WebView Error: ${error.description}');
          },
          onNavigationRequest: (NavigationRequest request) {
            // 这里可以处理特定的跳转逻辑(例如支付成功的重定向)
            if (request.url.contains('payment/success')) {
              // 模拟支付成功后的行为
              Navigator.pop(context, true);
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      );

    _loadPage();
  }

  Future<void> _loadPage() async {
    final storage = context.read<DioClient>().storage;
    final token = await storage.read(key: 'auth_token');

    // 注入 Token 到 Cookie 或 Header
    // 注意: webview_flutter 为 Header 传参提供了 loadRequest 方法
    final headers = <String, String>{};
    if (token != null) {
      headers['Authorization'] = 'Bearer $token';
    }

    await _controller.loadRequest(
      Uri.parse(widget.initialUrl),
      headers: headers,
    );
  }

  /// 注入 CSS 隐藏面板多余元素
  void _injectCSS() {
    const js = """
      (function() {
        var style = document.createElement('style');
        style.type = 'text/css';
        style.innerHTML = `
          /* 隐藏常见的面板导航和页脚 */
          header, footer, .navbar, .sidebar, .main-header, .main-footer { display: none !important; }
          .content-wrapper { margin-left: 0 !important; padding-top: 0 !important; }
          /* 适配深色模式背景 */
          body { background-color: #0A0E27 !important; color: #ffffff !important; }
        `;
        document.getElementsByTagName('head')[0].appendChild(style);
      })();
    """;
    _controller.runJavaScript(js);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0E27),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E2A4A),
        elevation: 0,
        title: Text(widget.title, style: const TextStyle(color: Colors.white, fontSize: 16)),
        leading: IconButton(
          icon: const Icon(Icons.close, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white),
            onPressed: () => _controller.reload(),
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(2),
          child: _progress < 1.0
              ? LinearProgressIndicator(
                  value: _progress,
                  backgroundColor: Colors.transparent,
                  valueColor: const AlwaysStoppedAnimation<Color>(Colors.greenAccent),
                  minHeight: 2,
                )
              : const SizedBox.shrink(),
        ),
      ),
      body: WebViewWidget(controller: _controller),
    );
  }
}
