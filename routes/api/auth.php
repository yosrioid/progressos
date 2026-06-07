<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:passwords');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:passwords');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me'])->middleware(['ability:read', 'throttle:api-read']);
    Route::patch('profile', [AuthController::class, 'updateProfile'])->middleware(['ability:write', 'throttle:api-write']);
    Route::post('profile/avatar', [AuthController::class, 'updateAvatar'])->middleware(['ability:write', 'throttle:api-write']);
    Route::put('profile/password', [AuthController::class, 'updatePassword'])->middleware(['ability:write', 'throttle:api-write']);
    Route::post('logout', [AuthController::class, 'logout']);
});
