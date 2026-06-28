import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:elephant_network/core/api/dio_client.dart';
import 'package:elephant_network/providers/user_provider.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_bootstrap.dart';

void main() {
  configureTestEnvironment();

  group('UserProvider invite code state', () {
    late DioClient dioClient;
    late UserProvider provider;

    setUp(() {
      dioClient = DioClient();
      provider = UserProvider(dioClient);
    });

    test('keeps user info visible and marks invite failure when generation fails',
        () async {
      dioClient.dio.httpClientAdapter = _ProviderAdapter((options) {
        if (options.path == '/api/v1/user/info') {
          return _json({
            'data': {
              'email': 'test@example.com',
              'transfer_enable': 100,
              'u': 0,
              'd': 0,
              'balance': 0,
            }
          });
        }

        if (options.path == '/api/v1/user/invite/fetch') {
          return _json({
            'data': {
              'codes': [],
              'stat': [0, 0, 0, 10, 0],
            }
          });
        }

        if (options.path == '/api/v1/user/invite/save') {
          return _json({'message': 'failed'}, statusCode: 500);
        }

        return _json({'message': 'not found'}, statusCode: 404);
      });

      await provider.fetchUserInfo();
      await pumpEventQueue();

      expect(provider.user?.email, 'test@example.com');
      expect(provider.inviteCode, isNull);
      expect(provider.isInviteCodeLoading, isFalse);
      expect(provider.inviteCodeLoadFailed, isTrue);
    });

    test('can retry invite code loading after an initial failure', () async {
      var failSave = true;
      dioClient.dio.httpClientAdapter = _ProviderAdapter((options) {
        if (options.path == '/api/v1/user/info') {
          return _json({
            'data': {
              'email': 'test@example.com',
              'transfer_enable': 100,
              'u': 0,
              'd': 0,
              'balance': 0,
            }
          });
        }

        if (options.path == '/api/v1/user/invite/fetch') {
          return _json({
            'data': {
              'codes': failSave
                  ? []
                  : [
                      {'code': 'RETRY123'}
                    ],
              'stat': [0, 0, 0, 10, 0],
            }
          });
        }

        if (options.path == '/api/v1/user/invite/save') {
          if (failSave) {
            failSave = false;
            return _json({'message': 'failed'}, statusCode: 500);
          }
          return _json({'data': true});
        }

        return _json({'message': 'not found'}, statusCode: 404);
      });

      await provider.fetchUserInfo();
      await pumpEventQueue();
      expect(provider.inviteCodeLoadFailed, isTrue);

      await provider.fetchInviteCode();

      expect(provider.inviteCode, 'RETRY123');
      expect(provider.inviteCodeLoadFailed, isFalse);
    });

    test('can ensure invite code loads when user info already exists',
        () async {
      dioClient.dio.httpClientAdapter = _ProviderAdapter((options) {
        if (options.path == '/api/v1/user/info') {
          return _json({
            'data': {
              'email': 'test@example.com',
              'transfer_enable': 100,
              'u': 0,
              'd': 0,
              'balance': 0,
            }
          });
        }

        if (options.path == '/api/v1/user/invite/fetch') {
          return _json({
            'data': {
              'codes': [
                {'code': 'ENSURE88'}
              ],
              'stat': [0, 0, 0, 10, 0],
            }
          });
        }

        return _json({'message': 'not found'}, statusCode: 404);
      });

      await provider.fetchUserInfo();
      await pumpEventQueue();
      provider.clearInviteCodeForTest();

      provider.ensureInviteCodeLoaded();
      await pumpEventQueue();

      expect(provider.inviteCode, 'ENSURE88');
      expect(provider.inviteCodeLoadFailed, isFalse);
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

class _ProviderAdapter implements HttpClientAdapter {
  final ResponseBody Function(RequestOptions options) handler;

  _ProviderAdapter(this.handler);

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
