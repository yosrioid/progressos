<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $color
 * @property string $icon
 * @property string $frequency
 * @property int[]|null $target_days
 * @property bool $active
 * @property bool $archived
 * @property int $order
 */
class Habit extends Model
{
    protected $fillable = [
        'user_id', 'name', 'description', 'color', 'icon',
        'frequency', 'target_days', 'active', 'archived', 'order',
    ];

    protected function casts(): array
    {
        return [
            'target_days' => 'array',
            'active' => 'boolean',
            'archived' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function currentStreak(): int
    {
        $logs = $this->logs()->orderByDesc('date')->pluck('date')->map(fn ($d) => $d->toDateString())->toArray();
        if (empty($logs)) {
            return 0;
        }
        $streak = 0;
        $check = now()->toDateString();
        foreach ($logs as $date) {
            if ($date === $check) {
                $streak++;
                $check = now()->subDays($streak)->toDateString();
            } elseif ($date === now()->subDays(1)->toDateString() && $streak === 0) {
                $streak++;
                $check = now()->subDays(2)->toDateString();
            } else {
                break;
            }
        }

        return $streak;
    }
}
