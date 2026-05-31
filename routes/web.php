<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DailyProgressController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LearningEntryController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuickCaptureController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');
    Route::post('quick-capture', QuickCaptureController::class)->name('quick-capture');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::patch('preferences/dashboard', [PreferenceController::class, 'dashboard'])->name('preferences.dashboard');
    Route::patch('preferences/notifications', [PreferenceController::class, 'notifications'])->name('preferences.notifications');

    Route::post('daily-progress/duplicate', [DailyProgressController::class, 'duplicate'])->name('daily-progress.duplicate');
    Route::patch('daily-progress/{dailyProgress}/archive', [DailyProgressController::class, 'archive'])->name('daily-progress.archive');
    Route::resource('daily-progress', DailyProgressController::class)->parameters(['daily-progress' => 'dailyProgress']);

    Route::patch('work-logs/bulk-status', [WorkLogController::class, 'bulkStatus'])->name('work-logs.bulk-status');
    Route::resource('work-logs', WorkLogController::class)->parameters(['work-logs' => 'workLog']);
    Route::patch('tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
    Route::resource('tasks', TaskController::class);
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('projects/{project}/tasks', [ProjectController::class, 'storeTask'])->name('projects.tasks.store');
    Route::post('projects/{project}/work-logs', [ProjectController::class, 'storeWorkLog'])->name('projects.work-logs.store');
    Route::resource('projects', ProjectController::class)->only(['index', 'show']);
    Route::resource('learning', LearningEntryController::class);
    Route::resource('milestones', MilestoneController::class);
    Route::post('references', [ReferenceController::class, 'store'])->name('references.store');
    Route::delete('references/{reference}', [ReferenceController::class, 'destroy'])->name('references.destroy');
    Route::post('saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
    Route::delete('saved-views/{savedView}', [SavedViewController::class, 'destroy'])->name('saved-views.destroy');

    Route::get('reports/{period}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{period}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reviews/daily', [ReviewController::class, 'daily'])->name('reviews.daily');
    Route::get('reviews/{period}', [ReviewController::class, 'period'])->name('reviews.period');
    Route::post('reviews', [ReviewController::class, 'save'])->name('reviews.save');
    Route::post('reviews/plan-task', [ReviewController::class, 'planTask'])->name('reviews.plan-task');
    Route::patch('reviews/tasks/{task}/carry', [ReviewController::class, 'carryTask'])->name('reviews.tasks.carry');
    Route::post('reviews/tasks/{task}/work-log', [ReviewController::class, 'taskToWorkLog'])->name('reviews.tasks.work-log');
});
