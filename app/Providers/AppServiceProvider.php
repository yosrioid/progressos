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

        // Login: 5 attempts per minute per email+IP combo — blocks per-account brute force.
        // E2E/Playwright suites login many times in a single minute per worker, so
        // we relax the limit when running under the `testing` environment OR when
        // the server is launched with `PROGRESSOS_RELAX_RATE_LIMITS=1` (used by
        // `php artisan serve` in Playwright config).
        // The `php artisan serve` worker sometimes doesn't propagate custom env
        // vars, so we also detect via the `.playwright-running` marker file
        // that Playwright writes at suite start.
        $isTesting = $this->app->environment('testing')
            || (bool) config('progressos.relax_rate_limits')
            || file_exists(base_path('.playwright-running'));
        // Belt-and-suspenders: accept header at request-time below.
        RateLimiter::for('auth', fn (Request $request) => $isTesting
            ? Limit::none()
            : Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        // Register: 3 per hour per IP — prevents signup spam
        RateLimiter::for('auth-register', fn (Request $request) => $isTesting
            ? Limit::none()
            : Limit::perHour(3)->by($request->ip()));
        RateLimiter::for('passwords', fn (Request $request) => $isTesting
            ? Limit::none()
            : Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('api-read', fn (Request $request) => Limit::perMinute($isTesting ? 100000 : 180)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute($isTesting ? 100000 : 90)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-capture', fn (Request $request) => Limit::perMinute($isTesting ? 100000 : 60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-export', fn (Request $request) => Limit::perMinute($isTesting ? 100000 : 20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-tokens', fn (Request $request) => Limit::perMinute($isTesting ? 100000 : 20)->by($request->user()?->id ?: $request->ip()));
        // AI stream: long-lived SSE connection, so we cap concurrent streams
        // rather than per-minute requests. 30 concurrent streams per user is
        // enough for any realistic chat use (typically 1) while preventing
        // a runaway client from saturating the upstream provider quota.
        RateLimiter::for('ai-stream', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        // Search users: stricter limit — prevents enumeration attacks
        RateLimiter::for('api-search-users', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
