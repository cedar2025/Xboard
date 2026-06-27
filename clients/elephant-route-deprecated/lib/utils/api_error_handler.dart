import 'package:dio/dio.dart';

class ApiErrorHandler {
  static String getMessage(Object error) {
    if (error is DioException) {
      switch (error.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          return '连接超时，请检查网络设置';
        case DioExceptionType.badResponse:
          return _handleBadResponse(error.response);
        case DioExceptionType.cancel:
          return '请求已取消';
        case DioExceptionType.connectionError:
          return '网络连接失败，请检查是否联网';
        default:
          return '网络异常，请稍后重试';
      }
    } else {
      return '发生未知错误: ${error.toString()}'; // Keep raw error shorter or generic
    }
  }

  static String _handleBadResponse(Response? response) {
    if (response == null) {
      return '服务器响应异常';
    }

    // 尝试从返回数据中解析错误信息
    // 假设后端格式为: { "message": "Error detail" } 或 { "error": "Detail" }
    try {
      final data = response.data;
      if (data is Map<String, dynamic>) {
        if (data.containsKey('message')) {
          return data['message'].toString();
        }
        if (data.containsKey('error')) {
          return data['error'].toString();
        }
        // Laravel Validation Errors (422)
        if (response.statusCode == 422 && data.containsKey('errors')) {
          final errors = data['errors'];
          if (errors is Map) {
            // Return the first validation error found
            final firstKey = errors.keys.first;
            final firstError = errors[firstKey];
            if (firstError is List && firstError.isNotEmpty) {
              return firstError.first.toString();
            }
          }
        }
      } else if (data is String) {
        // If response is just a string, it might be the error message
        if (data.length < 100) return data;
      }
    } catch (e) {
      // JSON parsing failed
    }

    // Fallback based on status code
    switch (response.statusCode) {
      case 400:
        return '请求参数错误 (400)';
      case 401:
        return '用户名或密码错误';
      case 403:
        return '访问被拒绝 (403)';
      case 404:
        return '请求的资源不存在 (404)';
      case 422:
        return '数据验证失败 (422)';
      case 500:
        return '服务器内部错误 (500)';
      case 502:
        return '网关错误 (502)';
      case 503:
        return '服务不可用 (503)';
      default:
        return '请求失败 (${response.statusCode})';
    }
  }
}
