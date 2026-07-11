import 'dart:convert';

import 'package:elephant_network/core/singbox/vpn_state.dart';
import 'package:elephant_network/core/singbox/windows_service_protocol.dart';
import 'package:elephant_network/core/singbox/windows_vpn_service.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const methodChannel = MethodChannel(WindowsServiceProtocol.methodChannel);
  const eventChannel = MethodChannel(WindowsServiceProtocol.eventChannel);
  final messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  final calls = <MethodCall>[];

  setUp(() {
    calls.clear();
    messenger.setMockMethodCallHandler(eventChannel, (_) async => null);
    messenger.setMockMethodCallHandler(methodChannel, (call) async {
      calls.add(call);
      if (call.method == 'urlTest') {
        return {'status': 'connected', 'delay': 42};
      }
      return {'status': call.method == 'stop' ? 'disconnected' : 'connected'};
    });
  });

  tearDown(() {
    messenger.setMockMethodCallHandler(eventChannel, null);
    messenger.setMockMethodCallHandler(methodChannel, null);
  });

  test('starts through the service with a forced TUN config', () async {
    final service = WindowsVpnService();
    await service.start(jsonEncode({
      'use_tun_mode': false,
      'inbounds': [
        {'type': 'mixed', 'listen_port': 2334},
      ],
      'outbounds': [
        {'type': 'direct', 'tag': 'direct'},
      ],
      'route': {'rules': <Object>[]},
    }));

    final startCall = calls.singleWhere((call) => call.method == 'start');
    final arguments = Map<String, dynamic>.from(startCall.arguments as Map);
    final config = jsonDecode(arguments['config'] as String) as Map;
    final inbounds = config['inbounds'] as List;
    expect(config.containsKey('use_tun_mode'), isFalse);
    expect(inbounds.where((item) => item['type'] == 'tun'), hasLength(1));
    expect(inbounds.where((item) => item['type'] == 'mixed'), isEmpty);
    expect(service.currentState.status, VpnStatus.connected);
    expect(service.currentState.connectionMode, VpnConnectionMode.tun);
    service.dispose();
  });

  test('uses service APIs for latency, outbound selection, and stop', () async {
    final service = WindowsVpnService();
    expect(await service.urlTest('节点选择'), 42);
    await service.selectOutbound('节点选择', 'Tokyo');
    await service.stop();

    expect(
      calls.map((call) => call.method),
      containsAllInOrder(['urlTest', 'selectOutbound', 'stop']),
    );
    expect(service.currentState.status, VpnStatus.disconnected);
    service.dispose();
  });
}
