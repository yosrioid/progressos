<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\ConfigurationController;
use App\Http\Controllers\Api\QuoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // User management
    Route::get('users', [AdminUserController::class, 'index']);
    Route::post('users', [AdminUserController::class, 'store']);
    Route::patch('users/{user}', [AdminUserController::class, 'update']);
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
    Route::post('users/{user}/disable', [AdminUserController::class, 'disable']);
    Route::post('users/{user}/enable', [AdminUserController::class, 'enable']);
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);

    // Global configuration (admin-only write + read)
    Route::get('configuration', [ConfigurationController::class, 'show']);
    Route::put('configuration/settings', [ConfigurationController::class, 'updateSettings']);
    Route::put('configuration/auth', [ConfigurationController::class, 'updateAuthConfig']);
    Route::put('configuration/mail', [ConfigurationController::class, 'updateMailConfig']);
    Route::put('configuration/backup-connection', [ConfigurationController::class, 'updateConnection']);
    Route::post('configuration/backup-connection/verify', [ConfigurationController::class, 'verifyConnection']);
    Route::post('configuration/backup-syncs', [ConfigurationController::class, 'storeSync']);
    Route::patch('configuration/backup-syncs/{sync}', [ConfigurationController::class, 'updateSync']);
    Route::delete('configuration/backup-syncs/{sync}', [ConfigurationController::class, 'destroySync']);
    Route::post('configuration/backup-syncs/{sync}/run', [ConfigurationController::class, 'runSync']);
    Route::put('configuration/quote', [QuoteController::class, 'saveConfig']);

    // AI configuration (admin-only)
    Route::get('configuration/ai', [ConfigurationController::class, 'getAiConfig']);
    Route::put('configuration/ai', [ConfigurationController::class, 'saveAiConfig']);
    Route::put('configuration/feature-providers', [ConfigurationController::class, 'saveFeatureProviders']);
    Route::get('configuration/ai/usage', [ConfigurationController::class, 'getAiUsage']);
});
