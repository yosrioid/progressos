<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\CaptureController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConfigurationController;
use App\Http\Controllers\Api\DailyProgressController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocController;
use App\Http\Controllers\Api\DocFileController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HabitController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\MoneyController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavedViewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StandupController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TodoListController;
use App\Http\Controllers\Api\WorkLogController;
use Illuminate\Support\Facades\Route;

// Routes accessible by both admin and regular users (with different data scoping)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Configuration routes - accessible by admin too
    Route::middleware(['ability:read', 'throttle:api-read'])->group(function () {
        Route::get('configuration', [ConfigurationController::class, 'show']);
        Route::post('ai/quota/check', [ConfigurationController::class, 'checkQuota']);
        Route::get('ai/config', [ConfigurationController::class, 'getAiConfig']);
        Route::get('quote/config', [QuoteController::class, 'configPayload']);
        Route::get('quote/usage', [QuoteController::class, 'usage']);
    });

    Route::middleware(['throttle:api-write'])->group(function () {
        Route::put('quote/config', [QuoteController::class, 'saveConfig']);
        // AI configuration write removed — admin-only via /api/admin/configuration/ai
    });
});

// Routes only for regular users (not admin)
Route::middleware(['auth:sanctum', 'not.admin'])->prefix('v1')->group(function () {
    Route::middleware(['ability:read', 'throttle:api-read'])->group(function () {
        Route::get('dashboard', DashboardController::class);
        Route::get('analytics', AnalyticsController::class);
        Route::get('standup', StandupController::class);
        Route::get('habits', [HabitController::class, 'index']);
        Route::get('goals', [GoalController::class, 'index']);
        Route::get('goals/{goal}', [GoalController::class, 'show']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('reports/{period}', [ReportController::class, 'show'])->middleware('ability:reports,read');
        Route::get('reports/{period}/snapshots', [ReportController::class, 'snapshots'])->middleware('ability:reports,read');
        Route::get('search', SearchController::class);
        Route::get('activity', ActivityController::class);
        Route::get('activity/summary', [ActivityController::class, 'summary']);
        Route::get('chat-sessions', [ChatController::class, 'index']);
        Route::get('chat-sessions/{chatSession}', [ChatController::class, 'show']);
        Route::get('quote/daily', [QuoteController::class, 'daily']);
        Route::get('quote/usage', [QuoteController::class, 'usage']);
        Route::get('journals/profile', [JournalController::class, 'profile']);
        Route::apiResource('journals', JournalController::class)->only(['index', 'show']);
        Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);
        Route::apiResource('daily-progress', DailyProgressController::class)->only(['index', 'show'])->parameters(['daily-progress' => 'dailyProgress']);
        Route::apiResource('work-logs', WorkLogController::class)->only(['index', 'show'])->parameters(['work-logs' => 'workLog']);
        Route::get('tasks/kanban', [TaskController::class, 'kanban']);
        Route::get('tasks/overdue-count', [TaskController::class, 'overdueCount']);
        Route::apiResource('tasks', TaskController::class)->only(['index', 'show']);
        Route::get('learning/stats', [LearningController::class, 'stats']);
        Route::get('learning/heatmap', [LearningController::class, 'heatmap']);
        Route::apiResource('learning', LearningController::class)->only(['index', 'show']);
        Route::apiResource('milestones', MilestoneController::class)->only(['index', 'show']);
        Route::get('milestones/{milestone}/history', [MilestoneController::class, 'history']);
        Route::apiResource('saved-views', SavedViewController::class)->only(['index'])->parameters(['saved-views' => 'savedView']);
        Route::get('docs/categories', [DocController::class, 'categories']);
        Route::apiResource('docs', DocController::class)->only(['index', 'show']);
        Route::get('lists', [TodoListController::class, 'index']);
        Route::get('lists/{todoList}', [TodoListController::class, 'show']);
        Route::get('money/months', [MoneyController::class, 'months']);
        Route::get('money/month/{month}', [MoneyController::class, 'month']);
        Route::get('bills', [BillController::class, 'index']);
        Route::get('bills/month/{month}', [BillController::class, 'month']);
        Route::get('bills/annual/{year}', [BillController::class, 'annual']);
        Route::get('bills/{bill}/history', [BillController::class, 'history']);
        Route::get('doc-files/{docFile}', [DocFileController::class, 'show']);
    });

    Route::middleware(['ability:write', 'throttle:api-write'])->group(function () {
        Route::post('chat-sessions', [ChatController::class, 'store']);
        Route::delete('chat-sessions/{chatSession}', [ChatController::class, 'destroy']);
        Route::post('chat-sessions/{chatSession}/messages', [ChatController::class, 'sendMessage']);
        Route::apiResource('journals', JournalController::class)->only(['store', 'update', 'destroy']);
        Route::post('journals/{journal}/analyze', [JournalController::class, 'analyze']);
        Route::post('habits', [HabitController::class, 'store']);
        Route::patch('habits/{habit}', [HabitController::class, 'update']);
        Route::delete('habits/{habit}', [HabitController::class, 'destroy']);
        Route::post('habits/{habit}/log', [HabitController::class, 'log']);
        Route::delete('habits/{habit}/log', [HabitController::class, 'unlog']);
        Route::post('habits/reorder', [HabitController::class, 'reorder']);
        Route::post('goals', [GoalController::class, 'store']);
        Route::patch('goals/{goal}', [GoalController::class, 'update']);
        Route::delete('goals/{goal}', [GoalController::class, 'destroy']);
        Route::post('goals/{goal}/key-results', [GoalController::class, 'storeKeyResult']);
        Route::patch('goals/{goal}/key-results/{keyResult}', [GoalController::class, 'updateKeyResult']);
        Route::delete('goals/{goal}/key-results/{keyResult}', [GoalController::class, 'destroyKeyResult']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::delete('notifications', [NotificationController::class, 'clearAll']);
        Route::apiResource('projects', ProjectController::class)->only(['update']);
        Route::apiResource('daily-progress', DailyProgressController::class)->only(['store', 'update', 'destroy'])->parameters(['daily-progress' => 'dailyProgress']);
        Route::apiResource('work-logs', WorkLogController::class)->only(['store', 'update', 'destroy'])->parameters(['work-logs' => 'workLog']);
        Route::apiResource('tasks', TaskController::class)->only(['store', 'update', 'destroy']);
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::apiResource('learning', LearningController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('milestones', MilestoneController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('saved-views', SavedViewController::class)->only(['store', 'destroy'])->parameters(['saved-views' => 'savedView']);
        Route::post('saved-views/{savedView}/set-default', [SavedViewController::class, 'setDefault']);
        Route::apiResource('references', ReferenceController::class)->only(['store', 'destroy']);
        Route::apiResource('docs', DocController::class)->only(['store', 'update', 'destroy']);
        Route::post('docs/{doc}/share', [DocController::class, 'share']);
        Route::post('money/import', [MoneyController::class, 'import']);
        Route::post('bills', [BillController::class, 'store']);
        Route::patch('bills/{bill}', [BillController::class, 'update']);
        Route::delete('bills/{bill}', [BillController::class, 'destroy']);
        Route::post('bills/{bill}/pay', [BillController::class, 'pay']);
        Route::delete('bills/{bill}/pay/{month}', [BillController::class, 'unpay']);
        Route::post('bills/{bill}/skip', [BillController::class, 'skip']);
        Route::delete('bills/{bill}/skip/{month}', [BillController::class, 'unskip']);
        Route::post('bills/set-budget', [BillController::class, 'setBudget']);
        Route::post('lists', [TodoListController::class, 'store']);
        Route::patch('lists/{todoList}', [TodoListController::class, 'update']);
        Route::delete('lists/{todoList}', [TodoListController::class, 'destroy']);
        Route::post('lists/{todoList}/items', [TodoListController::class, 'storeItem']);
        Route::patch('lists/{todoList}/items/{item}', [TodoListController::class, 'updateItem']);
        Route::delete('lists/{todoList}/items/{item}', [TodoListController::class, 'destroyItem']);
        Route::delete('docs/{doc}/share', [DocController::class, 'unshare']);
        Route::post('docs/{doc}/files', [DocFileController::class, 'store']);
        Route::delete('docs/{doc}/files/{docFile}', [DocFileController::class, 'destroy']);
        // Configuration write routes moved to /api/admin/configuration (admin-only)
    });

    Route::post('quick-capture', CaptureController::class)
        ->middleware(['ability:capture,write', 'throttle:api-capture']);

    Route::get('reports/{period}/export', [ReportController::class, 'export'])
        ->middleware(['ability:reports,read', 'throttle:api-export']);

    Route::get('reports/{period}/export-pdf', [ReportController::class, 'exportPdf'])
        ->middleware(['ability:reports,read', 'throttle:api-export']);

    Route::post('reports/{period}/snapshots', [ReportController::class, 'storeSnapshot'])
        ->middleware(['ability:reports,write', 'throttle:api-write']);

    // configuration/auth, configuration/mail, configuration/quote moved to /api/admin/configuration
    Route::put('quote/themes', [QuoteController::class, 'saveUserThemes'])->middleware(['ability:write', 'throttle:api-write']);
    Route::delete('quote/themes', [QuoteController::class, 'clearUserThemes'])->middleware(['ability:write']);
    Route::post('quote/refresh', [QuoteController::class, 'refresh'])
        ->middleware(['ability:write', 'throttle:api-write']);

    Route::middleware(['throttle:api-write'])->group(function () {
        Route::prefix('games/2048')->group(function () {
            Route::get('active', [GameController::class, 'active2048Session']);
            Route::get('daily', [GameController::class, 'daily2048Status']);
            Route::get('records', [GameController::class, 'records2048']);
            Route::post('sessions', [GameController::class, 'start2048Session']);
            Route::patch('sessions/{session}', [GameController::class, 'saveProgress']);
            Route::post('sessions/{session}/complete', [GameController::class, 'complete2048Session']);
        });

        Route::prefix('games/memory')->group(function () {
            Route::get('active', [GameController::class, 'activeMemorySession']);
            Route::get('daily', [GameController::class, 'memoryDailyStatus']);
            Route::get('records', [GameController::class, 'memoryRecords']);
            Route::post('sessions', [GameController::class, 'startMemorySession']);
            Route::patch('sessions/{session}', [GameController::class, 'saveProgress']);
            Route::post('sessions/{session}/complete', [GameController::class, 'completeMemorySession']);
        });

        Route::prefix('games/melody')->group(function () {
            Route::get('records', [GameController::class, 'melodyRecords']);
            Route::post('sessions', [GameController::class, 'startMelodySession']);
            Route::post('sessions/{session}/complete', [GameController::class, 'completeMelodySession']);
        });

        Route::prefix('games/pitch')->group(function () {
            Route::get('records', [GameController::class, 'pitchRecords']);
            Route::post('sessions', [GameController::class, 'startPitchSession']);
            Route::post('sessions/{session}/complete', [GameController::class, 'completePitchSession']);
        });

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
    }); // end throttle:api-write game group

    // Inbox (user-to-user messaging)
    Route::prefix('inbox')->group(function () {
        Route::get('conversations', [InboxController::class, 'conversations'])->middleware('throttle:api-read');
        Route::post('conversations', [InboxController::class, 'start'])->middleware(['ability:write', 'throttle:api-write']);
        Route::get('conversations/{conversation}/messages', [InboxController::class, 'messages'])->middleware('throttle:api-read');
        Route::post('conversations/{conversation}/messages', [InboxController::class, 'send'])->middleware(['ability:write', 'throttle:api-write']);
        Route::delete('messages/{message}', [InboxController::class, 'deleteMessage'])->middleware(['ability:write', 'throttle:api-write']);
        Route::get('messages/{message}/file', [InboxController::class, 'serveFile'])->middleware('throttle:api-read');
        Route::get('unread', [InboxController::class, 'unread'])->middleware('throttle:api-read');
        Route::get('users', [InboxController::class, 'searchUsers'])->middleware('throttle:api-read');
        Route::get('gif', [InboxController::class, 'searchGif'])->middleware('throttle:api-read');
    });
});
