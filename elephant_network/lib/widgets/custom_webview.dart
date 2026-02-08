import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/app_text_styles.dart';

class CustomWebView extends StatefulWidget {
  final String title;
  final String url;
  final bool injectAuth;
  final bool enableHiding; // 新增控制开关
  final List<String>? hiddenSelectors;

  const CustomWebView({
    super.key,
    required this.title,
    required this.url,
    this.injectAuth = true,
    this.enableHiding = true, // 默认开启
    this.hiddenSelectors,
  });

  @override
  State<CustomWebView> createState() => _CustomWebViewState();
}

class _CustomWebViewState extends State<CustomWebView> {
  late final WebViewController _controller;
  bool _isLoading = true;
  double _progress = 0;
  final _storage = const FlutterSecureStorage();

  @override
  void initState() {
    super.initState();
    _initController();
  }

  Future<void> _initController() async {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white) // 强制白色背景，看是否不再是黑屏
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {
            debugPrint('WebView Loading: $progress%'); // 打印进度
            if (mounted) {
              setState(() {
                _progress = progress / 100;
              });
            }
          },
          onPageStarted: (String url) {
            debugPrint('WebView Page Started: $url'); // 打印开始加载的 URL
            if (mounted) {
              setState(() {
                _isLoading = true;
              });
            }
          },
          onPageFinished: (String url) {
             debugPrint('WebView Page Finished: $url'); // 打印完成加载的 URL
             if (widget.enableHiding) {
               _injectStylesAndScripts();
               _startSmartWait();
             } else {
               if (mounted) {
                 setState(() {
                   _isLoading = false;
                 });
               }
             }
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint('❌ WebView Error Code: ${error.errorCode}');
            debugPrint('❌ WebView Error Desc: ${error.description}');
            debugPrint('❌ WebView Error Type: ${error.errorType}');
          },
        ),
      );

    // 0. 强力清理所有 Webview 缓存数据 (Cookie + Cache + LocalStorage)
    final cookieManager = WebViewCookieManager();
    await cookieManager.clearCookies();
    await _controller.clearCache();
    await _controller.clearLocalStorage();

    // 加载 URL
    try {
      await _controller.loadRequest(Uri.parse(widget.url));
    } catch (e) {
      debugPrint('❌ WebView Load Error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('页面加载失败: $e')),
        );
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _injectStylesAndScripts() async {
    try {
      // 1. 更激进的 CSS 隐藏规则
      final css = """
        /* ===== 隐藏明确的框架导航栏 ===== */
        header.v-app-bar, 
        header.v-toolbar,
        .v-app-bar.v-app-bar--fixed,
        nav.navbar,
        .ant-layout-header,
        .ant-page-header,
        .el-header,
        .el-page-header { 
          display: none !important; 
          opacity: 0 !important; 
          height: 0 !important; 
          visibility: hidden !important;
        }
        
        /* ===== 通用规则：隐藏所有固定在顶部的元素 ===== */
        /* 注意：这会隐藏所有 position:fixed 且在页面顶部的元素 */
        body > header,
        body > div:first-child {
          position: relative !important;
        }
        
        /* 强制隐藏页面顶部 80px 以内的所有固定定位元素 */
        * {
          /* 这个规则会在 JavaScript 中动态应用 */
        }
        
        /* 移除顶部间距 */
        .v-main, body, html, #app, .app { 
          padding-top: 0 !important;
          margin-top: 0 !important;
        }
        
        /* ===== Toast/Message 提示 ===== */
        .ant-message, .ant-message-notice,
        .ant-notification, .ant-notification-notice,
        .el-message, .el-notification,
        .van-toast, .van-notify,
        .v-snackbar__wrapper,
        div.v-overlay--active > .v-snackbar { 
          display: none !important; 
          opacity: 0 !important; 
          visibility: hidden !important; 
          z-index: -99999 !important;
        }
      """;
      
      // 2. 超强 JavaScript 轮询隐藏脚本
      final jsCode = '''
        (function() {
            console.log("🚀 CustomWebView: 启动超强隐藏脚本...");
            
            // A. 立即注入 CSS
            var style = document.createElement('style');
            style.id = 'flutter-webview-hide-style';
            style.innerHTML = `$css`;
            if (document.head) {
                document.head.appendChild(style);
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    document.head.appendChild(style);
                });
            }

            // B. 超强隐藏函数
            function superAggressiveHide() {
                try {
                    // 1. 精简 Header 选择器 - 只针对明确的框架类
                    var headerSelectors = [
                        'header.v-app-bar', 'header.v-toolbar',
                        '.v-app-bar.v-app-bar--fixed',
                        'nav.navbar',
                        '.ant-layout-header', '.ant-page-header',
                        '.el-header', '.el-page-header'
                    ];
                    
                    headerSelectors.forEach(function(selector) {
                        try {
                            var elements = document.querySelectorAll(selector);
                            elements.forEach(function(el) {
                                forceHide(el);
                            });
                        } catch (e) {}
                    });
                    
                    // 1.5 最简单粗暴的方法：隐藏所有顶部固定元素（但要保护重要元素）
                    var allElements = document.body.getElementsByTagName('*');
                    for (var i = 0; i < Math.min(allElements.length, 200); i++) {
                        var el = allElements[i];
                        try {
                            var style = window.getComputedStyle(el);
                            
                            // 只要是 fixed 或 sticky 定位
                            if (style.position === 'fixed' || style.position === 'sticky') {
                                var rect = el.getBoundingClientRect();
                                
                                // 只要在页面顶部 100px 以内
                                if (rect.top <= 100 && rect.top >= -50) {
                                    var height = rect.height || 0;
                                    
                                    // 排除高度为 0 的元素
                                    if (height > 0) {
                                        // ===== 白名单检查 =====
                                        var className = el.className || '';
                                        var textContent = el.textContent || '';
                                        
                                        // 如果包含这些关键词，不隐藏（保护操作列、按钮等）
                                        var protectedKeywords = [
                                            '操作', 'action', 'operation',
                                            '支付', 'pay', 'payment',
                                            '取消', 'cancel',
                                            '订单', 'order',
                                            'table', 'tbody', 'thead', // 保护表格
                                            'btn', 'button' // 保护按钮
                                        ];
                                        
                                        var shouldProtect = false;
                                        for (var k = 0; k < protectedKeywords.length; k++) {
                                            if (className.toLowerCase().indexOf(protectedKeywords[k]) !== -1 ||
                                                textContent.indexOf(protectedKeywords[k]) !== -1) {
                                                shouldProtect = true;
                                                break;
                                            }
                                        }
                                        
                                        if (!shouldProtect) {
                                            console.log('🎯 Hiding top fixed element:', el.tagName, el.className, 'top:', rect.top, 'height:', height);
                                            forceHide(el);
                                        } else {
                                            console.log('🛡️ Protected element:', el.tagName, el.className);
                                        }
                                    }
                                }
                            }
                        } catch (e) {
                            // 忽略错误，继续下一个
                        }
                    }
                    
                    // 专门针对所有 header 和 nav 标签（无论是否 fixed）
                    var headers = document.querySelectorAll('header, nav');
                    headers.forEach(function(el) {
                        var rect = el.getBoundingClientRect();
                        if (rect.top <= 100 && rect.top >= -50 && rect.height > 0) {
                            console.log('🎯 Hiding header/nav:', el.tagName, el.className);
                            forceHide(el);
                        }
                    });

                    // 2. 精简 Toast/Message 选择器 - 只针对明确的框架类
                    var toastSelectors = [
                        '.ant-message', '.ant-notification',
                        '.el-message', '.el-notification',
                        '.van-toast', '.van-notify',
                        '.v-snackbar__wrapper'
                    ];
                    
                    toastSelectors.forEach(function(selector) {
                        try {
                            var elements = document.querySelectorAll(selector);
                            elements.forEach(function(el) {
                                forceHide(el);
                            });
                        } catch (e) {}
                    });

                    // 3. 智能文本检测 - 找到并隐藏包含特定文字的元素
                    if (document.body) {
                        var allElements = document.body.getElementsByTagName('*');
                        for (var i = 0; i < allElements.length; i++) {
                            var el = allElements[i];
                            var text = el.textContent || el.innerText || '';
                            text = text.trim();
                            
                            if (!text || text.length > 100) continue;
                            
                            // ========== 白名单：这些元素不应被隐藏 ==========
                            var whitelistKeywords = [
                                '创建工单', '新建工单', '新的工单', 'Create', 'New Ticket',
                                '提交', 'Submit', '发送', 'Send',
                                '取消', 'Cancel', '确定', 'OK', 'Confirm',
                                '关闭', 'Close', '保存', 'Save',
                                '编辑', 'Edit', '删除', 'Delete',
                                '查看', 'View', '详情', 'Details'
                            ];
                            
                            var isWhitelisted = false;
                            for (var w = 0; w < whitelistKeywords.length; w++) {
                                if (text.indexOf(whitelistKeywords[w]) !== -1) {
                                    isWhitelisted = true;
                                    break;
                                }
                            }
                            
                            // 如果在白名单中，跳过
                            if (isWhitelisted) continue;
                            
                            // 检测头部关键词
                            var headerKeywords = [
                                '配置订阅', '我的订阅', 'Dashboard', '仪表盘', 
                                'My Subscription', '订阅管理', '控制面板'
                            ];
                            
                            // 检测 Toast 关键词（更精确的匹配）
                            var toastKeywords = [
                                '登录成功', 'Login Success', 'Login Successful',
                                '欢迎回来', 'Welcome back', 'Welcome Back',
                                '操作成功', 'Operation Success',
                                '已登录', 'Logged in', 'Signed in'
                            ];
                            
                            var style = window.getComputedStyle(el);
                            var isFixed = style.position === 'fixed';
                            var isAbsolute = style.position === 'absolute';
                            var isSticky = style.position === 'sticky';
                            var zIndex = parseInt(style.zIndex) || 0;
                            
                            // 如果是头部关键词且元素是 fixed/sticky
                            for (var k = 0; k < headerKeywords.length; k++) {
                                if (text.indexOf(headerKeywords[k]) !== -1) {
                                    if (isFixed || isSticky || el.tagName === 'HEADER') {
                                        forceHide(el);
                                        break;
                                    }
                                }
                            }
                            
                            // 如果是 Toast 关键词且元素是 fixed/absolute 且 z-index 高
                            // 仅针对简短文本（Toast 通常很短）
                            if (text.length < 30) {
                                for (var j = 0; j < toastKeywords.length; j++) {
                                    if (text.indexOf(toastKeywords[j]) !== -1) {
                                        // 更严格的条件：必须是高层级浮动元素
                                        if ((isFixed || isAbsolute) && zIndex > 100) {
                                            forceHide(el);
                                            // 也隐藏父元素
                                            var parent = el.parentElement;
                                            var attempts = 0;
                                            while (parent && attempts < 5) {
                                                var parentStyle = window.getComputedStyle(parent);
                                                var parentZIndex = parseInt(parentStyle.zIndex) || 0;
                                                // 父元素也必须满足条件才隐藏
                                                if ((parentStyle.position === 'fixed' || parentStyle.position === 'absolute') && parentZIndex > 100) {
                                                    forceHide(parent);
                                                }
                                                parent = parent.parentElement;
                                                attempts++;
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (e) {
                    console.error('Hide error:', e);
                }
            }

            // 强制隐藏元素
            function forceHide(element) {
                if (!element || !element.style) return;
                
                element.style.cssText = 'display: none !important; opacity: 0 !important; visibility: hidden !important; height: 0 !important; overflow: hidden !important; position: absolute !important; top: -99999px !important; left: -99999px !important; z-index: -99999 !important; pointer-events: none !important;';
                
                // 添加属性标记
                element.setAttribute('data-flutter-hidden', 'true');
            }

            // C. 启动超高频轮询（每 50ms 一次，前 5 秒）
            var count = 0;
            var maxCount = 100; // 5秒 = 100 * 50ms
            
            var fastInterval = setInterval(function() {
                superAggressiveHide();
                count++;
                if (count >= maxCount) {
                    clearInterval(fastInterval);
                    console.log("✅ 快速轮询结束，切换到慢速轮询");
                    // 切换到慢速轮询
                    setInterval(superAggressiveHide, 200);
                }
            }, 50);
            
            // 立即执行一次
            superAggressiveHide();
            
            // 监听 DOM 变化
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function(mutations) {
                    superAggressiveHide();
                });
                
                if (document.body) {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                } else {
                    document.addEventListener('DOMContentLoaded', function() {
                        observer.observe(document.body, {
                            childList: true,
                            subtree: true
                        });
                    });
                }
            }
            
            console.log("✅ CustomWebView: 超强隐藏脚本已激活");
        })();
      ''';

      await _controller.runJavaScript(jsCode);
      debugPrint('✅ 注入超强隐藏脚本成功');

      // 3. 注入 Auth Token（双重保险，确保 token 存在）
      if (widget.injectAuth) {
        final token = await _storage.read(key: 'auth_token');
        if (token != null) {
          final injectTokenJs = '''
            localStorage.setItem('token', '$token');
            localStorage.setItem('auth_token', '$token');
            
            // 验证 token 是否真的被设置
            var storedToken = localStorage.getItem('token');
            console.log('✅ Token injected, verification:', storedToken ? storedToken.substring(0, 10) + '...' : 'FAILED');
          ''';
          await _controller.runJavaScript(injectTokenJs);
          debugPrint('✅ [onPageFinished] Token 二次确认注入成功');
        } else {
          debugPrint('⚠️ [onPageFinished] Token 为空，无法注入');
        }
      }
    } catch (e) {
      debugPrint('❌ 注入脚本失败: $e');
    }
  }


  /// 智能等待：轮询直到 URL 跳转到非登录页，或超时
  /// 同时充当“原生守护进程”，持续注入防御脚本，防止页面跳转后脚本失效
  void _startSmartWait() {
    int attempts = 0;
    
    debugPrint('DEBUG: SmartWait & Guard Started...');
    
    Future.doWhile(() async {
      await Future.delayed(const Duration(milliseconds: 300));
      attempts++;
      
      if (!mounted) return false;
      
      // 1. 持续注入防御脚本 (Native Polling Injection)
      // 无论页面是否刷新，这里都会强制补充“弹窗/头部消除术”
      await _injectStylesAndScripts();
      
      // 2. 检查 URL 状态
      final currentUrl = await _controller.currentUrl();
      // debugPrint('DEBUG: Check [$attempts] Current URL: $currentUrl'); // 减少日志刷屏
      
      bool readyToShow = false;
      if (currentUrl != null) {
        // 排除 QuickLogin 跳转链接
        bool isTransitionUrl = currentUrl.contains('/s/') || currentUrl.contains('auth_data=');
        
        // 如果不在跳转页，且页面应该已经加载了一些内容
        if (!isTransitionUrl) {
           readyToShow = true;
        }
      }
      
      // 5秒后强制显示 (20 * 300ms = 6s)
      if (readyToShow || attempts >= 20) { 
        if (mounted && _isLoading) {
           debugPrint('DEBUG: SmartWait Finished. Showing Webview.');
           // 延迟一丢丢，确保 CSS 刚注入生效
           await Future.delayed(const Duration(milliseconds: 200));
           if (mounted) {
             setState(() {
              _isLoading = false;
            });
           }
        }
        // 注意：这里我们返回 false 停止了 SmartWait。
        // 如果用户在 Webview 内部继续点击跳转，可能需要监听 onUrlChange 来重启保护。
        // 但目前主要解决的是“刚进去”的那一下。
        return false; 
      }
      
      return true; // 继续循环
    });
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppColors.darkBackground : AppColors.lightBackground,
      appBar: AppBar(
        title: Text(
          widget.title,
          style: AppTextStyles.titleMedium.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
        ),
        backgroundColor: isDark ? AppColors.darkCard : AppColors.lightCard,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: Icon(
            Icons.close,
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
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
      body: Stack(
        children: [
          // Webview
          WebViewWidget(controller: _controller),
          
          // 加载指示器
          if (_isLoading)
            Container(
              color: isDark ? AppColors.darkBackground : AppColors.lightBackground,
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(
                      color: isDark ? AppColors.primaryLight : AppColors.primary,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      '正在安全跳转...',
                      style: AppTextStyles.bodyMedium.copyWith(
                        color: isDark ? AppColors.darkTextSecondary : AppColors.lightTextSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
