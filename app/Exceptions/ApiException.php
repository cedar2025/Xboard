<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected $code; // 错误码
    protected $message; // 错误消息
    protected $errors; // 全部错误信息

    public function __construct($message = null, $code = 400, $errors = null)
    {
        $this->message = $message;
        $this->code = $code;
        $this->errors = $errors;
    }
    public function errors(){
        return $this->errors;
    }

}
