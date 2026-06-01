<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\ProgressApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:passwords');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:passwords');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->middleware(['ability:read', 'throttle:api-read']);
        Route::patch('profile', [AuthController::class, 'updateProfile'])->middleware(['ability:write', 'throttle:api-write']);
        Route::post('profile/avatar', [AuthController::class, 'updateAvatar'])->middleware(['ability:write', 'throttle:api-write']);
        Route::put('profile/password', [AuthController::class, 'updatePassword'])->middleware(['ability:write', 'throttle:api-write']);
        Route::get('tokens', [ApiTokenController::class, 'index'])->middleware(['ability:tokens', 'throttle:api-tokens']);
        Route::post('tokens', [ApiTokenController::class, 'store'])->middleware(['ability:tokens', 'throttle:api-tokens']);
        Route::delete('tokens/{token}', [ApiTokenController::class, 'destroy'])->middleware(['ability:tokens', 'throttle:api-tokens']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::prefix('v1')->group(function () {
            Route::middleware(['ability:read', 'throttle:api-read'])->group(function () {
                Route::get('dashboard', [ProgressApiController::class, 'dashboard']);
                Route::get('projects', [ProgressApiController::class, 'projects']);
                Route::get('projects/{project}', [ProgressApiController::class, 'project']);
                Route::get('daily-progress', [ProgressApiController::class, 'dailyProgress']);
                Route::get('daily-progress/{dailyProgress}', [ProgressApiController::class, 'showDailyProgress']);
                Route::get('work-logs', [ProgressApiController::class, 'workLogs']);
                Route::get('work-logs/{workLog}', [ProgressApiController::class, 'showWorkLog']);
                Route::get('tasks', [ProgressApiController::class, 'tasks']);
                Route::get('tasks/{task}', [ProgressApiController::class, 'showTask']);
                Route::get('learning', [ProgressApiController::class, 'learning']);
                Route::get('learning/{learning}', [ProgressApiController::class, 'showLearning']);
                Route::get('milestones', [ProgressApiController::class, 'milestones']);
                Route::get('milestones/{milestone}', [ProgressApiController::class, 'showMilestone']);
                Route::get('reports/{period}', [ProgressApiController::class, 'report'])->middleware('ability:reports,read');
                Route::get('search', [ProgressApiController::class, 'search']);
                Route::get('activity', [ProgressApiController::class, 'activity']);
                Route::get('saved-views', [ProgressApiController::class, 'savedViews']);
            });

            Route::middleware(['ability:write', 'throttle:api-write'])->group(function () {
                Route::patch('projects/{project}', [ProgressApiController::class, 'updateProject']);
                Route::post('daily-progress', [ProgressApiController::class, 'storeDailyProgress']);
                Route::patch('daily-progress/{dailyProgress}', [ProgressApiController::class, 'updateDailyProgress']);
                Route::delete('daily-progress/{dailyProgress}', [ProgressApiController::class, 'deleteDailyProgress']);
                Route::post('work-logs', [ProgressApiController::class, 'storeWorkLog']);
                Route::patch('work-logs/{workLog}', [ProgressApiController::class, 'updateWorkLog']);
                Route::delete('work-logs/{workLog}', [ProgressApiController::class, 'deleteWorkLog']);
                Route::post('tasks', [ProgressApiController::class, 'storeTask']);
                Route::patch('tasks/{task}', [ProgressApiController::class, 'updateTask']);
                Route::patch('tasks/{task}/status', [ProgressApiController::class, 'updateTaskStatus']);
                Route::delete('tasks/{task}', [ProgressApiController::class, 'deleteTask']);
                Route::post('learning', [ProgressApiController::class, 'storeLearning']);
                Route::patch('learning/{learning}', [ProgressApiController::class, 'updateLearning']);
                Route::delete('learning/{learning}', [ProgressApiController::class, 'deleteLearning']);
                Route::post('milestones', [ProgressApiController::class, 'storeMilestone']);
                Route::patch('milestones/{milestone}', [ProgressApiController::class, 'updateMilestone']);
                Route::delete('milestones/{milestone}', [ProgressApiController::class, 'deleteMilestone']);
                Route::post('saved-views', [ProgressApiController::class, 'storeSavedView']);
                Route::delete('saved-views/{savedView}', [ProgressApiController::class, 'deleteSavedView']);
                Route::post('references', [ProgressApiController::class, 'storeReference']);
                Route::delete('references/{reference}', [ProgressApiController::class, 'deleteReference']);
            });

            Route::post('quick-capture', [ProgressApiController::class, 'quickCapture'])
                ->middleware(['ability:capture,write', 'throttle:api-capture']);

            Route::get('reports/{period}/export', [ProgressApiController::class, 'exportReport'])
                ->middleware(['ability:reports,read', 'throttle:api-export']);
        });
    });
});

Route::view('/{any?}', 'app')->where('any', '.*');
