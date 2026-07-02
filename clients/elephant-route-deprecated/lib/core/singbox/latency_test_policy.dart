typedef LatencyProbe = Future<int> Function(String probeUrl, int timeoutMs);

class LatencyTestPolicy {
  static const int concurrency = 4;
  static const int timeoutMs = 3500;
  static const List<String> builtInProbeUrls = [
    'https://www.gstatic.com/generate_204',
    'http://cp.cloudflare.com/generate_204',
  ];

  static List<String> probeUrls({required String configuredTestUrl}) {
    final urls = <String>[...builtInProbeUrls];
    final configured = configuredTestUrl.trim();
    if (configured.isNotEmpty && !urls.contains(configured)) {
      urls.add(configured);
    }
    return urls;
  }

  static bool requiresConnectedVpn({
    required bool isWeb,
    required bool isAndroid,
    required bool isMockVpn,
  }) {
    return !isWeb && isAndroid && !isMockVpn;
  }
}

class LatencyTester {
  final List<String> probeUrls;
  final int timeoutMs;
  final LatencyProbe probe;

  const LatencyTester({
    required this.probeUrls,
    required this.timeoutMs,
    required this.probe,
  });

  Future<int> test() async {
    final latencies = await Future.wait(
      probeUrls.map((probeUrl) => probe(probeUrl, timeoutMs)),
    );
    final validLatencies = latencies.where((latency) => latency > 0);
    if (validLatencies.isEmpty) return -1;
    return validLatencies.reduce((best, latency) {
      return latency < best ? latency : best;
    });
  }
}
