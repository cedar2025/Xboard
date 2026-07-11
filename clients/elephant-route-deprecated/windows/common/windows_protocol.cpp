#include "windows_protocol.h"

#include <windows.h>

#include <algorithm>
#include <array>
#include <charconv>
#include <sstream>
#include <vector>

namespace elephant {
namespace {

bool WriteAll(HANDLE handle, const void* data, DWORD length) {
  const auto* cursor = static_cast<const std::uint8_t*>(data);
  DWORD remaining = length;
  while (remaining > 0) {
    DWORD written = 0;
    if (!WriteFile(handle, cursor, remaining, &written, nullptr) || written == 0) {
      return false;
    }
    cursor += written;
    remaining -= written;
  }
  return true;
}

bool ReadAll(HANDLE handle, void* data, DWORD length) {
  auto* cursor = static_cast<std::uint8_t*>(data);
  DWORD remaining = length;
  while (remaining > 0) {
    DWORD read = 0;
    if (!ReadFile(handle, cursor, remaining, &read, nullptr) || read == 0) {
      return false;
    }
    cursor += read;
    remaining -= read;
  }
  return true;
}

std::size_t FindValue(const std::string& json, const std::string& key) {
  const std::string needle = "\"" + key + "\"";
  auto position = json.find(needle);
  if (position == std::string::npos) return position;
  position = json.find(':', position + needle.size());
  if (position == std::string::npos) return position;
  return json.find_first_not_of(" \t\r\n", position + 1);
}

int HexDigit(char value) {
  if (value >= '0' && value <= '9') return value - '0';
  if (value >= 'a' && value <= 'f') return value - 'a' + 10;
  if (value >= 'A' && value <= 'F') return value - 'A' + 10;
  return -1;
}

void AppendCodePoint(std::string* output, std::uint32_t code_point) {
  if (code_point <= 0x7f) {
    output->push_back(static_cast<char>(code_point));
  } else if (code_point <= 0x7ff) {
    output->push_back(static_cast<char>(0xc0 | (code_point >> 6)));
    output->push_back(static_cast<char>(0x80 | (code_point & 0x3f)));
  } else {
    output->push_back(static_cast<char>(0xe0 | (code_point >> 12)));
    output->push_back(static_cast<char>(0x80 | ((code_point >> 6) & 0x3f)));
    output->push_back(static_cast<char>(0x80 | (code_point & 0x3f)));
  }
}

}  // namespace

std::string JsonEscape(const std::string& value) {
  std::ostringstream output;
  for (const unsigned char character : value) {
    switch (character) {
      case '\"': output << "\\\""; break;
      case '\\': output << "\\\\"; break;
      case '\b': output << "\\b"; break;
      case '\f': output << "\\f"; break;
      case '\n': output << "\\n"; break;
      case '\r': output << "\\r"; break;
      case '\t': output << "\\t"; break;
      default:
        if (character < 0x20) {
          constexpr char kHex[] = "0123456789abcdef";
          output << "\\u00" << kHex[(character >> 4) & 0xf]
                 << kHex[character & 0xf];
        } else {
          output << static_cast<char>(character);
        }
    }
  }
  return output.str();
}

std::optional<std::string> JsonString(const std::string& json,
                                      const std::string& key) {
  auto position = FindValue(json, key);
  if (position == std::string::npos || json[position] != '\"') {
    return std::nullopt;
  }

  std::string result;
  for (++position; position < json.size(); ++position) {
    const char character = json[position];
    if (character == '\"') return result;
    if (character != '\\') {
      result.push_back(character);
      continue;
    }
    if (++position >= json.size()) return std::nullopt;
    switch (json[position]) {
      case '\"': result.push_back('\"'); break;
      case '\\': result.push_back('\\'); break;
      case '/': result.push_back('/'); break;
      case 'b': result.push_back('\b'); break;
      case 'f': result.push_back('\f'); break;
      case 'n': result.push_back('\n'); break;
      case 'r': result.push_back('\r'); break;
      case 't': result.push_back('\t'); break;
      case 'u': {
        if (position + 4 >= json.size()) return std::nullopt;
        std::uint32_t code_point = 0;
        for (int index = 0; index < 4; ++index) {
          const int digit = HexDigit(json[++position]);
          if (digit < 0) return std::nullopt;
          code_point = (code_point << 4) | static_cast<std::uint32_t>(digit);
        }
        AppendCodePoint(&result, code_point);
        break;
      }
      default: return std::nullopt;
    }
  }
  return std::nullopt;
}

std::optional<std::int64_t> JsonInteger(const std::string& json,
                                        const std::string& key) {
  auto position = FindValue(json, key);
  if (position == std::string::npos) return std::nullopt;
  const char* begin = json.data() + position;
  const char* end = json.data() + json.size();
  std::int64_t value = 0;
  const auto result = std::from_chars(begin, end, value);
  if (result.ec != std::errc()) return std::nullopt;
  return value;
}

std::string BuildRequest(const std::string& method,
                         const std::string& arguments_json) {
  return "{\"version\":" + std::to_string(kProtocolVersion) +
         ",\"method\":\"" + JsonEscape(method) + "\",\"arguments\":" +
         arguments_json + "}";
}

std::string BuildError(const std::string& code, const std::string& message) {
  return "{\"status\":\"error\",\"error_code\":\"" + JsonEscape(code) +
         "\",\"error_message\":\"" + JsonEscape(message) + "\"}";
}

std::wstring Utf8ToWide(const std::string& value) {
  if (value.empty()) return {};
  const int length = MultiByteToWideChar(CP_UTF8, MB_ERR_INVALID_CHARS,
                                         value.data(),
                                         static_cast<int>(value.size()),
                                         nullptr, 0);
  if (length <= 0) return {};
  std::wstring result(length, L'\0');
  MultiByteToWideChar(CP_UTF8, MB_ERR_INVALID_CHARS, value.data(),
                      static_cast<int>(value.size()), result.data(), length);
  return result;
}

std::string WideToUtf8(const std::wstring& value) {
  if (value.empty()) return {};
  const int length = WideCharToMultiByte(CP_UTF8, WC_ERR_INVALID_CHARS,
                                         value.data(),
                                         static_cast<int>(value.size()),
                                         nullptr, 0, nullptr, nullptr);
  if (length <= 0) return {};
  std::string result(length, '\0');
  WideCharToMultiByte(CP_UTF8, WC_ERR_INVALID_CHARS, value.data(),
                      static_cast<int>(value.size()), result.data(), length,
                      nullptr, nullptr);
  return result;
}

std::wstring ExecutableDirectory() {
  std::vector<wchar_t> buffer(32768);
  const DWORD length = GetModuleFileNameW(nullptr, buffer.data(),
                                          static_cast<DWORD>(buffer.size()));
  if (length == 0 || length >= static_cast<DWORD>(buffer.size())) return {};
  std::wstring path(buffer.data(), length);
  const auto separator = path.find_last_of(L"\\/");
  return separator == std::wstring::npos ? std::wstring() : path.substr(0, separator);
}

bool WriteFramedMessage(void* raw_handle, const std::string& payload) {
  if (payload.size() > kMaxMessageBytes) return false;
  const auto handle = static_cast<HANDLE>(raw_handle);
  const auto length = static_cast<std::uint32_t>(payload.size());
  return WriteAll(handle, &length, sizeof(length)) &&
         WriteAll(handle, payload.data(), length);
}

bool ReadFramedMessage(void* raw_handle, std::string* payload) {
  const auto handle = static_cast<HANDLE>(raw_handle);
  std::uint32_t length = 0;
  if (!ReadAll(handle, &length, sizeof(length)) || length > kMaxMessageBytes) {
    return false;
  }
  payload->assign(length, '\0');
  return length == 0 || ReadAll(handle, payload->data(), length);
}

std::string SendServiceRequest(const std::string& request,
                               std::uint32_t timeout_ms) {
  if (!WaitNamedPipeW(kPipeName, timeout_ms)) {
    return BuildError("service_unavailable", "Windows service is unavailable");
  }
  HANDLE pipe = CreateFileW(kPipeName, GENERIC_READ | GENERIC_WRITE, 0,
                            nullptr, OPEN_EXISTING, FILE_ATTRIBUTE_NORMAL,
                            nullptr);
  if (pipe == INVALID_HANDLE_VALUE) {
    return BuildError("service_unavailable", "Cannot connect to Windows service");
  }

  std::string response;
  const bool success = WriteFramedMessage(pipe, request) &&
                       ReadFramedMessage(pipe, &response);
  CloseHandle(pipe);
  return success ? response
                 : BuildError("service_unavailable", "Windows service IPC failed");
}

}  // namespace elephant
