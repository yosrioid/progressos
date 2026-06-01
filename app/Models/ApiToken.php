<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token_hash', 'abilities', 'last_used_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function issue(User $user, string $name, array $abilities = ['*']): array
    {
        $plainTextToken = 'pos_'.Str::random(64);

        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainTextToken),
            'abilities' => $abilities ?: ['*'],
        ]);

        return [$token, $plainTextToken];
    }
}
