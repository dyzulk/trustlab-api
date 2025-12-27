<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'ok', 'service' => 'trustlab-api']);
});

use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/register', [AuthController::class, 'register']);

// Social Auth Routes (Manually prefixed with /api to match frontend/callback config, but served via web middleware)
Route::get('/api/auth/{provider}/redirect', [AuthController::class, 'socialRedirect']);
Route::get('/api/auth/{provider}/callback', [AuthController::class, 'socialCallback']);

use App\Http\Controllers\TwoFactorController;

// 2FA Routes (Protected by Sanctum - supports both full auth and temp 2fa auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/api/auth/2fa/enable', [TwoFactorController::class, 'enable']);
    Route::post('/api/auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::delete('/api/auth/2fa/disable', [TwoFactorController::class, 'disable']);
    Route::post('/api/auth/2fa/verify', [TwoFactorController::class, 'verify']);
    Route::get('/api/auth/2fa/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
});
