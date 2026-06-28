import 'dart:convert';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Generate Logo Base64', (WidgetTester tester) async {
    tester.view.physicalSize = const Size(1024, 1024);
    tester.view.devicePixelRatio = 1.0;

    const svgString =
        '''<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" fill="none">
      <path d="M 78 28 L 38 28 C 28 28 24 32 24 42 L 24 58 C 24 68 28 72 38 72 L 78 72" stroke-width="14" stroke-linecap="round" stroke-linejoin="round" stroke="#0f172a"/>
      <path d="M 40 50 L 78 50" stroke-width="14" stroke-linecap="round" stroke="#10b981"/>
      <circle cx="78" cy="50" r="7" fill="#10b981" stroke="none" />
    </svg>''';

    await tester.pumpWidget(
      Directionality(
        textDirection: TextDirection.ltr,
        child: RepaintBoundary(
          key: const Key('icon'),
          child: Container(
            width: 1024,
            height: 1024,
            color: Colors.transparent,
            alignment: Alignment.center,
            padding: const EdgeInsets.all(200),
            child: SvgPicture.string(
              svgString,
              width: 624,
              height: 624,
            ),
          ),
        ),
      ),
    );
    await tester.pump(const Duration(milliseconds: 100));

    final boundary = tester.renderObject(find.byKey(const Key('icon')))
        as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 1.0);
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    final pngBytes = byteData!.buffer.asUint8List();

    final base64String = base64Encode(pngBytes);
    debugPrint('BASE64_START');
    debugPrint(base64String);
    debugPrint('BASE64_END');
  });
}
