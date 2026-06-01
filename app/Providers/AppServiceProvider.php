<?php

namespace App\Providers;

use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Reference;
use App\Models\ReviewEntry;
use App\Models\SavedView;
use App\Models\Task;
use App\Models\WorkLog;
use App\Policies\OwnedModelPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        foreach ([DailyProgressEntry::class, WorkLog::class, LearningEntry::class, Milestone::class, Task::class, Project::class, Reference::class, ReviewEntry::class, SavedView::class] as $model) {
            Gate::policy($model, OwnedModelPolicy::class);
        }

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('passwords', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('api-read', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-capture', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-export', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-tokens', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
    }
}
