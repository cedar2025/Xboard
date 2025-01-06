<?php

namespace App\Contracts;

interface PaymentInterface
{
    public function form(): array;
    public function pay($order): array;
    public function notify($params);
}
