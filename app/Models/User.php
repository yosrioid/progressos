<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'avatar_path', 'timezone', 'theme', 'dashboard_layout', 'notification_preferences'];

    protected $hidden = ['password', 'remember_token'];

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

    public function backupConnections(): HasMany
    {
        return $this->hasMany(BackupConnection::class);
    }

    public function backupSyncs(): HasMany
    {
        return $this->hasMany(BackupSync::class);
    }

    public function backupRuns(): HasMany
    {
        return $this->hasMany(BackupRun::class);
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
            'password' => 'hashed',
            'dashboard_layout' => 'array',
            'notification_preferences' => 'array',
        ];
    }
}
