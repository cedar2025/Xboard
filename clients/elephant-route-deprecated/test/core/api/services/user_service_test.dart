import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:elephant_network/core/api/dio_client.dart';
import 'package:elephant_network/core/api/services/user_service.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../../test_bootstrap.dart';

void main() {
  configureTestEnvironment();

  group('UserService.getInviteSummary', () {
    late List<String> requests;
    late DioClient dioClient;
    late UserService service;

    setUp(() {
      requests = [];
      dioClient = DioClient()
        ..dio.httpClientAdapter = _InviteAdapter((options) {
          requests.add('${options.method} ${options.path}');

          return switch (options.path) {
            '/api/v1/user/invite/fetch' => _json({
                'data': {
                  'codes': [
                    {'code': 'EXIST123'}
                  ],
                  'stat': [0, 0, 0, 10, 0],
                }
              }),
            _ => _json({'message': 'not found'}, statusCode: 404),
          };
        });
      service = UserService(dioClient);
    });

    test('returns existing invite code and commission rate', () async {
      final summary = await service.getInviteSummary();

      expect(summary?.code, 'EXIST123');
      expect(summary?.commissionRate, 10);
      expect(requests, ['GET /api/v1/user/invite/fetch']);
    });

    test('generates invite code with GET when fetch returns empty codes',
        () async {
      var generated = false;
      dioClient.dio.httpClientAdapter = _InviteAdapter((options) {
        requests.add('${options.method} ${options.path}');

        if (options.path == '/api/v1/user/invite/fetch') {
          return _json({
            'data': {
              'codes': generated
                  ? [
                      {'code': 'NEWCODE8'}
                    ]
                  : [],
              'stat': [0, 0, 0, 10, 0],
            }
          });
        }

        if (options.method == 'GET' &&
            options.path == '/api/v1/user/invite/save') {
          generated = true;
          return _json({'data': true});
        }

        return _json({'message': 'method not allowed'}, statusCode: 405);
      });

      final summary = await service.getInviteSummary();

      expect(summary?.code, 'NEWCODE8');
      expect(summary?.commissionRate, 10);
      expect(requests, [
        'GET /api/v1/user/invite/fetch',
        'GET /api/v1/user/invite/save',
        'GET /api/v1/user/invite/fetch',
      ]);
    });

    test('returns null when invite generation fails', () async {
      dioClient.dio.httpClientAdapter = _InviteAdapter((options) {
        requests.add('${options.method} ${options.path}');

        if (options.path == '/api/v1/user/invite/fetch') {
          return _json({
            'data': {
              'codes': [],
              'stat': [0, 0, 0, 10, 0],
            }
          });
        }

        return _json({'message': 'failed'}, statusCode: 500);
      });

      final summary = await service.getInviteSummary();

      expect(summary, isNull);
      expect(requests, [
        'GET /api/v1/user/invite/fetch',
        'GET /api/v1/user/invite/save',
      ]);
    });

    test('preserves a decimal commission rate', () async {
      dioClient.dio.httpClientAdapter = _InviteAdapter((options) {
        return _json({
          'data': {
            'codes': [
              {'code': 'DECIMAL8'}
            ],
            'stat': [0, 0, 0, 12.5, 0],
          }
        });
      });

      final summary = await service.getInviteSummary();

      expect(summary?.code, 'DECIMAL8');
      expect(summary?.commissionRate, 12.5);
    });

    test('does not invent a rate when stat is missing', () async {
      dioClient.dio.httpClientAdapter = _InviteAdapter((options) {
        return _json({
          'data': {
            'codes': [
              {'code': 'NORATE88'}
            ],
          }
        });
      });

      final summary = await service.getInviteSummary();

      expect(summary?.code, 'NORATE88');
      expect(summary?.commissionRate, isNull);
    });
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

class _InviteAdapter implements HttpClientAdapter {
  final ResponseBody Function(RequestOptions options) handler;

  _InviteAdapter(this.handler);

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    return handler(options);
  }

  @override
  void close({bool force = false}) {}
}
