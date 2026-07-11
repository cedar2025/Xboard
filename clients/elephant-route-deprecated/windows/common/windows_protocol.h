#ifndef ELEPHANT_NETWORK_WINDOWS_COMMON_WINDOWS_PROTOCOL_H_
#define ELEPHANT_NETWORK_WINDOWS_COMMON_WINDOWS_PROTOCOL_H_

#include <cstdint>
#include <optional>
#include <string>

namespace elephant {

inline constexpr wchar_t kServiceName[] = L"ElephantNetworkService";
inline constexpr wchar_t kPipeName[] = L"\\\\.\\pipe\\ElephantNetworkService.v1";
inline constexpr std::uint32_t kProtocolVersion = 1;
inline constexpr std::uint32_t kMaxConfigBytes = 4 * 1024 * 1024;
inline constexpr std::uint32_t kMaxMessageBytes = 5 * 1024 * 1024;

std::string JsonEscape(const std::string& value);
std::optional<std::string> JsonString(const std::string& json,
                                      const std::string& key);
std::optional<std::int64_t> JsonInteger(const std::string& json,
                                        const std::string& key);
std::string BuildRequest(const std::string& method,
                         const std::string& arguments_json = "{}");
std::string BuildError(const std::string& code, const std::string& message);

std::wstring Utf8ToWide(const std::string& value);
std::string WideToUtf8(const std::wstring& value);
std::wstring ExecutableDirectory();

bool WriteFramedMessage(void* handle, const std::string& payload);
bool ReadFramedMessage(void* handle, std::string* payload);
std::string SendServiceRequest(const std::string& request,
                               std::uint32_t timeout_ms = 5000);

}  // namespace elephant

#endif  // ELEPHANT_NETWORK_WINDOWS_COMMON_WINDOWS_PROTOCOL_H_
