<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    /**
     * 业务异常构造函数
     * @param array $codeResponse 状态码
     * @param string $info 自定义返回信息，不为空时会替换掉codeResponse 里面的message文字信息
     */
    public function __construct(array $codeResponse, $info = '')
    {
        [$code, $message] = $codeResponse;
        parent::__construct($info ?: $message, $code);
    }
}