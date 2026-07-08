<?php

namespace App\Services;

use App\Models\Configuration;
use App\Services\AiAdapters\AdaCodeAdapter;
use App\Services\AiAdapters\GroqAdapter;
use Illuminate\Support\Carbon;

class AiProviderManager
{
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

    public function trackUsage($user, int $tokens, int $requests = 1): void
    {
        $today = Carbon::now()->toDateString();
        $provider = $this->resolveProvider('chat'); // determine provider from config

        // Use provider-specific storage key
        $storageKey = $provider === 'adacode' ? 'adacode' : 'groq';
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
     * Track usage for quote generation (uses provider-specific storage)
     */
    public function trackQuoteUsage($user, int $tokens, string $provider = 'groq'): void
    {
        $today = Carbon::now()->toDateString();
        $storageKey = $provider === 'adacode' ? 'adacode' : 'groq';
        $stored = Configuration::getValue($user, $storageKey, 'usage', []);
        $stored = is_array($stored) ? $stored : [];

        if (($stored['date'] ?? '') !== $today) {
            $stored = ['date' => $today, 'requests' => 0, 'tokens' => 0];
        }

        $stored['requests'] = ($stored['requests'] ?? 0) + 1;
        $stored['tokens'] = ($stored['tokens'] ?? 0) + $tokens;

        Configuration::setValue($user, $storageKey, 'usage', $stored);
    }

    /**
     * Get usage for a specific provider (for frontend display)
     */
    public function getUsage($user, string $provider = 'groq'): array
    {
        $today = Carbon::now()->toDateString();
        $storageKey = $provider === 'adacode' ? 'adacode' : 'groq';
        $stored = Configuration::getValue($user, $storageKey, 'usage', []);
        $stored = is_array($stored) ? $stored : [];

        if (($stored['date'] ?? '') !== $today) {
            $stored = ['date' => $today, 'requests' => 0, 'tokens' => 0];
        }

        $limits = [
            'groq' => ['request_limit' => 14400, 'token_limit' => 10000000],
            'adacode' => ['request_limit' => 1000, 'token_limit' => 1000000],
        ];

        $limit = $limits[$provider] ?? $limits['groq'];

        return [
            'date' => $today,
            'requests' => $stored['requests'] ?? 0,
            'tokens' => $stored['tokens'] ?? 0,
            'request_limit' => $limit['request_limit'],
            'token_limit' => $limit['token_limit'],
            'provider' => $provider,
        ];
    }
}
