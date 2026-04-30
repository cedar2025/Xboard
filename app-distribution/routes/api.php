<?php

use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\UpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/update/check', [UpdateController::class, 'check'])->middleware('throttle:120,1');
    Route::post('/telemetry/heartbeat', [TelemetryController::class, 'heartbeat'])->middleware('throttle:60,1');
    Route::post('/telemetry/update-result', [TelemetryController::class, 'updateResult'])->middleware('throttle:60,1');
});
