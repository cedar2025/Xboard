import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';
import '../dio_client.dart';
import '../../../utils/constants.dart';
import '../../../models/user.dart';

class UserService {
  final DioClient _client;

  UserService(this._client);

  /// 获取用户信息
  Future<User> getUserInfo() async {
    final response = await _client.dio.get(ApiConstants.userInfo);
    return User.fromJson(response.data['data']);
  }

  /// 获取用户订阅信息
  Future<Map<String, dynamic>> getSubscribe() async {
    final response = await _client.dio.get(ApiConstants.getSubscribe);
    return response.data['data'];
  }

  /// 获取订阅配置(sing-box JSON)
  Future<Map<String, dynamic>> getSubscriptionConfig(String token) async {
    final response = await _client.dio.get(
      ApiConstants.subscribe,
      queryParameters: {'token': token},
      options: Options(
        headers: {'User-Agent': 'sing-box/1.10.0'},
      ),
    );
    
    print('DEBUG UserService: response.data type = ${response.data.runtimeType}');
    print('DEBUG UserService: response.data = ${response.data}');
    
    return response.data;
  }

  /// 获取可用套餐列表
  Future<List<dynamic>> fetchPlans() async {
    final response = await _client.dio.get(ApiConstants.planList);
    return response.data['data'];
  }

  /// 创建订单并获取支付链接
  Future<String> createOrder(int planId, String period) async {
    print('DEBUG UserService: Creating order for planId: $planId, period: $period');
    final response = await _client.dio.post(
      ApiConstants.createOrder,
      data: {
        'plan_id': planId,
        'period': period,
      },
    );
    print('DEBUG UserService: API Response: ${response.data}');
    // Xboard 接口返回的是订单号 trade_no
    final tradeNo = response.data['data'];
    
    // 修正：根据用户反馈，前端入口 path 为 /app
    final url = '${ApiConstants.baseUrl}/app#/order/$tradeNo';
    print('DEBUG UserService: Final URL: $url');
    return url;
  }

  /// 取消订单
  Future<void> cancelOrder(String tradeNo) async {
    await _client.dio.post(
      ApiConstants.cancelOrder,
      data: {'trade_no': tradeNo},
    );
  }

  /// 修改密码
  Future<void> changePassword(String oldPassword, String newPassword) async {
    await _client.dio.post(
      ApiConstants.changePassword,
      data: {
        'old_password': oldPassword,
        'new_password': newPassword,
      },
    );
  }

  /// 获取真实邀请码
  Future<String?> getInviteCode() async {
    try {
      final response = await _client.dio.get('/api/v1/user/invite/fetch');
      debugPrint('DEBUG InviteCode: fetch response = ${response.data}');
      final data = response.data['data'];
      
      if (data is Map && data.containsKey('codes')) {
        final codes = data['codes'];
        if (codes is List && codes.isNotEmpty) {
          return codes.first['code'];
        } else if (codes is List && codes.isEmpty) {
          // 当没有生成过邀请码时，主动调用生成一个
          debugPrint('DEBUG InviteCode: no existing codes, generating new one...');
          final saveRes = await _client.dio.post('/api/v1/user/invite/save');
          debugPrint('DEBUG InviteCode: save response = ${saveRes.data}');
          
          // 生成后再拉一次
          final fetchAg = await _client.dio.get('/api/v1/user/invite/fetch');
          final dataAg = fetchAg.data['data'];
          if (dataAg is Map && dataAg.containsKey('codes')) {
            final codesAg = dataAg['codes'];
            if (codesAg is List && codesAg.isNotEmpty) {
              return codesAg.first['code'];
            }
          }
        }
      } else if (data is List && data.isNotEmpty) {
        // 兼容旧版或其他可能的格式
        return data.first['code'];
      }
    } catch (e) {
      debugPrint('获取邀请码失败异常: $e');
    }
    return null;
  }
}

