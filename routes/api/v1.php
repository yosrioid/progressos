<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\CaptureController;
use App\Http\Controllers\Api\ConfigurationController;
use App\Http\Controllers\Api\DailyProgressController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GameController;
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

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::middleware(['ability:read', 'throttle:api-read'])->group(function () {
        Route::get('dashboard', DashboardController::class);
        Route::get('reports/{period}', [ReportController::class, 'show'])->middleware('ability:reports,read');
        Route::get('reports/{period}/snapshots', [ReportController::class, 'snapshots'])->middleware('ability:reports,read');
        Route::get('search', SearchController::class);
        Route::get('activity', ActivityController::class);
        Route::get('configuration', [ConfigurationController::class, 'show']);
        Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);
        Route::apiResource('daily-progress', DailyProgressController::class)->only(['index', 'show'])->parameters(['daily-progress' => 'dailyProgress']);
        Route::apiResource('work-logs', WorkLogController::class)->only(['index', 'show'])->parameters(['work-logs' => 'workLog']);
        Route::apiResource('tasks', TaskController::class)->only(['index', 'show']);
        Route::apiResource('learning', LearningController::class)->only(['index', 'show']);
        Route::apiResource('milestones', MilestoneController::class)->only(['index', 'show']);
        Route::apiResource('saved-views', SavedViewController::class)->only(['index'])->parameters(['saved-views' => 'savedView']);
    });

    Route::middleware(['ability:write', 'throttle:api-write'])->group(function () {
        Route::apiResource('projects', ProjectController::class)->only(['update']);
        Route::apiResource('daily-progress', DailyProgressController::class)->only(['store', 'update', 'destroy'])->parameters(['daily-progress' => 'dailyProgress']);
        Route::apiResource('work-logs', WorkLogController::class)->only(['store', 'update', 'destroy'])->parameters(['work-logs' => 'workLog']);
        Route::apiResource('tasks', TaskController::class)->only(['store', 'update', 'destroy']);
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::apiResource('learning', LearningController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('milestones', MilestoneController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('saved-views', SavedViewController::class)->only(['store', 'destroy'])->parameters(['saved-views' => 'savedView']);
        Route::apiResource('references', ReferenceController::class)->only(['store', 'destroy']);
        Route::put('configuration/settings', [ConfigurationController::class, 'updateSettings']);
        Route::put('configuration/backup-connection', [ConfigurationController::class, 'updateConnection']);
        Route::post('configuration/backup-connection/verify', [ConfigurationController::class, 'verifyConnection']);
        Route::post('configuration/backup-syncs', [ConfigurationController::class, 'storeSync']);
        Route::patch('configuration/backup-syncs/{sync}', [ConfigurationController::class, 'updateSync']);
        Route::delete('configuration/backup-syncs/{sync}', [ConfigurationController::class, 'destroySync']);
        Route::post('configuration/backup-syncs/{sync}/run', [ConfigurationController::class, 'runSync']);
    });

    Route::post('quick-capture', CaptureController::class)
        ->middleware(['ability:capture,write', 'throttle:api-capture']);

    Route::get('reports/{period}/export', [ReportController::class, 'export'])
        ->middleware(['ability:reports,read', 'throttle:api-export']);

    Route::post('reports/{period}/snapshots', [ReportController::class, 'storeSnapshot'])
        ->middleware(['ability:reports,write', 'throttle:api-write']);

    Route::put('configuration/auth', [ConfigurationController::class, 'updateAuthConfig']);
    Route::put('configuration/mail', [ConfigurationController::class, 'updateMailConfig']);

    Route::prefix('games/minesweeper')->group(function () {
        Route::get('active', [GameController::class, 'activeMinesweeperSession']);
        Route::get('daily', [GameController::class, 'minesweeperDailyStatus']);
        Route::get('records', [GameController::class, 'minesweeperRecords']);
        Route::post('sessions', [GameController::class, 'startMinesweeperSession']);
        Route::patch('sessions/{session}', [GameController::class, 'saveProgress']);
        Route::post('sessions/{session}/complete', [GameController::class, 'completeMinesweeperSession']);
    });

    Route::prefix('games/sudoku')->group(function () {
        Route::get('active', [GameController::class, 'activeSession']);
        Route::get('daily', [GameController::class, 'dailyStatus']);
        Route::get('records', [GameController::class, 'records']);
        Route::post('sessions', [GameController::class, 'startSession']);
        Route::patch('sessions/{session}', [GameController::class, 'saveProgress']);
        Route::post('sessions/{session}/complete', [GameController::class, 'completeSession']);
    });
});
