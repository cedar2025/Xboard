#include <cassert>
#include <iostream>
#include <string>

#include "windows_protocol.h"

int main() {
  const std::string nested = R"({"inbounds":[{"type":"tun"}],"tag":"东京"})";
  const auto request = elephant::BuildRequest(
      "start", "{\"config\":\"" + elephant::JsonEscape(nested) + "\"}");
  assert(elephant::JsonInteger(request, "version") == 1);
  assert(elephant::JsonString(request, "method") == "start");
  assert(elephant::JsonString(request, "config") == nested);

  const auto error = elephant::BuildError("config_invalid", "bad \"value\"");
  assert(elephant::JsonString(error, "status") == "error");
  assert(elephant::JsonString(error, "error_code") == "config_invalid");
  assert(elephant::JsonString(error, "error_message") == "bad \"value\"");

  const auto wide = elephant::Utf8ToWide("大象网络");
  assert(!wide.empty());
  assert(elephant::WideToUtf8(wide) == "大象网络");
  std::cout << "windows_protocol_test passed\n";
  return 0;
}
