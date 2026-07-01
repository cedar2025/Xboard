import 'dart:io';

import 'package:elephant_network/core/services/app_logger.dart';
import 'package:elephant_network/screens/profile/about_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider_platform_interface/path_provider_platform_interface.dart';

import '../test_bootstrap.dart';

void main() {
  late PathProviderPlatform originalPathProvider;

  setUp(() {
    configureTestEnvironment();
    originalPathProvider = PathProviderPlatform.instance;
    PathProviderPlatform.instance = _TestPathProvider(
      Directory.systemTemp.createTempSync('elephant_about_test_').path,
    );
    PackageInfo.setMockInitialValues(
      appName: '大象网络',
      packageName: 'com.elephantroute',
      version: '1.1.0',
      buildNumber: '2',
      buildSignature: '',
    );
  });

  tearDown(() {
    PathProviderPlatform.instance = originalPathProvider;
  });

  testWidgets('关于页显示 Android 标题并且底部只保留检查更新按钮', (tester) async {
    await tester.runAsync(() async {
      await AppLogger.instance.getLogPath();
    });

    await tester.pumpWidget(
      const MaterialApp(
        home: AboutScreen(),
      ),
    );
    await tester.runAsync(() async {
      await Future<void>.delayed(const Duration(seconds: 1));
    });
    await tester.pump();

    expect(find.text('大象网络 Android'), findsOneWidget);
    expect(find.text('ElephantRoute macOS Beta'), findsNothing);
    expect(find.text('检查更新'), findsOneWidget);
    expect(find.text('导出诊断'), findsNothing);
    expect(find.text('恢复系统代理'), findsNothing);
    expect(find.text('复制日志路径'), findsNothing);
  });
}

class _TestPathProvider extends PathProviderPlatform {
  _TestPathProvider(this.path);

  final String path;

  @override
  Future<String?> getApplicationSupportPath() async => path;

  @override
  Future<String?> getApplicationDocumentsPath() async => path;

  @override
  Future<String?> getTemporaryPath() async => path;
}
