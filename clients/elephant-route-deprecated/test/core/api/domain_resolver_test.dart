import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:elephant_network/core/api/dio_client.dart';
import 'package:elephant_network/core/api/domain_resolver.dart';
import 'package:elephant_network/utils/constants.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../test_bootstrap.dart';

void main() {
  configureTestEnvironment();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  test('selects the highest weight healthy domain from control plane',
      () async {
    final dio = Dio()
      ..httpClientAdapter = _Adapter((options, _) async {
        if (options.uri.toString() == ApiConstants.domainConfigUrl) {
          return _json({
            'domains': [
              {
                'url': 'https://www.elephant223.com',
                'name': '海外站',
                'weight': 60,
                'enabled': true,
                'healthPath': ApiConstants.domainHealthPath,
              },
              {
                'url': 'https://www.elephant111.com',
                'name': '国内最新入口',
                'weight': 80,
                'enabled': true,
                'healthPath': ApiConstants.domainHealthPath,
              },
            ],
          });
        }

        if (options.uri.path == ApiConstants.domainHealthPath) {
          return _json({'ok': true});
        }

        return _json({'message': 'not found'}, statusCode: 404);
      });

    final resolver = DomainResolver(dio: dio);
    final selected = await resolver.resolve();

    expect(selected, 'https://www.elephant111.com');

    final prefs = await SharedPreferences.getInstance();
    expect(
      prefs.getString('runtime_api_base_url'),
      'https://www.elephant111.com',
    );
  });

  test('skips unhealthy domains and falls through to the next candidate',
      () async {
    final dio = Dio()
      ..httpClientAdapter = _Adapter((options, _) async {
        if (options.uri.toString() == ApiConstants.domainConfigUrl) {
          return _json({
            'domains': [
              {
                'url': 'https://www.elephant111.com',
                'weight': 80,
                'enabled': true,
                'healthPath': ApiConstants.domainHealthPath,
              },
              {
                'url': 'https://www.elphantroute.com',
                'weight': 50,
                'enabled': true,
                'healthPath': ApiConstants.domainHealthPath,
              },
            ],
          });
        }

        if (options.uri.host == 'www.elephant111.com') {
          return _json({'message': 'blocked'}, statusCode: 503);
        }

        if (options.uri.host == 'www.elphantroute.com') {
          return _json({'ok': true});
        }

        return _json({'message': 'not found'}, statusCode: 404);
      });

    final resolver = DomainResolver(dio: dio);

    expect(await resolver.resolve(), 'https://www.elphantroute.com');
  });

  test('DioClient sends relative API requests to the resolved runtime domain',
      () async {
    final domainDio = Dio()
      ..httpClientAdapter = _Adapter((options, _) async {
        if (options.uri.toString() == ApiConstants.domainConfigUrl) {
          return _json({
            'domains': [
              {
                'url': 'https://www.elephant111.com',
                'weight': 80,
                'enabled': true,
                'healthPath': ApiConstants.domainHealthPath,
              },
            ],
          });
        }

        if (options.uri.host == 'www.elephant111.com' &&
            options.uri.path == ApiConstants.domainHealthPath) {
          return _json({'ok': true});
        }

        return _json({'message': 'not found'}, statusCode: 404);
      });

    final requests = <Uri>[];
    final client = DioClient(domainResolver: DomainResolver(dio: domainDio))
      ..dio.httpClientAdapter = _Adapter((options, _) async {
        requests.add(options.uri);
        return _json({
          'data': {'email': 'test@example.com'},
        });
      });

    await client.dio.get(ApiConstants.userInfo);

    expect(requests, hasLength(1));
    expect(requests.single.host, 'www.elephant111.com');
    expect(client.currentBaseUrl, 'https://www.elephant111.com');
  });
}

ResponseBody _json(Map<String, Object?> data, {int statusCode = 200}) {
  return ResponseBody.fromString(
    jsonEncode(data),
    statusCode,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    },
  );
}

class _Adapter implements HttpClientAdapter {
  _Adapter(this.handler);

  final Future<ResponseBody> Function(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
  ) handler;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) {
    return handler(options, requestStream);
  }

  @override
  void close({bool force = false}) {}
}
