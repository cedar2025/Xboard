import 'package:flutter/material.dart';

enum NavigationPage {
  dashboard,
  shop,
  profile,
}

class NavigationProvider with ChangeNotifier {
  NavigationPage _currentPage = NavigationPage.dashboard;

  NavigationPage get currentPage => _currentPage;

  void setPage(NavigationPage page) {
    _currentPage = page;
    notifyListeners();
  }
}
