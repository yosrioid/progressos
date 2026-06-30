<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property \Illuminate\Support\Carbon|null $disabled_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'google_id', 'avatar_path', 'timezone', 'theme', 'dashboard_layout', 'notification_preferences', 'disabled_at'];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function dailyProgressEntries(): HasMany
    {
        return $this->hasMany(DailyProgressEntry::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function reviewEntries(): HasMany
    {
        return $this->hasMany(ReviewEntry::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(Reference::class);
    }

    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    public function learningEntries(): HasMany
    {
        return $this->hasMany(LearningEntry::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function reportSnapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(Configuration::class);
    }

    public function backupRuns(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }

    public function docs(): HasMany
    {
        return $this->hasMany(Doc::class);
    }

    public function todoLists(): HasMany
    {
        return $this->hasMany(TodoList::class);
    }

    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    public function habitLogs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function moneyTransactions(): HasMany
    {
        return $this->hasMany(MoneyTransaction::class);
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(KeyResult::class);
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password' => 'hashed',
            'dashboard_layout' => 'array',
            'notification_preferences' => 'array',
        ];
    }
}
