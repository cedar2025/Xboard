#include "flutter_window.h"

#include <optional>

#include "flutter/generated_plugin_registrant.h"
#include <flutter/method_channel.h>
#include <flutter/standard_method_codec.h>
#include <windows.h>
#include <wininet.h>
#include <string>

#pragma comment(lib, "wininet.lib")

FlutterWindow::FlutterWindow(const flutter::DartProject& project)
    : project_(project) {}

FlutterWindow::~FlutterWindow() {}

bool FlutterWindow::OnCreate() {
  if (!Win32Window::OnCreate()) {
    return false;
  }

  RECT frame = GetClientArea();

  // The size here must match the window dimensions to avoid unnecessary surface
  // creation / destruction in the startup path.
  flutter_controller_ = std::make_unique<flutter::FlutterViewController>(
      frame.right - frame.left, frame.bottom - frame.top, project_);
  // Ensure that basic setup of the controller was successful.
  if (!flutter_controller_->engine() || !flutter_controller_->view()) {
    return false;
  }
  RegisterPlugins(flutter_controller_->engine());
  SetChildContent(flutter_controller_->view()->GetNativeWindow());

  // Setup Proxy MethodChannel
  flutter::MethodChannel<flutter::EncodableValue> channel(
      flutter_controller_->engine()->messenger(), "com.elephant.network/proxy",
      &flutter::StandardMethodCodec::GetInstance());

  channel.SetMethodCallHandler(
      [](const flutter::MethodCall<flutter::EncodableValue>& call,
         std::unique_ptr<flutter::MethodResult<flutter::EncodableValue>> result) {
        if (call.method_name() == "enableProxy") {
          const auto* arguments = std::get_if<flutter::EncodableMap>(call.arguments());
          int port = 2334;
          if (arguments) {
              auto port_it = arguments->find(flutter::EncodableValue("port"));
              if (port_it != arguments->end() && std::holds_alternative<int>(port_it->second)) {
                  port = std::get<int>(port_it->second);
              }
          }
          
          std::wstring proxy_str = L"127.0.0.1:" + std::to_wstring(port);
          INTERNET_PER_CONN_OPTION_LIST list;
          INTERNET_PER_CONN_OPTION options[3];
          unsigned long list_size = sizeof(list);
          
          options[0].dwOption = INTERNET_PER_CONN_FLAGS;
          options[0].Value.dwValue = PROXY_TYPE_PROXY | PROXY_TYPE_DIRECT;
          
          options[1].dwOption = INTERNET_PER_CONN_PROXY_SERVER;
          options[1].Value.pszValue = (LPWSTR)proxy_str.c_str();
          
          options[2].dwOption = INTERNET_PER_CONN_PROXY_BYPASS;
          options[2].Value.pszValue = (LPWSTR)L"localhost;127.*;10.*;172.16.*;172.17.*;172.18.*;172.19.*;172.20.*;172.21.*;172.22.*;172.23.*;172.24.*;172.25.*;172.26.*;172.27.*;172.28.*;172.29.*;172.30.*;172.31.*;192.168.*;*.local";
          
          list.dwSize = sizeof(list);
          list.pszConnection = nullptr;
          list.dwOptionCount = 3;
          list.dwOptionError = 0;
          list.pOptions = options;
          
          InternetSetOption(nullptr, INTERNET_OPTION_PER_CONNECTION_OPTION, &list, list_size);
          InternetSetOption(nullptr, INTERNET_OPTION_SETTINGS_CHANGED, nullptr, 0);
          InternetSetOption(nullptr, INTERNET_OPTION_REFRESH, nullptr, 0);
          
          result->Success(flutter::EncodableValue(true));
        } else if (call.method_name() == "disableProxy") {
          INTERNET_PER_CONN_OPTION_LIST list;
          INTERNET_PER_CONN_OPTION options[1];
          unsigned long list_size = sizeof(list);
          
          options[0].dwOption = INTERNET_PER_CONN_FLAGS;
          options[0].Value.dwValue = PROXY_TYPE_DIRECT;
          
          list.dwSize = sizeof(list);
          list.pszConnection = nullptr;
          list.dwOptionCount = 1;
          list.dwOptionError = 0;
          list.pOptions = options;
          
          InternetSetOption(nullptr, INTERNET_OPTION_PER_CONNECTION_OPTION, &list, list_size);
          InternetSetOption(nullptr, INTERNET_OPTION_SETTINGS_CHANGED, nullptr, 0);
          InternetSetOption(nullptr, INTERNET_OPTION_REFRESH, nullptr, 0);
          
          result->Success(flutter::EncodableValue(true));
        } else {
          result->NotImplemented();
        }
      });

  flutter_controller_->engine()->SetNextFrameCallback([&]() {
    this->Show();
  });

  // Flutter can complete the first frame before the "show window" callback is
  // registered. The following call ensures a frame is pending to ensure the
  // window is shown. It is a no-op if the first frame hasn't completed yet.
  flutter_controller_->ForceRedraw();

  return true;
}

void FlutterWindow::OnDestroy() {
  if (flutter_controller_) {
    flutter_controller_ = nullptr;
  }

  Win32Window::OnDestroy();
}

LRESULT
FlutterWindow::MessageHandler(HWND hwnd, UINT const message,
                              WPARAM const wparam,
                              LPARAM const lparam) noexcept {
  // Give Flutter, including plugins, an opportunity to handle window messages.
  if (flutter_controller_) {
    std::optional<LRESULT> result =
        flutter_controller_->HandleTopLevelWindowProc(hwnd, message, wparam,
                                                      lparam);
    if (result) {
      return *result;
    }
  }

  switch (message) {
    case WM_FONTCHANGE:
      flutter_controller_->engine()->ReloadSystemFonts();
      break;
  }

  return Win32Window::MessageHandler(hwnd, message, wparam, lparam);
}
