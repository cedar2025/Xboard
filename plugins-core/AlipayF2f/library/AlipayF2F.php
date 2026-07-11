<?php
namespace Plugin\AlipayF2f\library;

use Illuminate\Support\Facades\Http;

class AlipayF2F
{
    private $appId;
    private $privateKey;
    private $alipayPublicKey;
    private $signType = 'RSA2';
    public $bizContent;
    public $method;
    public $notifyUrl;
    public $response;

    public function __construct()
    {
    }

    public function verify($data): bool
    {
        if (is_string($data)) {
            parse_str($data, $data);
        }
        if (empty($data['sign'])) {
            return false;
        }
        $sign = $data['sign'];
        $decodedSign = base64_decode($sign, true);
        if ($decodedSign === false) {
            return false;
        }
        unset($data['sign']);
        unset($data['sign_type']);
        ksort($data);
        $data = $this->buildQuery($data);
        $publicKey = $this->loadPublicKey();
        if ("RSA2" == $this->signType) {
            $result = (openssl_verify($data, $decodedSign, $publicKey, OPENSSL_ALGO_SHA256) === 1);
        } else {
            $result = (openssl_verify($data, $decodedSign, $publicKey) === 1);
        }
        return $result;
    }

    public function setBizContent($bizContent = [])
    {
        $this->bizContent = json_encode($bizContent);
    }

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    public function setPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;
    }

    public function setAlipayPublicKey($alipayPublicKey)
    {
        $this->alipayPublicKey = $alipayPublicKey;
    }

    public function setNotifyUrl($url)
    {
        $this->notifyUrl = $url;
    }

    public function send()
    {
        $response = Http::get('https://openapi.alipay.com/gateway.do', $this->buildParam())->json();
        $resKey = str_replace('.', '_', $this->method) . '_response';
        if (!isset($response[$resKey]))
            throw new \Exception('从支付宝请求失败');
        $response = $response[$resKey];
        if ($response['msg'] !== 'Success')
            throw new \Exception($response['sub_msg']);
        $this->response = $response;
    }

    public function getQrCodeUrl()
    {
        $response = $this->response;
        if (!isset($response['qr_code']))
            throw new \Exception('获取付款二维码失败');
        return $response['qr_code'];
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function buildParam(): array
    {
        $params = [
            'app_id' => $this->appId,
            'method' => $this->method,
            'charset' => 'UTF-8',
            'sign_type' => $this->signType,
            'timestamp' => date('Y-m-d H:i:s'),
            'biz_content' => $this->bizContent,
            'version' => '1.0',
            '_input_charset' => 'UTF-8'
        ];
        if ($this->notifyUrl)
            $params['notify_url'] = $this->notifyUrl;
        ksort($params);
        $params['sign'] = $this->buildSign($this->buildQuery($params));
        return $params;
    }

    public function buildQuery($query)
    {
        if (!$query) {
            throw new \Exception('参数构造错误');
        }
        //将要 参数 排序
        ksort($query);

        //重新组装参数
        $params = array();
        foreach ($query as $key => $value) {
            $params[] = $key . '=' . $value;
        }
        $data = implode('&', $params);
        return $data;
    }

    private function buildSign(string $signData): string
    {
        $privateKey = $this->loadPrivateKey();
        $signature = '';
        if ("RSA2" == $this->signType) {
            $signed = openssl_sign($signData, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        } else {
            $signed = openssl_sign($signData, $signature, $privateKey, OPENSSL_ALGO_SHA1);
        }
        if (!$signed) {
            throw new \RuntimeException('支付宝请求签名失败，请检查应用私钥');
        }
        return base64_encode($signature);
    }

    private function loadPrivateKey()
    {
        return $this->loadKey($this->privateKey, ['PRIVATE KEY', 'RSA PRIVATE KEY'], true);
    }

    private function loadPublicKey()
    {
        return $this->loadKey($this->alipayPublicKey, ['PUBLIC KEY', 'RSA PUBLIC KEY'], false);
    }

    private function loadKey($value, array $labels, bool $private)
    {
        $value = trim((string) $value);
        $body = null;
        foreach ($labels as $label) {
            $quotedLabel = preg_quote($label, '/');
            $pattern = '/\A-----BEGIN ' . $quotedLabel . '-----\s*'
                . '(.*?)\s*-----END ' . $quotedLabel . '-----\z/s';
            if (preg_match($pattern, $value, $matches) === 1) {
                $body = preg_replace('/\s+/', '', $matches[1]);
                break;
            }
        }
        if ($body === null) {
            if (strpos($value, '-----') !== false) {
                throw new \RuntimeException($private ? '支付宝应用私钥格式无效' : '支付宝公钥格式无效');
            }
            $body = preg_replace('/\s+/', '', $value);
        }
        if (!$body || base64_decode($body, true) === false) {
            throw new \RuntimeException($private ? '支付宝应用私钥格式无效' : '支付宝公钥格式无效');
        }
        foreach ($labels as $label) {
            $pem = "-----BEGIN {$label}-----\n"
                . wordwrap($body, 64, "\n", true)
                . "\n-----END {$label}-----";
            $key = $private ? openssl_pkey_get_private($pem) : openssl_pkey_get_public($pem);
            if ($key !== false) {
                return $key;
            }
        }
        throw new \RuntimeException($private ? '支付宝应用私钥无法解析' : '支付宝公钥无法解析');
    }
}
