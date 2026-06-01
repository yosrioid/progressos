<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaptureController;
use App\Http\Controllers\Api\DailyProgressController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavedViewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WorkLogController;
use Illuminate\Support\Facades\Route;

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
            Route::get('dashboard', DashboardController::class);
            Route::get('projects', [ProjectController::class, 'index']);
            Route::get('projects/{project}', [ProjectController::class, 'show']);
            Route::get('daily-progress', [DailyProgressController::class, 'index']);
            Route::get('daily-progress/{dailyProgress}', [DailyProgressController::class, 'show']);
            Route::get('work-logs', [WorkLogController::class, 'index']);
            Route::get('work-logs/{workLog}', [WorkLogController::class, 'show']);
            Route::get('tasks', [TaskController::class, 'index']);
            Route::get('tasks/{task}', [TaskController::class, 'show']);
            Route::get('learning', [LearningController::class, 'index']);
            Route::get('learning/{learning}', [LearningController::class, 'show']);
            Route::get('milestones', [MilestoneController::class, 'index']);
            Route::get('milestones/{milestone}', [MilestoneController::class, 'show']);
            Route::get('reports/{period}', [ReportController::class, 'show'])->middleware('ability:reports,read');
            Route::get('search', SearchController::class);
            Route::get('activity', ActivityController::class);
            Route::get('saved-views', [SavedViewController::class, 'index']);
        });

        Route::middleware(['ability:write', 'throttle:api-write'])->group(function () {
            Route::patch('projects/{project}', [ProjectController::class, 'update']);
            Route::post('daily-progress', [DailyProgressController::class, 'store']);
            Route::patch('daily-progress/{dailyProgress}', [DailyProgressController::class, 'update']);
            Route::delete('daily-progress/{dailyProgress}', [DailyProgressController::class, 'destroy']);
            Route::post('work-logs', [WorkLogController::class, 'store']);
            Route::patch('work-logs/{workLog}', [WorkLogController::class, 'update']);
            Route::delete('work-logs/{workLog}', [WorkLogController::class, 'destroy']);
            Route::post('tasks', [TaskController::class, 'store']);
            Route::patch('tasks/{task}', [TaskController::class, 'update']);
            Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
            Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
            Route::post('learning', [LearningController::class, 'store']);
            Route::patch('learning/{learning}', [LearningController::class, 'update']);
            Route::delete('learning/{learning}', [LearningController::class, 'destroy']);
            Route::post('milestones', [MilestoneController::class, 'store']);
            Route::patch('milestones/{milestone}', [MilestoneController::class, 'update']);
            Route::delete('milestones/{milestone}', [MilestoneController::class, 'destroy']);
            Route::post('saved-views', [SavedViewController::class, 'store']);
            Route::delete('saved-views/{savedView}', [SavedViewController::class, 'destroy']);
            Route::post('references', [ReferenceController::class, 'store']);
            Route::delete('references/{reference}', [ReferenceController::class, 'destroy']);
        });

        Route::post('quick-capture', CaptureController::class)
            ->middleware(['ability:capture,write', 'throttle:api-capture']);

        Route::get('reports/{period}/export', [ReportController::class, 'export'])
            ->middleware(['ability:reports,read', 'throttle:api-export']);
    });
});
