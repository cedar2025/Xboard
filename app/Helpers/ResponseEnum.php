<?php

namespace App\Helpers;

class ResponseEnum
{
    // 001 ~ 099 表示系统状态；100 ~ 199 表示授权业务；200 ~ 299 表示用户业务

    /*-------------------------------------------------------------------------------------------*/
    // 100开头的表示 信息提示，这类状态表示临时的响应
    // 100 - 继续
    // 101 - 切换协议

    /*-------------------------------------------------------------------------------------------*/
    // 200表示服务器成功地接受了客户端请求
    const HTTP_OK = [200001, '操作成功'];
    const HTTP_ERROR = [200002, '操作失败'];
    const HTTP_ACTION_COUNT_ERROR = [200302, '操作频繁'];
    const USER_SERVICE_LOGIN_SUCCESS = [200200, '登录成功'];
    const USER_SERVICE_LOGIN_ERROR = [200201, '登录失败'];
    const USER_SERVICE_LOGOUT_SUCCESS = [200202, '退出登录成功'];
    const USER_SERVICE_LOGOUT_ERROR = [200203, '退出登录失败'];
    const USER_SERVICE_REGISTER_SUCCESS = [200104, '注册成功'];
    const USER_SERVICE_REGISTER_ERROR = [200105, '注册失败'];
    const USER_ACCOUNT_REGISTERED = [23001, '账号已注册'];

    /*-------------------------------------------------------------------------------------------*/
    // 300开头的表示服务器重定向,指向的别的地方，客户端浏览器必须采取更多操作来实现请求
    // 302 - 对象已移动。
    // 304 - 未修改。
    // 307 - 临时重定向。

    /*-------------------------------------------------------------------------------------------*/
    // 400开头的表示客户端错误请求错误，请求不到数据，或者找不到等等
    // 400 - 错误的请求
    const CLIENT_NOT_FOUND_HTTP_ERROR = [400001, '请求失败'];
    const CLIENT_PARAMETER_ERROR = [400200, '参数错误'];
    const CLIENT_CREATED_ERROR = [400201, '数据已存在'];
    const CLIENT_DELETED_ERROR = [400202, '数据不存在'];
    // 401 - 访问被拒绝
    const CLIENT_HTTP_UNAUTHORIZED = [401001, '授权失败，请先登录'];
    const CLIENT_HTTP_UNAUTHORIZED_EXPIRED = [401200, '账号信息已过期，请重新登录'];
    const CLIENT_HTTP_UNAUTHORIZED_BLACKLISTED = [401201, '账号在其他设备登录，请重新登录'];
    // 403 - 禁止访问
    // 404 - 没有找到文件或目录
    const CLIENT_NOT_FOUND_ERROR = [404001, '没有找到该页面'];
    // 405 - 用来访问本页面的 HTTP 谓词不被允许（方法不被允许）
    const CLIENT_METHOD_HTTP_TYPE_ERROR = [405001, 'HTTP请求类型错误'];
    // 406 - 客户端浏览器不接受所请求页面的 MIME 类型
    // 407 - 要求进行代理身份验证
    // 412 - 前提条件失败
    // 413 – 请求实体太大
    // 414 - 请求 URI 太长
    // 415 – 不支持的媒体类型
    // 416 – 所请求的范围无法满足
    // 417 – 执行失败
    // 423 – 锁定的错误

    /*-------------------------------------------------------------------------------------------*/
    // 500开头的表示服务器错误，服务器因为代码，或者什么原因终止运行
    // 服务端操作错误码：500 ~ 599 开头，后拼接 3 位
    // 500 - 内部服务器错误
    const SYSTEM_ERROR = [500001, '服务器错误'];
    const SYSTEM_UNAVAILABLE = [500002, '服务器正在维护，暂不可用'];
    const SYSTEM_CACHE_CONFIG_ERROR = [500003, '缓存配置错误'];
    const SYSTEM_CACHE_MISSED_ERROR = [500004, '缓存未命中'];
    const SYSTEM_CONFIG_ERROR = [500005, '系统配置错误'];

    // 业务操作错误码（外部服务或内部服务调用）
    const SERVICE_REGISTER_ERROR = [500101, '注册失败'];
    const SERVICE_LOGIN_ERROR = [500102, '登录失败'];
    const SERVICE_LOGIN_ACCOUNT_ERROR = [500103, '账号或密码错误'];
    const SERVICE_USER_INTEGRAL_ERROR = [500200, '积分不足'];

    //501 - 页眉值指定了未实现的配置
    //502 - Web 服务器用作网关或代理服务器时收到了无效响应
    //503 - 服务不可用。这个错误代码为 IIS 6.0 所专用
    //504 - 网关超时
    //505 - HTTP 版本不受支持
    /*-------------------------------------------------------------------------------------------*/
}