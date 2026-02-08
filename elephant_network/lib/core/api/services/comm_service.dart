import '../dio_client.dart';
import '../../../utils/constants.dart';

class CommService {
  final DioClient _client;

  CommService(this._client);

  /// 发送邮箱验证码
  Future<void> sendEmailVerify(String email) async {
    await _client.dio.post(
      ApiConstants.sendEmailVerify,
      data: {'email': email},
    );
  }
}
