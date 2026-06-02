<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupSync extends Model
{
    public const MODULES = ['daily_progress', 'work_logs', 'tasks', 'learning', 'milestones', 'reports'];

    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    protected $fillable = ['user_id', 'backup_connection_id', 'module', 'frequency', 'destination_sheet_name', 'enabled', 'filters', 'last_run_at', 'next_run_at'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'filters' => 'array',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(BackupConnection::class, 'backup_connection_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
