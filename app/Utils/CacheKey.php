<?php

namespace App\Utils;

class CacheKey
{
    const KEYS = [
        'EMAIL_VERIFY_CODE' => '邮箱验证码',
        'LAST_SEND_EMAIL_VERIFY_TIMESTAMP' => '最后一次发送邮箱验证码时间',
        'SERVER_VMESS_ONLINE_USER' => '节点在线用户',
        'MULTI_SERVER_VMESS_ONLINE_USER' => '节点多服务器在线用户',
        'SERVER_VMESS_LAST_CHECK_AT' => '节点最后检查时间',
        'SERVER_VMESS_LAST_PUSH_AT' => '节点最后推送时间',
        'SERVER_TROJAN_ONLINE_USER' => 'trojan节点在线用户',
        'MULTI_SERVER_TROJAN_ONLINE_USER' => 'trojan节点多服务器在线用户',
        'SERVER_TROJAN_LAST_CHECK_AT' => 'trojan节点最后检查时间',
        'SERVER_TROJAN_LAST_PUSH_AT' => 'trojan节点最后推送时间',
        'SERVER_SHADOWSOCKS_ONLINE_USER' => 'ss节点在线用户',
        'MULTI_SERVER_SHADOWSOCKS_ONLINE_USER' => 'ss节点多服务器在线用户',
        'SERVER_SHADOWSOCKS_LAST_CHECK_AT' => 'ss节点最后检查时间',
        'SERVER_SHADOWSOCKS_LAST_PUSH_AT' => 'ss节点最后推送时间',
        'SERVER_HYSTERIA_ONLINE_USER' => 'hysteria节点在线用户',
        'MULTI_SERVER_HYSTERIA_ONLINE_USER' => 'hysteria节点多服务器在线用户',
        'SERVER_HYSTERIA_LAST_CHECK_AT' => 'hysteria节点最后检查时间',
        'SERVER_HYSTERIA_LAST_PUSH_AT' => 'hysteria节点最后推送时间',
        'SERVER_VLESS_ONLINE_USER' => 'vless节点在线用户',
        'MULTI_SERVER_VLESS_ONLINE_USER' => 'vless节点多服务器在线用户',
        'SERVER_VLESS_LAST_CHECK_AT' => 'vless节点最后检查时间',
        'SERVER_VLESS_LAST_PUSH_AT' => 'vless节点最后推送时间',
        'SERVER_TUIC_ONLINE_USER' => 'TUIC节点在线用户',
        'MULTI_SERVER_TUIC_ONLINE_USER' => 'TUIC节点多服务器在线用户',
        'SERVER_TUIC_LAST_CHECK_AT' => 'TUIC节点最后检查时间',
        'SERVER_TUIC_LAST_PUSH_AT' => 'TUIC节点最后推送时间',
        'SERVER_ANYTLS_ONLINE_USER' => 'ANYTLS节点在线用户',
        'MULTI_SERVER_ANYTLS_ONLINE_USER' => 'ANYTLS节点多服务器在线用户',
        'SERVER_ANYTLS_LAST_CHECK_AT' => 'ANYTLS节点最后检查时间',
        'SERVER_ANYTLS_LAST_PUSH_AT' => 'ANYTLS节点最后推送时间',
        'SERVER_SOCKS_ONLINE_USER' => 'socks节点在线用户',
        'MULTI_SERVER_SOCKS_ONLINE_USER' => 'socks节点多服务器在线用户',
        'SERVER_SOCKS_LAST_CHECK_AT' => 'socks节点最后检查时间',
        'SERVER_SOCKS_LAST_PUSH_AT' => 'socks节点最后推送时间',
        'SERVER_NAIVE_ONLINE_USER' => 'naive节点在线用户',
        'MULTI_SERVER_NAIVE_ONLINE_USER' => 'naive节点多服务器在线用户',
        'SERVER_NAIVE_LAST_CHECK_AT' => 'naive节点最后检查时间',
        'SERVER_NAIVE_LAST_PUSH_AT' => 'naive节点最后推送时间',
        'SERVER_HTTP_ONLINE_USER' => 'http节点在线用户',
        'MULTI_SERVER_HTTP_ONLINE_USER' => 'http节点多服务器在线用户',
        'SERVER_HTTP_LAST_CHECK_AT' => 'http节点最后检查时间',
        'SERVER_HTTP_LAST_PUSH_AT' => 'http节点最后推送时间',
        'SERVER_MIERU_ONLINE_USER' => 'mieru节点在线用户',
        'MULTI_SERVER_MIERU_ONLINE_USER' => 'mieru节点多服务器在线用户',
        'SERVER_MIERU_LAST_CHECK_AT' => 'mieru节点最后检查时间',
        'SERVER_MIERU_LAST_PUSH_AT' => 'mieru节点最后推送时间',
        'TEMP_TOKEN' => '临时令牌',
        'LAST_SEND_EMAIL_REMIND_TRAFFIC' => '最后发送流量邮件提醒',
        'SCHEDULE_LAST_CHECK_AT' => '计划任务最后检查时间',
        'REGISTER_IP_RATE_LIMIT' => '注册频率限制',
        'LAST_SEND_LOGIN_WITH_MAIL_LINK_TIMESTAMP' => '最后一次发送登入链接时间',
        'PASSWORD_ERROR_LIMIT' => '密码错误次数限制',
        'USER_SESSIONS' => '用户session',
        'FORGET_REQUEST_LIMIT' => '找回密码次数限制'
    ];

    public static function get(string $key, $uniqueValue)
    {
        if (!in_array($key, array_keys(self::KEYS))) {
            abort(500, 'key is not in cache key list');
        }
        return $key . '_' . $uniqueValue;
    }
}
