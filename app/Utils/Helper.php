<?php

namespace App\Utils;

use App\Services\Plugin\HookManager;
use Illuminate\Support\Arr;

class Helper
{
    public static function uuidToBase64($uuid, $length)
    {
        return base64_encode(substr($uuid, 0, $length));
    }

    public static function getServerKey($timestamp, $length)
    {
        return base64_encode(substr(md5($timestamp), 0, $length));
    }

    public static function guid($format = false)
    {
        if (function_exists('com_create_guid') === true) {
            return md5(trim(com_create_guid(), '{}'));
        }
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        if ($format) {
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
        return md5(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)) . '-' . time());
    }

    public static function generateOrderNo(): string
    {
        $randomChar = mt_rand(10000, 99999);
        return date('YmdHms') . substr(microtime(), 2, 6) . $randomChar;
    }

    public static function exchange($from, $to)
    {
        $result = file_get_contents('https://api.exchangerate.host/latest?symbols=' . $to . '&base=' . $from);
        $result = json_decode($result, true);
        return $result['rates'][$to];
    }

    public static function randomChar($len, $special = false)
    {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );

        if ($special) {
            $chars = array_merge($chars, array(
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ));
        }

        $charsLen = count($chars) - 1;
        shuffle($chars);
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];
        }
        return $str;
    }

    public static function wrapIPv6($addr) {
        if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return "[$addr]";
        } else {
            return $addr;
        }
    }

    public static function multiPasswordVerify($algo, $salt, $password, $hash)
    {
        switch($algo) {
            case 'md5': return md5($password) === $hash;
            case 'sha256': return hash('sha256', $password) === $hash;
            case 'md5salt': return md5($password . $salt) === $hash;
            default: return password_verify($password, $hash);
        }
    }

    public static function emailSuffixVerify($email, $suffixs)
    {
        $suffix = preg_split('/@/', $email)[1];
        if (!$suffix) return false;
        if (!is_array($suffixs)) {
            $suffixs = preg_split('/,/', $suffixs);
        }
        if (!in_array($suffix, $suffixs)) return false;
        return true;
    }

    public static function trafficConvert(float $byte)
    {
        $kb = 1024;
        $mb = 1048576;
        $gb = 1073741824;
        if ($byte > $gb) {
            return round($byte / $gb, 2) . ' GB';
        } else if ($byte > $mb) {
            return round($byte / $mb, 2) . ' MB';
        } else if ($byte > $kb) {
            return round($byte / $kb, 2) . ' KB';
        } else if ($byte < 0) {
            return 0;
        } else {
            return round($byte, 2) . ' B';
        }
    }

    public static function getSubscribeUrl(string $token, $subscribeUrl = null)
    {
        $path = route('client.subscribe', ['token' => $token], false);
        if (!$subscribeUrl) {
            $subscribeUrls = explode(',', (string)admin_setting('subscribe_url', ''));
            $subscribeUrl = Arr::random($subscribeUrls);
            $subscribeUrl = self::replaceByPattern($subscribeUrl);
        }

        $subscribeUrl = self::processWildcardPrefix($subscribeUrl);

        $finalUrl = $subscribeUrl ? rtrim($subscribeUrl, '/') . $path : url($path);
        return HookManager::filter('subscribe.url', $finalUrl);
    }

    /**
     * 处理URL通配符前缀替换（例如 http://*. 或 https://*.），自动检测前缀
     *
     * @param string $url 原始URL
     * @return string 处理后的URL
     */
    private static function processWildcardPrefix(string $url): string
    {
        $pattern = '/^(https?:\/\/)\*\./';
        if (preg_match($pattern, $url, $matches)) {
            $prefix = $matches[1];
            $prefixLength = strlen($prefix);
            $pos = strpos($url, '.', $prefixLength);
            if ($pos !== false) {
                return $prefix . Helper::randomChar(5) . substr($url, $pos);
            } else {
                return str_replace($prefix . '*.', $prefix . Helper::randomChar(5) . '.', $url);
            }
        }
        return $url;
    }

    public static function randomPort($range): int {
        $portRange = explode('-', $range);
        return random_int((int)$portRange[0], (int)$portRange[1]);
    }

    public static function base64EncodeUrlSafe($data)
    {
        $encoded = base64_encode($data);
        return str_replace(['+', '/', '='], ['-', '_', ''], $encoded);
    }

    /**
     * 根据规则替换域名中对应的字符串
     *
     * @param string $input 用户输入的字符串
     * @return string 替换后的字符串
     */
    public static function replaceByPattern($input)
    {
        $patterns = [
            '/\[(\d+)-(\d+)\]/' => function ($matches) {
                $min = intval($matches[1]);
                $max = intval($matches[2]);
                if ($min > $max) {
                    list($min, $max) = [$max, $min];
                }
                $randomNumber = rand($min, $max);
                return $randomNumber;
            },
            '/\[uuid\]/' => function () {
                return  self::guid(true);
            }
        ];
        foreach ($patterns as $pattern => $callback) {
            $input = preg_replace_callback($pattern, $callback, $input);
        }
        return $input;
    }

    public static function getIpByDomainName($domain) {
        return gethostbynamel($domain) ?: [];
    }

    public static function getRandFingerprint() {
        $fingerprints = ['chrome', 'firefox', 'safari', 'ios', 'edge', 'qq'];
        return Arr::random($fingerprints);
    }

    public static function encodeURIComponent($str) {
        $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
        return strtr(rawurlencode($str), $revert);
    }
    
}
