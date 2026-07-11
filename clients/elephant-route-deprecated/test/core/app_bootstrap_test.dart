import 'package:elephant_network/core/api/domain_resolver.dart';
import 'package:elephant_network/core/app_bootstrap.dart';
import 'package:flutter_test/flutter_test.dart';

class _FakeDomainResolver extends DomainResolver {
  int resolveCalls = 0;

  @override
  Future<String> resolve({bool force = false}) async {
    resolveCalls += 1;
    return 'https://runtime.example/';
  }
}

void main() {
  test('resolves once and injects one runtime domain into network clients',
      () async {
    final resolver = _FakeDomainResolver();

    final bootstrap = await AppBootstrap.initialize(domainResolver: resolver);

    expect(resolver.resolveCalls, 1);
    expect(bootstrap.domainResolver, same(resolver));
    expect(bootstrap.dioClient.currentBaseUrl, 'https://runtime.example/');
    expect(
      bootstrap.appUpdateService.currentBaseUrl,
      'https://runtime.example/',
    );
  });
}
