<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReportSnapshot extends Model
{
    protected $fillable = ['user_id', 'period_type', 'period_start', 'period_end', 'payload'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'payload' => 'array'];
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
