#include "windows_service_bridge.h"

#include <wincrypt.h>

#include <chrono>
#include <optional>
#include <utility>
#include <vector>

#include "flutter/standard_method_codec.h"
#include "windows_protocol.h"

namespace {

using EncodableMap = flutter::EncodableMap;
using EncodableValue = flutter::EncodableValue;

const EncodableMap* Arguments(const flutter::MethodCall<EncodableValue>& call) {
  return call.arguments() ? std::get_if<EncodableMap>(call.arguments()) : nullptr;
}

std::optional<std::string> StringArgument(
    const EncodableMap* arguments, const char* key) {
  if (!arguments) return std::nullopt;
  const auto iterator = arguments->find(EncodableValue(key));
  if (iterator == arguments->end()) return std::nullopt;
  if (const auto* value = std::get_if<std::string>(&iterator->second)) return *value;
  return std::nullopt;
}

EncodableValue ResponseValue(const std::string& json) {
  EncodableMap map;
  for (const auto* key : {"status", "mode", "core_version", "error_code", "error_message"}) {
    if (const auto value = elephant::JsonString(json, key)) {
      map[EncodableValue(key)] = EncodableValue(*value);
    }
  }
  for (const auto* key : {"up_speed", "down_speed", "total_up", "total_down", "core_pid", "delay"}) {
    if (const auto value = elephant::JsonInteger(json, key)) {
      map[EncodableValue(key)] = EncodableValue(*value);
    }
  }
  return EncodableValue(map);
}

std::string Base64Encode(const BYTE* data, DWORD length) {
  DWORD output_length = 0;
  if (!CryptBinaryToStringA(data, length,
                            CRYPT_STRING_BASE64 | CRYPT_STRING_NOCRLF,
                            nullptr, &output_length)) {
    return {};
  }
  std::string output(output_length, '\0');
  if (!CryptBinaryToStringA(data, length,
                            CRYPT_STRING_BASE64 | CRYPT_STRING_NOCRLF,
                            output.data(), &output_length)) {
    return {};
  }
  if (!output.empty() && output.back() == '\0') output.pop_back();
  return output;
}

std::vector<BYTE> Base64Decode(const std::string& value) {
  DWORD length = 0;
  if (!CryptStringToBinaryA(value.data(), static_cast<DWORD>(value.size()),
                            CRYPT_STRING_BASE64, nullptr, &length, nullptr,
                            nullptr)) {
    return {};
  }
  std::vector<BYTE> output(length);
  if (!CryptStringToBinaryA(value.data(), static_cast<DWORD>(value.size()),
                            CRYPT_STRING_BASE64, output.data(), &length,
                            nullptr, nullptr)) {
    return {};
  }
  output.resize(length);
  return output;
}

std::optional<std::string> ProtectSecret(const std::string& key,
                                         const std::string& value) {
  DATA_BLOB input{static_cast<DWORD>(value.size()),
                  reinterpret_cast<BYTE*>(const_cast<char*>(value.data()))};
  DATA_BLOB entropy{static_cast<DWORD>(key.size()),
                    reinterpret_cast<BYTE*>(const_cast<char*>(key.data()))};
  DATA_BLOB output{};
  if (!CryptProtectData(&input, L"Elephant Network", &entropy, nullptr,
                        nullptr, CRYPTPROTECT_UI_FORBIDDEN, &output)) {
    return std::nullopt;
  }
  const auto encoded = Base64Encode(output.pbData, output.cbData);
  LocalFree(output.pbData);
  return encoded.empty() ? std::nullopt : std::optional<std::string>(encoded);
}

std::optional<std::string> UnprotectSecret(const std::string& key,
                                           const std::string& value) {
  auto encrypted = Base64Decode(value);
  if (encrypted.empty()) return std::nullopt;
  DATA_BLOB input{static_cast<DWORD>(encrypted.size()), encrypted.data()};
  DATA_BLOB entropy{static_cast<DWORD>(key.size()),
                    reinterpret_cast<BYTE*>(const_cast<char*>(key.data()))};
  DATA_BLOB output{};
  if (!CryptUnprotectData(&input, nullptr, &entropy, nullptr, nullptr,
                          CRYPTPROTECT_UI_FORBIDDEN, &output)) {
    return std::nullopt;
  }
  std::string decrypted(reinterpret_cast<char*>(output.pbData), output.cbData);
  LocalFree(output.pbData);
  return decrypted;
}

}  // namespace

WindowsServiceBridge::WindowsServiceBridge(flutter::BinaryMessenger* messenger,
                                           HWND window)
    : window_(window) {
  method_channel_ = std::make_unique<flutter::MethodChannel<EncodableValue>>(
      messenger, "com.elephant.network/windows_service",
      &flutter::StandardMethodCodec::GetInstance());
  method_channel_->SetMethodCallHandler(
      [this](const auto& call, auto result) {
        HandleMethod(call, std::move(result));
      });

  event_channel_ = std::make_unique<flutter::EventChannel<EncodableValue>>(
      messenger, "com.elephant.network/windows_service/events",
      &flutter::StandardMethodCodec::GetInstance());
  event_channel_->SetStreamHandler(
      std::make_unique<flutter::StreamHandlerFunctions<EncodableValue>>(
          [this](const EncodableValue*,
                 std::unique_ptr<flutter::EventSink<EncodableValue>>&& sink) {
            StartPolling(std::move(sink));
            return nullptr;
          },
          [this](const EncodableValue*) {
            StopPolling();
            return nullptr;
          }));
}

WindowsServiceBridge::~WindowsServiceBridge() {
  StopPolling();
  if (event_channel_) event_channel_->SetStreamHandler(nullptr);
  if (method_channel_) method_channel_->SetMethodCallHandler(nullptr);
}

void WindowsServiceBridge::HandleMethod(
    const flutter::MethodCall<EncodableValue>& call,
    std::unique_ptr<flutter::MethodResult<EncodableValue>> result) {
  const auto* arguments = Arguments(call);
  const auto& method = call.method_name();

  if (method == "protectSecret") {
    const auto key = StringArgument(arguments, "key");
    const auto value = StringArgument(arguments, "value");
    const auto protected_value = key && value ? ProtectSecret(*key, *value) : std::nullopt;
    if (!protected_value) {
      result->Error("DPAPI_ERROR", "Unable to encrypt the secret");
    } else {
      result->Success(EncodableValue(*protected_value));
    }
    return;
  }
  if (method == "unprotectSecret") {
    const auto key = StringArgument(arguments, "key");
    const auto value = StringArgument(arguments, "value");
    const auto plain_value = key && value ? UnprotectSecret(*key, *value) : std::nullopt;
    if (!plain_value) {
      result->Error("DPAPI_ERROR", "Unable to decrypt the secret");
    } else {
      result->Success(EncodableValue(*plain_value));
    }
    return;
  }
  if (method == "deleteSecret" || method == "deleteAllSecrets") {
    result->Success();
    return;
  }
  std::string arguments_json = "{}";
  if (method == "start" || method == "prepareSpeedTest") {
    const auto config = StringArgument(arguments, "config");
    if (!config || config->size() > elephant::kMaxConfigBytes) {
      result->Error("INVALID_CONFIG", "Missing or oversized service configuration");
      return;
    }
    arguments_json = "{\"config\":\"" + elephant::JsonEscape(*config) + "\"}";
  } else if (method == "urlTest") {
    const auto group = StringArgument(arguments, "group_tag");
    arguments_json = "{\"group_tag\":\"" +
                     elephant::JsonEscape(group.value_or("")) + "\"}";
  } else if (method == "selectOutbound") {
    const auto group = StringArgument(arguments, "group_tag");
    const auto outbound = StringArgument(arguments, "outbound_tag");
    arguments_json = "{\"group_tag\":\"" +
                     elephant::JsonEscape(group.value_or("")) +
                     "\",\"outbound_tag\":\"" +
                     elephant::JsonEscape(outbound.value_or("")) + "\"}";
  } else if (method != "getStatus" && method != "stop" &&
             method != "stopSpeedTest") {
    result->NotImplemented();
    return;
  }

  const auto response = elephant::SendServiceRequest(
      elephant::BuildRequest(method, arguments_json));
  result->Success(ResponseValue(response));
}

void WindowsServiceBridge::StartPolling(
    std::unique_ptr<flutter::EventSink<EncodableValue>>&& event_sink) {
  StopPolling();
  event_sink_ = std::move(event_sink);
  polling_ = true;
  poll_thread_ = std::thread(&WindowsServiceBridge::PollLoop, this);
}

void WindowsServiceBridge::StopPolling() {
  polling_ = false;
  if (poll_thread_.joinable()) poll_thread_.join();
  event_sink_.reset();
}

void WindowsServiceBridge::PollLoop() {
  std::string previous;
  while (polling_) {
    const auto response = elephant::SendServiceRequest(
        elephant::BuildRequest("getStatus"), 1200);
    if (response != previous) {
      {
        std::lock_guard<std::mutex> lock(event_mutex_);
        pending_event_ = response;
      }
      PostMessageW(window_, kEventMessage, 0, 0);
      previous = response;
    }
    for (int index = 0; index < 10 && polling_; ++index) {
      std::this_thread::sleep_for(std::chrono::milliseconds(100));
    }
  }
}

void WindowsServiceBridge::DrainServiceEvent() {
  std::string event;
  {
    std::lock_guard<std::mutex> lock(event_mutex_);
    event.swap(pending_event_);
  }
  if (!event.empty() && event_sink_) event_sink_->Success(ResponseValue(event));
}
