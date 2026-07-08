<?php

namespace App\Providers;

use App\Models\DailyProgressEntry;
use App\Models\Doc;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\KeyResult;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Reference;
use App\Models\ReviewEntry;
use App\Models\SavedView;
use App\Models\Task;
use App\Models\TodoList;
use App\Models\WorkLog;
use App\Policies\OwnedModelPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([DailyProgressEntry::class, WorkLog::class, LearningEntry::class, Milestone::class, Task::class, Project::class, Reference::class, ReviewEntry::class, SavedView::class, Doc::class, Habit::class, Goal::class, KeyResult::class, TodoList::class] as $model) {
            Gate::policy($model, OwnedModelPolicy::class);
        }

        // Enforce strong passwords globally
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        // Login: 5 attempts per minute per email+IP combo — blocks per-account brute force
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        // Register: 3 per hour per IP — prevents signup spam
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perHour(3)->by($request->ip()));
        RateLimiter::for('passwords', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('api-read', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-capture', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-export', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-tokens', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        // AI stream: long-lived SSE connection, so we cap concurrent streams
        // rather than per-minute requests. 30 concurrent streams per user is
        // enough for any realistic chat use (typically 1) while preventing
        // a runaway client from saturating the upstream provider quota.
        RateLimiter::for('ai-stream', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
