<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\User;
use App\Services\AiAdapters\BaseOpenAiCompatibleAdapter;
use Illuminate\Support\Carbon;

/**
 * Single routing layer between callers and AI provider adapters.
 *
 * Provider metadata (base URL, allowed models, limits, storage bucket) is
 * sourced from config/ai.php so that adding a new provider requires changes
 * to ONE file plus an adapter class. Adapter classes are listed in the
 * config; this manager does not hardcode them.
 */
class AiProviderManager
{
    public function resolveProvider(string $feature): string
    {
        $config = $this->providerConfig();
        $configured = $config['feature_providers'][$feature] ?? null;

        if (is_string($configured) && $configured !== '' && $this->isKnownProvider($configured)) {
            return $configured;
        }

        return $config['provider'] ?? config('ai.default_provider', 'groq');
    }

    /**
     * Call the adapter for a provider. Returns the normalised array shape
     * documented in BaseOpenAiCompatibleAdapter.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function call(string $provider, array $payload): array
    {
        $adapter = $this->adapterFor($provider);
        if ($adapter === null) {
            throw new \InvalidArgumentException("Unknown provider: {$provider}");
        }

        return $adapter::chat(
            $payload['apiKey'] ?? config("ai.providers.{$provider}.api_key_env"),
            $payload['model'] ?? config("ai.providers.{$provider}.chat_model"),
            $payload['messages'],
            $payload['maxTokens'] ?? null,
            $payload['temperature'] ?? 0.8,
            $payload['systemPrompt'] ?? null,
        );
    }

    /**
     * Stream a chat completion. Returns a generator yielding normalised
     * events: ['type' => 'chunk'|'done'|'error', ...].
     *
     * Each adapter decides how to consume the upstream SSE stream; this
     * method just routes to the right adapter and validates provider support.
     *
     * @param  array<string, mixed>  $payload  Adapter-specific payload (model, messages, etc.)
     * @return \Generator<int, array<string, mixed>>
     */
    public function stream(string $provider, array $payload): \Generator
    {
        $adapter = $this->adapterFor($provider);
        if ($adapter === null) {
            throw new \InvalidArgumentException("Unknown provider: {$provider}");
        }

        if (! config("ai.providers.{$provider}.supports_streaming", false)) {
            throw new \RuntimeException("Provider '{$provider}' does not support streaming.");
        }

        yield from $adapter::streamChat(
            $payload['apiKey'] ?? config("ai.providers.{$provider}.api_key_env"),
            $payload['model'] ?? config("ai.providers.{$provider}.chat_model"),
            $payload['messages'],
            $payload['maxTokens'] ?? null,
            $payload['temperature'] ?? 0.8,
            $payload['systemPrompt'] ?? null,
        );
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

        $limit = config("ai.providers.{$provider}.limits")
            ?? config('ai.providers.groq.limits')
            ?? ['request_limit' => 14400, 'token_limit' => 10000000];

        return [
            'date' => $today,
            'requests' => $stored['requests'] ?? 0,
            'tokens' => $stored['tokens'] ?? 0,
            'request_limit' => $limit['request_limit'],
            'token_limit' => $limit['token_limit'],
            'provider' => $provider,
        ];
    }

    /**
     * List all known providers (for admin UI and validation).
     *
     * @return array<string, array<string, mixed>>
     */
    public function listProviders(): array
    {
        return config('ai.providers', []);
    }

    /**
     * Whether a provider key is registered in config/ai.php.
     */
    public function isKnownProvider(string $provider): bool
    {
        return array_key_exists($provider, $this->listProviders());
    }

    /**
     * Whether a model name is in the allowlist for a given provider.
     */
    public function isAllowedModel(string $provider, string $model): bool
    {
        $allowed = config("ai.providers.{$provider}.allowed_models", []);

        return is_array($allowed) && in_array($model, $allowed, true);
    }

    /**
     * Map a provider identifier to the adapter class that owns its
     * OpenAI-compatible endpoint. Returns null for unknown providers so
     * the caller can throw a domain-specific error.
     */
    protected function adapterFor(string $provider): ?string
    {
        $adapter = config("ai.providers.{$provider}.adapter");

        if (is_string($adapter) && class_exists($adapter) && is_subclass_of($adapter, BaseOpenAiCompatibleAdapter::class)) {
            return $adapter;
        }

        return null;
    }

    protected function storageKeyFor(string $provider): string
    {
        return config("ai.providers.{$provider}.storage_key", $provider);
    }

    /**
     * @return array<string, mixed>
     */
    protected function providerConfig(): array
    {
        $config = Configuration::getValue(null, 'ai', 'provider_config', []);

        return is_array($config) ? $config : [];
    }
}
