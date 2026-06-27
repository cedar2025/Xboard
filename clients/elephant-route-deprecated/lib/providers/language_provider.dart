import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../utils/translations.dart';

class LanguageProvider extends ChangeNotifier {
  Locale _locale = const Locale('zh');

  Locale get locale => _locale;

  String get currentLanguage => _locale.languageCode == 'zh' ? '中文' : 'English';

  void setLanguage(String languageCode) {
    _locale = Locale(languageCode);
    notifyListeners();
  }

  String translate(String key) {
    final lang = _locale.languageCode;
    return Translations.map[lang]?[key] ?? key;
  }
}

extension TranslationExtension on BuildContext {
  String tr(String key) => read<LanguageProvider>().translate(key);
}
