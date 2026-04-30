<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    protected $levels = [];
    protected $dontReport = [];
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];
}
