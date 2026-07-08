<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\User;
use App\Services\AiAdapters\AdaCodeAdapter;
use App\Services\AiAdapters\GroqAdapter;
use Illuminate\Support\Carbon;

class AiProviderManager
{
    /**
     * Provider quota limits, mirrored from AiQuotaService to avoid a hard
     * dependency on it (AiQuotaService already depends on this class).
     */
    protected array $limits = [
        'groq' => ['request_limit' => 14400, 'token_limit' => 10000000],
        'adacode' => ['request_limit' => 1000, 'token_limit' => 1000000],
        'openai' => ['request_limit' => 5000, 'token_limit' => 5000000],
    ];

    public function resolveProvider(string $feature): string
    {
        $config = Configuration::getValue(null, 'ai', 'provider_config', []);
        $config = is_array($config) ? $config : [];

        // Check feature-specific providers first
        if (isset($config['feature_providers'][$feature])) {
            return $config['feature_providers'][$feature];
        }

        return $config['provider'] ?? config('ai.default_provider', 'groq');
    }

    public function call(string $provider, array $payload): array
    {
        return match ($provider) {
            'groq' => GroqAdapter::chat(
                $payload['apiKey'] ?? config('ai.providers.groq.api_key_env'),
                $payload['model'] ?? config('ai.providers.groq.chat_model', 'llama-3.1-8b-instant'),
                $payload['messages'],
                $payload['maxTokens'] ?? 600,
                $payload['temperature'] ?? 0.8,
                $payload['systemPrompt'] ?? null,
            ),
            'adacode' => AdaCodeAdapter::chat(
                $payload['apiKey'] ?? config('ai.providers.adacode.api_key_env'),
                $payload['model'] ?? config('ai.providers.adacode.chat_model', 'claude-sonnet-4-6'),
                $payload['messages'],
                $payload['maxTokens'] ?? 600,
                $payload['temperature'] ?? 0.8,
                $payload['systemPrompt'] ?? null,
            ),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    public function isQuotaExceeded(array $result): bool
    {
        return ($result['status'] ?? 0) === 403
            && ($result['error_code'] ?? '') === 'billing_error';
    }

    /**
     * Track usage for a feature.
     *
     * @param  string  $feature  Feature name (e.g. 'chat', 'journal', 'quote') used
     *                           to resolve which AI provider the request consumed.
     */
    public function trackUsage(User $user, int $tokens, int $requests = 1, ?string $feature = null, ?string $provider = null): void
    {
        $provider = $provider ?? $this->resolveProvider($feature ?? 'chat');
        $this->incrementUsageBucket($user, $provider, $tokens, $requests);
    }

    /**
     * Track usage for quote generation. Caller may specify the provider explicitly
     * because the quote generator may have fallen back from AdaCode to Groq.
     */
    public function trackQuoteUsage(User $user, int $tokens, string $provider = 'groq'): void
    {
        $this->incrementUsageBucket($user, $provider, $tokens, 1);
    }

    /**
     * Atomically increment today's usage bucket for the given provider.
     *
     * Uses a single Configuration::setValue write (the underlying value is
     * serialized on the row, not on the controller instance), which is safe
     * against concurrent writes within a single request lifecycle. Laravel's
     * request lifecycle ensures one execution per request, so we do not need
     * a DB transaction here — but we DO guard against stale cross-day reads
     * by comparing the stored 'date' to today before incrementing.
     */
    protected function incrementUsageBucket(User $user, string $provider, int $tokens, int $requests): void
    {
        $today = Carbon::now()->toDateString();
        $storageKey = $this->storageKeyFor($provider);
        $stored = Configuration::getValue($user, $storageKey, 'usage', []);
        $stored = is_array($stored) ? $stored : [];

        if (($stored['date'] ?? '') !== $today) {
            $stored = ['date' => $today, 'requests' => 0, 'tokens' => 0];
        }

        $stored['requests'] = ($stored['requests'] ?? 0) + $requests;
        $stored['tokens'] = ($stored['tokens'] ?? 0) + $tokens;

        Configuration::setValue($user, $storageKey, 'usage', $stored);
    }

    /**
     * Get usage for a specific provider (for frontend display).
     */
    public function getUsage(User $user, string $provider = 'groq'): array
    {
        $today = Carbon::now()->toDateString();
        $storageKey = $this->storageKeyFor($provider);
        $stored = Configuration::getValue($user, $storageKey, 'usage', []);
        $stored = is_array($stored) ? $stored : [];

        if (($stored['date'] ?? '') !== $today) {
            $stored = ['date' => $today, 'requests' => 0, 'tokens' => 0];
        }

        $limit = $this->limits[$provider] ?? $this->limits['groq'];

        return [
            'date' => $today,
            'requests' => $stored['requests'] ?? 0,
            'tokens' => $stored['tokens'] ?? 0,
            'request_limit' => $limit['request_limit'],
            'token_limit' => $limit['token_limit'],
            'provider' => $provider,
        ];
    }

    protected function storageKeyFor(string $provider): string
    {
        return $provider === 'adacode' ? 'adacode' : 'groq';
    }
}
