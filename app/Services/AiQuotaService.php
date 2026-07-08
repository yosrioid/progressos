<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AiQuotaService
{
    protected array $limits = [
        'groq' => [
            'request_limit' => 14400,
            'token_limit' => 10000000,
        ],
        'adacode' => [
            'request_limit' => 1000,
            'token_limit' => 1000000,
        ],
        'openai' => [
            'request_limit' => 5000,
            'token_limit' => 5000000,
        ],
    ];

    /**
     * Check if user has exceeded their AI quota.
     *
     * Reads usage from the same storage key as {@see AiProviderManager::trackUsage()}
     * so that quotas are enforced against the live counter, not a stale snapshot.
     */
    public function checkQuota(User $user, string $provider = 'groq'): array
    {
        // Source of truth: AiProviderManager (writes to ${provider}/usage, keys 'requests' & 'tokens').
        // Fetch via the manager so we never duplicate storage conventions.
        $usage = app(AiProviderManager::class)->getUsage($user, $provider);

        $usageRequests = $usage['requests'];
        $usageTokens = $usage['tokens'];
        $requestLimit = $usage['request_limit'];
        $tokenLimit = $usage['token_limit'];

        $isExceeded = $usageRequests >= $requestLimit || $usageTokens >= $tokenLimit;

        return [
            'provider' => $provider,
            'usage_requests' => $usageRequests,
            'usage_tokens' => $usageTokens,
            'request_limit' => $requestLimit,
            'token_limit' => $tokenLimit,
            'request_percentage' => $requestLimit > 0 ? round(($usageRequests / $requestLimit) * 100, 2) : 0,
            'token_percentage' => $tokenLimit > 0 ? round(($usageTokens / $tokenLimit) * 100, 2) : 0,
            'is_exceeded' => $isExceeded,
        ];
    }

    /**
     * Increment usage counters for a provider.
     *
     * Delegates to {@see AiProviderManager::trackUsage()} so writes go to the
     * same storage key that {@see checkQuota()} reads from.
     */
    public function incrementUsage(User $user, string $provider, int $tokensUsed = 0): void
    {
        app(AiProviderManager::class)->trackUsage($user, $tokensUsed, 1, $provider);
    }

    /**
     * Reset usage counters (for billing cycle).
     *
     * Resets both per-provider buckets so a quota refresh does not leave stale
     * counters for the provider that is no longer active.
     */
    public function resetUsage(User $user): void
    {
        foreach (['groq', 'adacode'] as $provider) {
            Configuration::setValue($user, $provider, 'usage', [
                'date' => now()->toDateString(),
                'requests' => 0,
                'tokens' => 0,
            ]);
        }
    }

    /**
     * Get quota info from external provider (AdaCode)
     */
    public function fetchExternalQuota(string $apiKey): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->get('https://api.adacode.ai/v1/quotas', [
                'type' => 'request_limit',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'usage' => $data['usage'] ?? 0,
                    'limit' => $data['limit'] ?? 1000,
                    'reset_at' => $data['reset_at'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            \Log::warning('Failed to fetch external quota: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Enforce quota limits - throw exception if exceeded
     */
    public function enforceQuota(User $user, string $provider = 'groq'): void
    {
        $quota = $this->checkQuota($user, $provider);

        if ($quota['is_exceeded']) {
            throw new \RuntimeException('AI API quota exceeded. Please configure a different provider or wait for your next billing cycle.');
        }
    }
}
