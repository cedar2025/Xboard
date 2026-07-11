import '../utils/constants.dart';
import 'api/dio_client.dart';
import 'api/domain_resolver.dart';
import 'api/services/app_update_service.dart';

class AppBootstrap {
  const AppBootstrap._({
    required this.domainResolver,
    required this.dioClient,
    required this.appUpdateService,
  });

  final DomainResolver domainResolver;
  final DioClient dioClient;
  final AppUpdateService appUpdateService;

  static Future<AppBootstrap> initialize({
    DomainResolver? domainResolver,
  }) async {
    final resolver = domainResolver ?? DomainResolver();
    final resolvedBaseUrl = await resolver.resolve();
    final updateBaseUrl = ApiConstants.hasAppDistributionOverride
        ? ApiConstants.appDistributionBaseUrl
        : resolvedBaseUrl;

    return AppBootstrap._(
      domainResolver: resolver,
      dioClient: DioClient(
        domainResolver: resolver,
        initialBaseUrl: resolvedBaseUrl,
      ),
      appUpdateService: AppUpdateService(
        domainResolver: resolver,
        initialBaseUrl: updateBaseUrl,
      ),
    );
  }
}
