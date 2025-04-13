<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;

class CheckForMaintenanceMode extends PreventRequestsDuringMaintenance
{
    /**
     * 维护模式白名单URI
     * @var array<int, string>
     */
    protected $except = [
        // 示例：
        // '/api/health-check',
        // '/status'
    ];
}
