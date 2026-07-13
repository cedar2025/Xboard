typedef LatencyProbe = Future<int> Function(String probeUrl, int timeoutMs);

enum LatencyTestProfile { standard, v2boxConnection }

class LatencyTestPolicy {
  static const int concurrency = 4;
  static const int timeoutMs = 3500;
  static const int v2boxConnectionTimeoutMs = 5000;
  static const List<String> builtInProbeUrls = [
    'https://www.gstatic.com/generate_204',
    'http://cp.cloudflare.com/generate_204',
  ];

  static List<String> probeUrls({
    required String configuredTestUrl,
    LatencyTestProfile profile = LatencyTestProfile.standard,
  }) {
    final configured = configuredTestUrl.trim();
    if (profile == LatencyTestProfile.v2boxConnection) {
      if (configured.isEmpty ||
          configured == 'http://cp.cloudflare.com/generate_204') {
        return const ['https://www.gstatic.com/generate_204'];
      }
      return [configured];
    }

    final urls = <String>[...builtInProbeUrls];
    if (configured.isNotEmpty && !urls.contains(configured)) {
      urls.add(configured);
    }
    return urls;
  }

  static int timeoutMsFor(LatencyTestProfile profile) {
    return profile == LatencyTestProfile.v2boxConnection
        ? v2boxConnectionTimeoutMs
        : timeoutMs;
  }

  static bool requiresConnectedVpn({
    required bool isWeb,
    required bool isAndroid,
    required bool isWindows,
    required bool isMacOS,
    required bool isMockVpn,
  }) {
    return !isWeb && (isAndroid || isWindows || isMacOS) && !isMockVpn;
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
