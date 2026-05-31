<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProgressApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
    });

    Route::middleware('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::put('profile/password', [AuthController::class, 'updatePassword']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::prefix('v1')->group(function () {
            Route::get('dashboard', [ProgressApiController::class, 'dashboard']);
            Route::get('projects', [ProgressApiController::class, 'projects']);
            Route::get('projects/{project}', [ProgressApiController::class, 'project']);
            Route::post('quick-capture', [ProgressApiController::class, 'quickCapture']);
            Route::get('daily-progress', [ProgressApiController::class, 'dailyProgress']);
            Route::post('daily-progress', [ProgressApiController::class, 'storeDailyProgress']);
            Route::get('work-logs', [ProgressApiController::class, 'workLogs']);
            Route::post('work-logs', [ProgressApiController::class, 'storeWorkLog']);
            Route::get('tasks', [ProgressApiController::class, 'tasks']);
            Route::post('tasks', [ProgressApiController::class, 'storeTask']);
            Route::patch('tasks/{task}/status', [ProgressApiController::class, 'updateTaskStatus']);
            Route::delete('tasks/{task}', [ProgressApiController::class, 'deleteTask']);
            Route::get('learning', [ProgressApiController::class, 'learning']);
            Route::post('learning', [ProgressApiController::class, 'storeLearning']);
            Route::get('milestones', [ProgressApiController::class, 'milestones']);
            Route::post('milestones', [ProgressApiController::class, 'storeMilestone']);
            Route::get('reports/{period}', [ProgressApiController::class, 'report']);
            Route::get('reports/{period}/export', [ProgressApiController::class, 'exportReport']);
            Route::get('search', [ProgressApiController::class, 'search']);
        });
    });
});

Route::view('/{any?}', 'app')->where('any', '.*');
