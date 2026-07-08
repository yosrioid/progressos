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
     * Check if user has exceeded their AI quota
     */
    public function checkQuota(User $user, string $provider = 'groq'): array
    {
        // Get usage from provider-specific storage (same as AiProviderManager)
        $storageKey = $provider === 'adacode' ? 'adacode' : 'groq';
        $stored = Configuration::getValue($user, $storageKey, 'usage', []);
        $stored = is_array($stored) ? $stored : [];

        $limits = $this->limits[$provider] ?? $this->limits['groq'];

        $usageRequests = $stored['requests'] ?? 0;
        $usageTokens = $stored['tokens'] ?? 0;
        $requestLimit = $limits['request_limit'];
        $tokenLimit = $limits['token_limit'];

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
     * Increment usage counters
     */
    public function incrementUsage(User $user, string $provider, int $tokensUsed = 0): void
    {
        $config = $this->getUserConfig($user);

        $config['usage_requests'] = ($config['usage_requests'] ?? 0) + 1;
        $config['usage_tokens'] = ($config['usage_tokens'] ?? 0) + $tokensUsed;

        $this->saveUserConfig($user, $config);
    }

    /**
     * Reset usage counters (for billing cycle)
     */
    public function resetUsage(User $user): void
    {
        $config = $this->getUserConfig($user);
        $config['usage_requests'] = 0;
        $config['usage_tokens'] = 0;
        $this->saveUserConfig($user, $config);
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

    protected function getUserConfig(User $user): array
    {
        $config = Configuration::getValue($user, 'ai', 'settings', []);

        return is_array($config) ? $config : [];
    }

    protected function saveUserConfig(User $user, array $config): void
    {
        Configuration::setValue($user, 'ai', 'settings', $config);
    }
}
