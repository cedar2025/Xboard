<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\ArtifactDownloadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.post');
});

Route::get('/download/{artifact}', [ArtifactDownloadController::class, 'download'])
    ->middleware('throttle:60,1')
    ->name('download.artifact');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');

    Route::resource('apps', AppController::class)->except(['show', 'destroy']);
    Route::resource('versions', VersionController::class)->except(['show']);
    Route::post('/versions/{version}/publish', [VersionController::class, 'publish'])->name('versions.publish');
    Route::post('/versions/{version}/disable', [VersionController::class, 'disable'])->name('versions.disable');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/logs/downloads', [LogController::class, 'downloads'])->name('logs.downloads');
    Route::get('/logs/audits', [LogController::class, 'audits'])
        ->middleware('role:owner,admin')
        ->name('logs.audits');
});
