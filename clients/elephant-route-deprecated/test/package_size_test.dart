import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('app uses system fonts and does not ship custom font assets', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();

    expect(pubspec,
        isNot(contains(RegExp(r'^\s*cupertino_icons\s*:', multiLine: true))));
    expect(pubspec,
        isNot(contains(RegExp(r'^\s*google_fonts\s*:', multiLine: true))));
    expect(pubspec, isNot(contains(RegExp(r'^\s*fonts\s*:', multiLine: true))));

    final fontDirectory = Directory('assets/fonts');
    final packagedFontFiles = fontDirectory.existsSync()
        ? fontDirectory
            .listSync(recursive: true)
            .whereType<File>()
            .where((file) =>
                RegExp(r'\.(ttf|otf|woff2?)$', caseSensitive: false)
                    .hasMatch(file.path))
            .map((file) => file.path)
            .toList()
        : <String>[];

    expect(packagedFontFiles, isEmpty);
  });
}
