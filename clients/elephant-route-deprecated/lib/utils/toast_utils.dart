import 'package:flutter/material.dart';
import '../widgets/custom_toast_widget.dart';

class ToastUtils {
  static OverlayEntry? _currentOverlay;
  static bool _isShowing = false;

  static void show(BuildContext context, String message) {
    if (_isShowing) {
      _currentOverlay?.remove();
      _isShowing = false;
    }

    _currentOverlay = OverlayEntry(
      builder: (context) => CustomToastWidget(
        message: message,
        onDismiss: () {
          _currentOverlay?.remove();
          _currentOverlay = null;
          _isShowing = false;
        },
      ),
    );

    Overlay.of(context).insert(_currentOverlay!);
    _isShowing = true;

    // 2秒后自动消失
    Future.delayed(const Duration(seconds: 2), () {
      if (_isShowing && _currentOverlay != null) {
        _currentOverlay?.remove();
        _currentOverlay = null;
        _isShowing = false;
      }
    });
  }
}
