<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSession extends Model
{
    protected $fillable = [
        'user_id', 'type', 'level', 'puzzle', 'solution',
        'user_state', 'notes_state', 'elapsed_seconds', 'status',
    ];

    protected function casts(): array
    {
        return [
            'puzzle' => 'array',
            'solution' => 'array',
            'user_state' => 'array',
            'notes_state' => 'array',
            'elapsed_seconds' => 'integer',
        ];
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
