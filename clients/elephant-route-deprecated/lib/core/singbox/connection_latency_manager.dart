typedef ConnectionLatencyResultCallback = void Function(
  String nodeTag,
  ConnectionLatencyResult result,
);

abstract interface class ConnectionLatencyManager {
  Future<Map<String, ConnectionLatencyResult>> testConnectionLatencies({
    required List<String> nodeTags,
    required String testUrl,
    required int timeoutMs,
    required int concurrency,
    ConnectionLatencyResultCallback? onResult,
  });

  Future<void> stopConnectionLatencyTest();
}

class ConnectionLatencyResult {
  const ConnectionLatencyResult({
    required this.latencyMs,
    required this.elapsedMs,
    this.attempts = const [],
  });

  final int latencyMs;
  final int elapsedMs;
  final List<int> attempts;
}
