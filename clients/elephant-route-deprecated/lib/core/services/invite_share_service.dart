import 'dart:io';

import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

enum InviteSharePlatform {
  telegram(
    label: 'Telegram',
    assetPath: 'assets/images/share/telegram.svg',
    androidPackages: ['org.telegram.messenger'],
  ),
  whatsapp(
    label: 'WhatsApp',
    assetPath: 'assets/images/share/whatsapp.svg',
    androidPackages: ['com.whatsapp', 'com.whatsapp.w4b'],
  ),
  x(
    label: 'X',
    assetPath: 'assets/images/share/x.svg',
    androidPackages: ['com.twitter.android'],
  ),
  facebook(
    label: 'Facebook',
    assetPath: 'assets/images/share/facebook.svg',
    androidPackages: ['com.facebook.katana'],
  ),
  line(
    label: 'LINE',
    assetPath: 'assets/images/share/line.svg',
    androidPackages: ['jp.naver.line.android'],
  );

  const InviteSharePlatform({
    required this.label,
    required this.assetPath,
    required this.androidPackages,
  });

  final String label;
  final String assetPath;
  final List<String> androidPackages;
}

class InviteShareService {
  InviteShareService({
    MethodChannel channel = const MethodChannel('com.elephant.network/share'),
  }) : _channel = channel;

  final MethodChannel _channel;

  static String buildInviteUrl(String baseUrl, String inviteCode) {
    final normalizedBaseUrl = baseUrl.replaceFirst(RegExp(r'/+$'), '');
    final encodedCode = Uri.encodeQueryComponent(inviteCode);
    return '$normalizedBaseUrl/app#/register?code=$encodedCode';
  }

  static String buildShareText(String inviteUrl) {
    return '邀请你加入大象网络，注册并使用服务：$inviteUrl';
  }

  static String formatCommissionRate(num? rate) {
    if (rate == null) return '';
    if (rate == rate.roundToDouble()) return rate.toInt().toString();
    return rate.toString().replaceFirst(RegExp(r'0+$'), '').replaceFirst(
          RegExp(r'\.$'),
          '',
        );
  }

  Future<void> shareText({
    required InviteSharePlatform platform,
    required String text,
  }) async {
    await _channel.invokeMethod<void>('shareText', {
      'packageNames': platform.androidPackages,
      'text': text,
    });
  }

  Future<void> shareInvite({
    required InviteSharePlatform platform,
    required String inviteUrl,
  }) async {
    if (Platform.isWindows) {
      final opened = await launchUrl(
        buildWebShareUri(platform, inviteUrl),
        mode: LaunchMode.externalApplication,
      );
      if (!opened) {
        throw PlatformException(
          code: 'SHARE_UNAVAILABLE',
          message: 'Unable to open ${platform.label}',
        );
      }
      return;
    }
    await shareText(platform: platform, text: buildShareText(inviteUrl));
  }

  static Uri buildWebShareUri(
    InviteSharePlatform platform,
    String inviteUrl,
  ) {
    final text = buildShareText(inviteUrl);
    switch (platform) {
      case InviteSharePlatform.telegram:
        return Uri.https('t.me', '/share/url', {
          'url': inviteUrl,
          'text': text,
        });
      case InviteSharePlatform.whatsapp:
        return Uri.https('wa.me', '/', {'text': text});
      case InviteSharePlatform.x:
        return Uri.https('twitter.com', '/intent/tweet', {'text': text});
      case InviteSharePlatform.facebook:
        return Uri.https(
          'www.facebook.com',
          '/sharer/sharer.php',
          {'u': inviteUrl},
        );
      case InviteSharePlatform.line:
        return Uri.https(
          'social-plugins.line.me',
          '/lineit/share',
          {'url': inviteUrl},
        );
    }
  }
}
