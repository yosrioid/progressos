<?php

namespace App\Services;

use App\Models\InAppNotification;

class QuotaNotificationService
{
    public function notifyQuotaExceeded($user, string $provider): void
    {
        InAppNotification::create([
            'user_id' => $user->id,
            'type' => 'quota_exceeded',
            'title' => "Kuota {$provider} Habis",
            'body' => "Kuota API {$provider} Anda sudah habis. "
                .($provider === 'adacode'
                    ? 'Silakan upgrade plan di adacode.ai atau beralih ke Groq.'
                    : 'Silakan periksa quota Groq Anda.'),
            'data' => [
                'provider' => $provider,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
