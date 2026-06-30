<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $analyzed_at
 */
class Journal extends Model
{
    protected $fillable = [
        'user_id', 'date', 'body',
        'mood', 'tema', 'ai_content', 'ai_insight', 'ai_saran', 'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'analyzed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }
}
