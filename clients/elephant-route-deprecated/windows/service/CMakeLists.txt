add_executable(ElephantNetworkService
  "service_main.cpp"
  "service.rc"
  "../common/windows_protocol.cpp"
)

apply_standard_settings(ElephantNetworkService)
target_compile_options(ElephantNetworkService PRIVATE /utf-8)
target_include_directories(ElephantNetworkService PRIVATE "../common")
target_compile_definitions(ElephantNetworkService PRIVATE UNICODE _UNICODE NOMINMAX)
target_compile_definitions(ElephantNetworkService PRIVATE
  "FLUTTER_VERSION=\"${FLUTTER_VERSION}\""
  "FLUTTER_VERSION_MAJOR=${FLUTTER_VERSION_MAJOR}"
  "FLUTTER_VERSION_MINOR=${FLUTTER_VERSION_MINOR}"
  "FLUTTER_VERSION_PATCH=${FLUTTER_VERSION_PATCH}"
  "FLUTTER_VERSION_BUILD=${FLUTTER_VERSION_BUILD}")
target_link_libraries(ElephantNetworkService PRIVATE advapi32 winhttp shell32 ole32 version)
