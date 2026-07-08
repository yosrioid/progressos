<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\User;

class QuotaNotificationService
{
    /**
     * Deduplication window: skip creating a new quota_exceeded notification if
     * an unread one already exists for the same (user, provider) within this
     * window. Prevents notification spam when a user sends multiple requests
     * after hitting the quota cap.
     */
    protected int $dedupeWindowMinutes = 60;

    public function notifyQuotaExceeded(User $user, string $provider): void
    {
        $since = now()->subMinutes($this->dedupeWindowMinutes);

        $alreadyNotified = InAppNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'quota_exceeded')
            ->whereNull('read_at')
            ->where('created_at', '>=', $since)
            ->where('data->provider', $provider)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

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
