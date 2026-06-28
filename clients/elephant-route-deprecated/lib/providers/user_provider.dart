import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';
import '../core/api/dio_client.dart';
import '../core/api/services/user_service.dart';
import '../models/user.dart';

class UserProvider with ChangeNotifier {
  final UserService _userService;

  User? _user;
  Map<String, dynamic>? _subscribeInfo;
  List<dynamic> _plans = [];
  bool _isLoading = false;
  String? _errorMessage;
  String? _inviteCode; // [NEW] Invite code storage

  UserProvider(DioClient dioClient) : _userService = UserService(dioClient);

  User? get user => _user;
  Map<String, dynamic>? get subscribeInfo => _subscribeInfo;
  List<dynamic> get plans => _plans;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  String? get inviteCode => _inviteCode; // Expose invite code

  /// 获取用户信息
  Future<void> fetchUserInfo() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _user = await _userService.getUserInfo();

      // Async fetch invite code in background without failing main user info fetch
      _userService.getInviteCode().then((code) {
        if (code != null) {
          _inviteCode = code;
          notifyListeners();
        }
      });

      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _errorMessage = '获取用户信息失败: ${e.toString()}';
      _isLoading = false;
      notifyListeners();
    }
  }

  /// 获取订阅信息
  Future<void> fetchSubscribeInfo() async {
    try {
      _subscribeInfo = await _userService.getSubscribe();

      // 从订阅信息中提取流量数据并更新到 User 对象
      if (_subscribeInfo != null && _user != null) {
        final u = _subscribeInfo!['u'] ?? _subscribeInfo!['upload'] ?? 0;
        final d = _subscribeInfo!['d'] ?? _subscribeInfo!['download'] ?? 0;
        final transferEnable =
            _subscribeInfo!['transfer_enable'] ?? _user!.transferEnable;

        // 创建新的 User 对象，包含流量数据
        _user = User(
          email: _user!.email,
          transferEnable:
              transferEnable is int ? transferEnable : _user!.transferEnable,
          u: u is int ? u : 0,
          d: d is int ? d : 0,
          expiredAt: _user!.expiredAt,
          balance: _user!.balance,
          planId: _user!.planId,
        );
      }

      notifyListeners();
    } catch (e) {
      _errorMessage = '获取订阅信息失败: ${e.toString()}';
      notifyListeners();
    }
  }

  /// 刷新所有信息
  Future<void> refresh() async {
    // 必须先获取用户基本信息，再获取订阅信息（因为订阅信息需要更新到 _user 对象）
    await fetchUserInfo();
    await fetchSubscribeInfo();
    // 套餐信息可以并行获取
    fetchPlans(); // 不等待，异步获取
  }

  /// 获取套餐列表
  Future<void> fetchPlans() async {
    try {
      _plans = await _userService.fetchPlans();
      notifyListeners();
    } catch (e) {
      debugPrint('获取套餐失败: $e');
    }
  }

  /// 购买套餐
  Future<String?> createPayUrl(int planId, String period) async {
    try {
      _isLoading = true;
      _errorMessage = null;
      notifyListeners();

      debugPrint(
          'DEBUG: Request Parameter - plan_id: $planId, type: ${planId.runtimeType}');
      debugPrint('DEBUG: Request Parameter - period: $period');

      final payUrl = await _userService.createOrder(planId, period);
      _isLoading = false;
      notifyListeners();
      return payUrl;
    } catch (e) {
      debugPrint('创建订单失败: $e');
      String msg = '未知错误';

      if (e is DioException && e.response != null) {
        final data = e.response?.data;

        String cleanMsg = '';
        if (data is Map) {
          cleanMsg = data['message'] ?? data.toString();
        } else {
          cleanMsg = data?.toString() ?? e.toString();
        }

        if (cleanMsg.contains('未付款') || cleanMsg.contains('开通中')) {
          msg = '您有未完成的订单，请前往“我的-订阅账单”中支付或取消后再试。';
        } else {
          msg = '服务器提示: $cleanMsg';
        }
      } else {
        msg = e.toString();
      }

      _errorMessage = msg;
      _isLoading = false;
      notifyListeners();
      return null;
    }
  }

  /// 修改密码
  Future<void> changePassword(String oldPassword, String newPassword) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _userService.changePassword(oldPassword, newPassword);
    } on DioException catch (e) {
      if (e.response != null && e.response!.data != null) {
        final data = e.response!.data;
        if (data is Map && data.containsKey('message')) {
          _errorMessage = data['message'];
          throw data['message'];
        }
      }
      throw e.message ?? '修改失败，请检查网络';
    } catch (e) {
      _errorMessage = e.toString();
      rethrow;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
