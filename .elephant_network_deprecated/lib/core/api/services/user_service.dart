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
}
