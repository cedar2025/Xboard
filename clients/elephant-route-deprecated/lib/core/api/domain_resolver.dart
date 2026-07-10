import 'package:dio/dio.dart';

import '../../utils/constants.dart';
import '../services/app_logger.dart';
import '../storage/local_storage.dart';

class DomainResolver {
  DomainResolver({
    Dio? dio,
    LocalStorage storage = const LocalStorage(),
  })  : _dio = dio ??
            Dio(BaseOptions(
              connectTimeout: const Duration(seconds: 4),
              receiveTimeout: const Duration(seconds: 4),
            )),
        _storage = storage;

  static const _cachedBaseUrlKey = 'runtime_api_base_url';

  final Dio _dio;
  final LocalStorage _storage;

  String? _currentBaseUrl;
  Future<String>? _activeResolve;
  bool _resolvedThisRun = false;

  String get currentBaseUrl => _currentBaseUrl ?? ApiConstants.baseUrl;

  Future<String> resolve({bool force = false}) async {
    if (ApiConstants.disableDynamicDomain) {
      _currentBaseUrl = _normalizeBaseUrl(ApiConstants.baseUrl);
      return _currentBaseUrl!;
    }

    if (!force && _resolvedThisRun && _currentBaseUrl != null) {
      return _currentBaseUrl!;
    }

    final activeResolve = _activeResolve;
    if (!force && activeResolve != null) {
      return activeResolve;
    }

    final resolveFuture = _resolve();
    _activeResolve = resolveFuture;
    try {
      final baseUrl = await resolveFuture;
      _resolvedThisRun = true;
      return baseUrl;
    } finally {
      if (identical(_activeResolve, resolveFuture)) {
        _activeResolve = null;
      }
    }
  }

  Future<String> _resolve() async {
    final fallbackBaseUrl = await _fallbackBaseUrl();
    final candidates = await _fetchCandidates(fallbackBaseUrl);
    final probes = await Future.wait(candidates.map(_probe));
    final available = probes.where((probe) => probe.available).toList()
      ..sort((a, b) {
        final weightCompare = b.candidate.weight.compareTo(a.candidate.weight);
        if (weightCompare != 0) return weightCompare;
        return a.elapsedMilliseconds.compareTo(b.elapsedMilliseconds);
      });

    final selected =
        available.isNotEmpty ? available.first.candidate.url : fallbackBaseUrl;
    await _setCurrentBaseUrl(selected);

    if (available.isNotEmpty) {
      await AppLogger.instance.info(
        'Selected API domain: $selected, latency=${available.first.elapsedMilliseconds}ms',
      );
    } else {
      await AppLogger.instance.warn(
        'No API domain passed health check, using fallback: $selected',
      );
    }

    return selected;
  }

  Future<String> _fallbackBaseUrl() async {
    final cached = await _storage.read(key: _cachedBaseUrlKey);
    final normalizedCached = _normalizeBaseUrl(cached);
    if (normalizedCached != null) return normalizedCached;
    return _normalizeBaseUrl(ApiConstants.baseUrl)!;
  }

  Future<void> _setCurrentBaseUrl(String baseUrl) async {
    final normalized = _normalizeBaseUrl(baseUrl);
    if (normalized == null) return;
    _currentBaseUrl = normalized;
    await _storage.write(key: _cachedBaseUrlKey, value: normalized);
  }

  Future<List<DomainCandidate>> _fetchCandidates(String fallbackBaseUrl) async {
    final fallback = DomainCandidate(
      url: fallbackBaseUrl,
      name: '内置入口',
      weight: 0,
      healthPath: ApiConstants.domainHealthPath,
    );

    try {
      final response = await _dio.get(ApiConstants.domainConfigUrl);
      final parsed = _parseCandidates(response.data);
      if (parsed.isNotEmpty) {
        return _dedupeCandidates([...parsed, fallback]);
      }
    } catch (e, stackTrace) {
      await AppLogger.instance.error(
        'Failed to fetch domain config',
        error: e,
        stackTrace: stackTrace,
      );
    }

    return _dedupeCandidates([
      fallback,
      DomainCandidate(
        url: ApiConstants.baseUrl,
        name: '默认入口',
        weight: 0,
        healthPath: ApiConstants.domainHealthPath,
      ),
    ]);
  }

  List<DomainCandidate> _parseCandidates(dynamic data) {
    if (data is! Map) return const [];
    final domains = data['domains'];
    if (domains is! List) return const [];

    return domains
        .whereType<Map>()
        .where((item) => item['enabled'] != false)
        .map((item) {
          final url = _normalizeBaseUrl(item['url'] as String?);
          if (url == null) return null;

          return DomainCandidate(
            url: url,
            name: item['name'] as String?,
            weight: _parseInt(item['weight']),
            healthPath: _parseHealthPath(item['healthPath']),
          );
        })
        .whereType<DomainCandidate>()
        .toList();
  }

  Future<_DomainProbeResult> _probe(DomainCandidate candidate) async {
    final stopwatch = Stopwatch()..start();
    try {
      final response = await _dio.getUri(
        Uri.parse(candidate.url).resolve(candidate.healthPath),
        options: Options(
          headers: const {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache',
          },
          validateStatus: (status) => status != null && status < 500,
        ),
      );
      stopwatch.stop();

      final statusCode = response.statusCode ?? 0;
      return _DomainProbeResult(
        candidate: candidate,
        available: statusCode >= 200 && statusCode < 400,
        elapsedMilliseconds: stopwatch.elapsedMilliseconds,
      );
    } catch (_) {
      stopwatch.stop();
      return _DomainProbeResult(
        candidate: candidate,
        available: false,
        elapsedMilliseconds: stopwatch.elapsedMilliseconds,
      );
    }
  }

  List<DomainCandidate> _dedupeCandidates(List<DomainCandidate> candidates) {
    final seen = <String>{};
    return candidates.where((candidate) => seen.add(candidate.url)).toList();
  }

  static String _parseHealthPath(dynamic value) {
    if (value is String && value.trim().isNotEmpty) {
      final path = value.trim();
      return path.startsWith('/') ? path : '/$path';
    }
    return ApiConstants.domainHealthPath;
  }

  static int _parseInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value) ?? 0;
    return 0;
  }

  static String? _normalizeBaseUrl(String? value) {
    final trimmed = value?.trim();
    if (trimmed == null || trimmed.isEmpty) return null;

    final uri = Uri.tryParse(trimmed);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) return null;
    if (uri.scheme != 'http' && uri.scheme != 'https') return null;

    var normalized = uri.replace(path: uri.path.replaceAll(RegExp(r'/+$'), ''));
    if (normalized.path == '/') {
      normalized = normalized.replace(path: '');
    }
    return normalized.toString().replaceAll(RegExp(r'/+$'), '');
  }
}

class DomainCandidate {
  const DomainCandidate({
    required this.url,
    required this.weight,
    required this.healthPath,
    this.name,
  });

  final String url;
  final String? name;
  final int weight;
  final String healthPath;
}

class _DomainProbeResult {
  const _DomainProbeResult({
    required this.candidate,
    required this.available,
    required this.elapsedMilliseconds,
  });

  final DomainCandidate candidate;
  final bool available;
  final int elapsedMilliseconds;
}
