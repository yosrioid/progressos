<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    public const STATUSES = ['queued', 'running', 'completed', 'failed'];

    protected $fillable = ['user_id', 'sync_id', 'module', 'destination_sheet_name', 'status', 'started_at', 'finished_at', 'rows_exported', 'file_path', 'error_message'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
