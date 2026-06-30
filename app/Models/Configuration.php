<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Configuration extends Model
{
    use Auditable;

    public const GROUPS = ['general', 'appearance', 'sync', 'notifications'];

    public const SYNC_MODULES = ['daily_progress', 'work_logs', 'tasks', 'learning', 'milestones', 'reports'];

    public const SYNC_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    protected $fillable = ['user_id', 'group', 'key', 'value', 'encrypted_value', 'type'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'encrypted_value' => 'encrypted:array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getValue(?User $user, string $group, string $key, mixed $default = null): mixed
    {
        $query = static::where('group', $group)->where('key', $key);
        $user === null ? $query->whereNull('user_id') : $query->where('user_id', $user->id);
        $config = $query->first();
        if (! $config) {
            return $default;
        }

        return $config->encrypted_value ?? $config->value ?? $default;
    }

    public static function setValue(?User $user, string $group, string $key, mixed $value, bool $encrypted = false, string $type = 'array'): self
    {
        $config = static::firstOrNew(['user_id' => $user?->id ?? null, 'group' => $group, 'key' => $key]);
        $config->type = $type;
        $config->value = $encrypted ? null : $value;
        $config->encrypted_value = $encrypted ? $value : null;
        $config->save();

        return $config;
    }
}
