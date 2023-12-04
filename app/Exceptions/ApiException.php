<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected $code; // 错误码
    protected $message; // 错误消息
    protected $errors; // 全部错误信息

    public function __construct($code = 400, $message = null, $errors = null)
    {
        $this->message = $message;
        $this->code = $code;
        $this->errors = $errors;
    }
    public function errors(){
        return $this->errors;
    }

}
