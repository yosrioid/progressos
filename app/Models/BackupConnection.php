<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupConnection extends Model
{
    public const PROVIDERS = ['google_sheets'];

    public const STATUSES = ['draft', 'verified', 'error'];

    protected $fillable = ['user_id', 'provider', 'name', 'spreadsheet_id', 'credentials', 'status', 'last_verified_at'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'last_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncs(): HasMany
    {
        return $this->hasMany(BackupSync::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
