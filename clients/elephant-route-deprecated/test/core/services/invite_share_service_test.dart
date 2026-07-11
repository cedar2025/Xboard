import 'package:elephant_network/core/services/invite_share_service.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_bootstrap.dart';

void main() {
  configureTestEnvironment();

  test('builds an encoded invite URL from the active base URL', () {
    expect(
      InviteShareService.buildInviteUrl(
        'https://www.example.com///',
        'CODE +/8',
      ),
      'https://www.example.com/app#/register?code=CODE+%2B%2F8',
    );
  });

  test('formats integer and decimal commission rates without trailing zeros',
      () {
    expect(InviteShareService.formatCommissionRate(10), '10');
    expect(InviteShareService.formatCommissionRate(12.5), '12.5');
    expect(InviteShareService.formatCommissionRate(null), '');
  });

  test('defines the approved Android package mappings', () {
    expect(InviteSharePlatform.telegram.androidPackages,
        ['org.telegram.messenger']);
    expect(InviteSharePlatform.whatsapp.androidPackages,
        ['com.whatsapp', 'com.whatsapp.w4b']);
    expect(InviteSharePlatform.x.androidPackages, ['com.twitter.android']);
    expect(
        InviteSharePlatform.facebook.androidPackages, ['com.facebook.katana']);
    expect(InviteSharePlatform.line.androidPackages, ['jp.naver.line.android']);
  });

  test('builds approved Windows web share URLs', () {
    final inviteUrl = 'https://example.com/app#/register?code=A+B';
    final telegram = InviteShareService.buildWebShareUri(
      InviteSharePlatform.telegram,
      inviteUrl,
    );
    final facebook = InviteShareService.buildWebShareUri(
      InviteSharePlatform.facebook,
      inviteUrl,
    );

    expect(telegram.host, 't.me');
    expect(telegram.queryParameters['url'], inviteUrl);
    expect(facebook.host, 'www.facebook.com');
    expect(facebook.queryParameters['u'], inviteUrl);
  });

  test('sends text and package fallbacks over the Android channel', () async {
    const channel = MethodChannel('test.elephant.network/share');
    MethodCall? receivedCall;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      receivedCall = call;
      return null;
    });

    await InviteShareService(channel: channel).shareText(
      platform: InviteSharePlatform.whatsapp,
      text: '邀请内容',
    );

    expect(receivedCall?.method, 'shareText');
    expect(receivedCall?.arguments, {
      'packageNames': ['com.whatsapp', 'com.whatsapp.w4b'],
      'text': '邀请内容',
    });
  });
}
