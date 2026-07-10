import 'package:elephant_network/widgets/dashboard_brand_header.dart';
import 'package:elephant_network/widgets/invite_share_sheet.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_bootstrap.dart';

void main() {
  configureTestEnvironment();

  testWidgets('places the dashboard logo to the left of the brand name',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: SizedBox(
            width: 360,
            child: DashboardBrandHeader(
              appName: '大象网络',
              slogan: '安全连接',
              isDark: false,
            ),
          ),
        ),
      ),
    );

    final logo = find.byKey(const ValueKey('dashboard-brand-logo'));
    final brandName = find.byKey(const ValueKey('dashboard-brand-name'));
    expect(logo, findsOneWidget);
    expect(brandName, findsOneWidget);
    expect(
        tester.getTopLeft(logo).dx, lessThan(tester.getTopLeft(brandName).dx));
  });

  testWidgets('shows only the six approved invite share actions',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: InviteShareSheet(
            inviteUrl: 'https://example.com/app#/register?code=ABC',
            onCopySuccess: () {},
            onShareError: (_) {},
          ),
        ),
      ),
    );
    await tester.pump();

    for (final label in [
      '复制链接',
      'Telegram',
      'WhatsApp',
      'X',
      'Facebook',
      'LINE',
    ]) {
      expect(find.byKey(ValueKey('invite-share-$label')), findsOneWidget);
    }
    expect(find.text('更多'), findsNothing);
    expect(find.text('Instagram'), findsNothing);
    expect(find.text('微信'), findsNothing);
    expect(find.text('QQ'), findsNothing);
  });
}
