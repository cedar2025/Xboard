<?php

namespace App\Contracts;

interface ProtocolInterface
{
    public function getFlags(): array;
    /**
     * 处理并生成配置
     */
    public function handle();
} 