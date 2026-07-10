import 'package:dio/dio.dart';
import '../services/app_logger.dart';
import '../storage/secure_storage.dart';
import '../../utils/constants.dart';
import 'domain_resolver.dart';
import 'interceptors/auth_interceptor.dart';
import 'package:dio/io.dart';
import 'dart:io';

class DioClient {
  DioClient({DomainResolver? domainResolver})
      : _domainResolver = domainResolver ?? DomainResolver() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConstants.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
    ));

    _dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        if (ApiConstants.allowInsecureCertificates) {
          client.badCertificateCallback =
              (X509Certificate cert, String host, int port) => true;
          AppLogger.instance.warn(
              'Insecure certificate validation is enabled for ${ApiConstants.baseUrl}');
        }
        return client;
      },
    );

    _dio.interceptors.add(QueuedInterceptorsWrapper(
      onRequest: (options, handler) async {
        if (_usesRuntimeBaseUrl(options)) {
          final baseUrl = await _domainResolver.resolve();
          _applyBaseUrl(baseUrl);
          options.baseUrl = baseUrl;
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        if (!_shouldRetryAfterDomainSwitch(error)) {
          handler.next(error);
          return;
        }

        try {
          final previousBaseUrl = _dio.options.baseUrl;
          final nextBaseUrl = await _domainResolver.resolve(force: true);
          _applyBaseUrl(nextBaseUrl);

          if (nextBaseUrl == previousBaseUrl) {
            handler.next(error);
            return;
          }

          final requestOptions = error.requestOptions;
          requestOptions.extra[_domainRetryKey] = true;
          requestOptions.baseUrl = nextBaseUrl;
          final response = await _dio.fetch<dynamic>(requestOptions);
          handler.resolve(response);
        } catch (e, stackTrace) {
          await AppLogger.instance.error(
            'Domain failover retry failed',
            error: e,
            stackTrace: stackTrace,
          );
          handler.next(error);
        }
      },
    ));

    // 添加认证拦截器
    _dio.interceptors.add(AuthInterceptor(_secureStorage));
  }

  static const _domainRetryKey = 'domain_failover_retry';

  late Dio _dio;
  final _secureStorage = const SecureStorage();
  final DomainResolver _domainResolver;

  Dio get dio => _dio;
  SecureStorage get storage => _secureStorage;
  String get currentBaseUrl => _dio.options.baseUrl;

  Future<String> refreshDomain() async {
    final baseUrl = await _domainResolver.resolve(force: true);
    _applyBaseUrl(baseUrl);
    return baseUrl;
  }

  void _applyBaseUrl(String baseUrl) {
    if (_dio.options.baseUrl == baseUrl) return;
    _dio.options.baseUrl = baseUrl;
  }

  bool _usesRuntimeBaseUrl(RequestOptions options) {
    return !options.path.startsWith(RegExp(r'https?://'));
  }

  bool _shouldRetryAfterDomainSwitch(DioException error) {
    final options = error.requestOptions;
    if (!_usesRuntimeBaseUrl(options)) return false;
    if (options.extra[_domainRetryKey] == true) return false;
    if (!_isSafeRetryMethod(options.method)) return false;
    if (error.response != null) return false;

    return switch (error.type) {
      DioExceptionType.connectionTimeout ||
      DioExceptionType.sendTimeout ||
      DioExceptionType.receiveTimeout ||
      DioExceptionType.connectionError ||
      DioExceptionType.badCertificate =>
        true,
      _ => false,
    };
  }

  bool _isSafeRetryMethod(String method) {
    final normalized = method.toUpperCase();
    return normalized == 'GET' ||
        normalized == 'HEAD' ||
        normalized == 'OPTIONS';
  }
}
