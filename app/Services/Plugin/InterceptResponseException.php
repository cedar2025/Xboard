<?php

namespace App\Services\Plugin;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class InterceptResponseException extends Exception
{
    protected Response $response;

    public function __construct(Response $response)
    {
        parent::__construct('Response intercepted');
        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
} 