<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 */
class Goal extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'period_label',
        'start_date', 'end_date', 'status', 'color',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(KeyResult::class)->orderBy('order');
    }

    public function progressPercent(): float
    {
        $krs = $this->keyResults()->where('status', '!=', 'abandoned')->get();
        if ($krs->isEmpty()) {
            return 0;
        }
        $total = $krs->sum(function (KeyResult $kr) {
            if ($kr->metric_type === 'boolean') {
                return $kr->current_value >= 1 ? 100 : 0;
            }

            return $kr->target_value > 0
                ? min(100, ($kr->current_value / $kr->target_value) * 100)
                : 0;
        });

        return round($total / $krs->count(), 1);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
