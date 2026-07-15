<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AiQuotaService
{
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
            'allowed' => ! $isExceeded,
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
        // Argument order matters: AiProviderManager::trackUsage signature is
        // (user, tokens, requests=1, feature=null, provider=null).
        // Passing $provider as the 3rd positional argument previously
        // overwrote $requests=1 silently and dropped the provider hint,
        // which routed the write to whatever provider resolved from
        // resolveProvider('chat'). That bug is fixed here by passing it
        // positionally as the 5th argument.
        app(AiProviderManager::class)->trackUsage(
            $user,
            $tokensUsed,
            1,
            null,
            $provider,
        );
    }

    /**
     * Reset usage counters (for billing cycle).
     *
     * Resets buckets for every registered provider so a quota refresh
     * does not leave stale counters for an inactive provider.
     */
    public function resetUsage(User $user): void
    {
        $providers = array_keys((array) config('ai.providers', []));

        foreach ($providers as $provider) {
            $storageKey = config("ai.providers.{$provider}.storage_key", $provider);
            Configuration::setValue($user, $storageKey, 'usage', [
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
