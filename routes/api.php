<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Bamcom AI CRM
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    // Health & System Info
    Route::get('/health', [HealthController::class, 'index'])->name('api.v1.health');
    Route::get('/version', [HealthController::class, 'version'])->name('api.v1.version');

    // Public Authentication
    Route::post('/auth/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
    });
});
