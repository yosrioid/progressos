<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\ProgressApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::post('profile/avatar', [AuthController::class, 'updateAvatar']);
        Route::put('profile/password', [AuthController::class, 'updatePassword']);
        Route::get('tokens', [ApiTokenController::class, 'index']);
        Route::post('tokens', [ApiTokenController::class, 'store']);
        Route::delete('tokens/{token}', [ApiTokenController::class, 'destroy']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::prefix('v1')->group(function () {
            Route::get('dashboard', [ProgressApiController::class, 'dashboard']);
            Route::get('projects', [ProgressApiController::class, 'projects']);
            Route::get('projects/{project}', [ProgressApiController::class, 'project']);
            Route::patch('projects/{project}', [ProgressApiController::class, 'updateProject']);
            Route::post('quick-capture', [ProgressApiController::class, 'quickCapture']);
            Route::get('daily-progress', [ProgressApiController::class, 'dailyProgress']);
            Route::post('daily-progress', [ProgressApiController::class, 'storeDailyProgress']);
            Route::get('daily-progress/{dailyProgress}', [ProgressApiController::class, 'showDailyProgress']);
            Route::patch('daily-progress/{dailyProgress}', [ProgressApiController::class, 'updateDailyProgress']);
            Route::delete('daily-progress/{dailyProgress}', [ProgressApiController::class, 'deleteDailyProgress']);
            Route::get('work-logs', [ProgressApiController::class, 'workLogs']);
            Route::post('work-logs', [ProgressApiController::class, 'storeWorkLog']);
            Route::get('work-logs/{workLog}', [ProgressApiController::class, 'showWorkLog']);
            Route::patch('work-logs/{workLog}', [ProgressApiController::class, 'updateWorkLog']);
            Route::delete('work-logs/{workLog}', [ProgressApiController::class, 'deleteWorkLog']);
            Route::get('tasks', [ProgressApiController::class, 'tasks']);
            Route::post('tasks', [ProgressApiController::class, 'storeTask']);
            Route::get('tasks/{task}', [ProgressApiController::class, 'showTask']);
            Route::patch('tasks/{task}', [ProgressApiController::class, 'updateTask']);
            Route::patch('tasks/{task}/status', [ProgressApiController::class, 'updateTaskStatus']);
            Route::delete('tasks/{task}', [ProgressApiController::class, 'deleteTask']);
            Route::get('learning', [ProgressApiController::class, 'learning']);
            Route::post('learning', [ProgressApiController::class, 'storeLearning']);
            Route::get('learning/{learning}', [ProgressApiController::class, 'showLearning']);
            Route::patch('learning/{learning}', [ProgressApiController::class, 'updateLearning']);
            Route::delete('learning/{learning}', [ProgressApiController::class, 'deleteLearning']);
            Route::get('milestones', [ProgressApiController::class, 'milestones']);
            Route::post('milestones', [ProgressApiController::class, 'storeMilestone']);
            Route::get('milestones/{milestone}', [ProgressApiController::class, 'showMilestone']);
            Route::patch('milestones/{milestone}', [ProgressApiController::class, 'updateMilestone']);
            Route::delete('milestones/{milestone}', [ProgressApiController::class, 'deleteMilestone']);
            Route::get('reports/{period}', [ProgressApiController::class, 'report']);
            Route::get('reports/{period}/export', [ProgressApiController::class, 'exportReport']);
            Route::get('search', [ProgressApiController::class, 'search']);
            Route::get('activity', [ProgressApiController::class, 'activity']);
            Route::get('saved-views', [ProgressApiController::class, 'savedViews']);
            Route::post('saved-views', [ProgressApiController::class, 'storeSavedView']);
            Route::delete('saved-views/{savedView}', [ProgressApiController::class, 'deleteSavedView']);
            Route::post('references', [ProgressApiController::class, 'storeReference']);
            Route::delete('references/{reference}', [ProgressApiController::class, 'deleteReference']);
        });
    });
});

Route::view('/{any?}', 'app')->where('any', '.*');
