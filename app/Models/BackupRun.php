<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    public const STATUSES = ['queued', 'running', 'completed', 'failed'];

    protected $fillable = ['backup_sync_id', 'user_id', 'status', 'started_at', 'finished_at', 'rows_exported', 'file_path', 'error_message'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function sync(): BelongsTo
    {
        return $this->belongsTo(BackupSync::class, 'backup_sync_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
