#ifndef RUNNER_WINDOWS_SERVICE_BRIDGE_H_
#define RUNNER_WINDOWS_SERVICE_BRIDGE_H_

#include <flutter/binary_messenger.h>
#include <flutter/encodable_value.h>
#include <flutter/event_channel.h>
#include <flutter/event_sink.h>
#include <flutter/event_stream_handler_functions.h>
#include <flutter/method_channel.h>

#include <windows.h>

#include <atomic>
#include <memory>
#include <mutex>
#include <string>
#include <thread>

class WindowsServiceBridge {
 public:
  static constexpr UINT kEventMessage = WM_APP + 0x314;

  WindowsServiceBridge(flutter::BinaryMessenger* messenger, HWND window);
  ~WindowsServiceBridge();

  WindowsServiceBridge(const WindowsServiceBridge&) = delete;
  WindowsServiceBridge& operator=(const WindowsServiceBridge&) = delete;

  void DrainServiceEvent();

 private:
  using EncodableValue = flutter::EncodableValue;

  void HandleMethod(
      const flutter::MethodCall<EncodableValue>& call,
      std::unique_ptr<flutter::MethodResult<EncodableValue>> result);
  void StartPolling(
      std::unique_ptr<flutter::EventSink<EncodableValue>>&& event_sink);
  void StopPolling();
  void PollLoop();

  std::unique_ptr<flutter::MethodChannel<EncodableValue>> method_channel_;
  std::unique_ptr<flutter::EventChannel<EncodableValue>> event_channel_;
  std::unique_ptr<flutter::EventSink<EncodableValue>> event_sink_;
  HWND window_;
  std::atomic<bool> polling_{false};
  std::thread poll_thread_;
  std::mutex event_mutex_;
  std::string pending_event_;
};

#endif  // RUNNER_WINDOWS_SERVICE_BRIDGE_H_
