<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property Carbon|null $analyzed_at
 *
 * @use HasFactory<\Database\Factories\JournalFactory>
 */
class Journal extends Model
{
    use HasFactory;

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
