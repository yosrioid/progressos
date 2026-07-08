<?php

namespace App\Services\AiAdapters;

use App\Services\AiProviderManager;
use Illuminate\Support\Facades\Http;

/**
 * Shared implementation for AI providers that expose an OpenAI-compatible
 * /chat/completions endpoint (AdaCode, Groq, OpenAI, OpenRouter, etc.).
 *
 * Subclasses only need to declare where to point at (baseUrl) and which
 * provider name to tag the response with. Everything else — request
 * shape, auth header, timeout, error handling, response parsing — is
 * shared here so the OpenAI-compatible contract can be tested once.
 *
 * The return shape is intentionally identical across providers so
 * {@see AiProviderManager::call()} can treat the result
 * opaquely:
 *
 *   success:
 *     ['success' => true,  'content' => string, 'tokens' => int, 'provider' => string]
 *   failure:
 *     ['success' => false, 'status' => int, 'error_code' => ?string,
 *      'error_message' => string, 'provider' => string]
 */
abstract class BaseOpenAiCompatibleAdapter
{
    /** Endpoint root, no trailing slash (e.g. https://api.groq.com/openai/v1). */
    abstract protected static function baseUrl(): string;

    /** Provider identifier used in results and storage (e.g. 'groq', 'adacode'). */
    abstract protected static function providerName(): string;

    /** Default max_tokens for this provider if the caller doesn't specify one. */
    protected static function defaultMaxTokens(): int
    {
        return 1024;
    }

    /** Per-request timeout in seconds. */
    protected static function requestTimeout(): int
    {
        return 30;
    }

    /**
     * Send a chat completion request and normalise the response.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    public static function chat(
        string $apiKey,
        string $model,
        array $messages,
        ?int $maxTokens = null,
        float $temperature = 0.8,
        ?string $systemPrompt = null,
    ): array {
        $maxTokens ??= static::defaultMaxTokens();

        try {
            $payload = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'messages' => $messages,
            ];

            if ($systemPrompt !== null && $systemPrompt !== '') {
                array_unshift($payload['messages'], ['role' => 'system', 'content' => $systemPrompt]);
            }

            $response = Http::timeout(static::requestTimeout())
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(static::baseUrl().'/chat/completions', $payload);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'error_code' => $response->json('error.code'),
                    'error_message' => $response->json('error.message', 'Request failed'),
                    'provider' => static::providerName(),
                ];
            }

            return [
                'success' => true,
                'content' => $response->json('choices.0.message.content', ''),
                'tokens' => $response->json('usage.total_tokens', 0),
                'provider' => static::providerName(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'error_code' => 'internal_error',
                'error_message' => $e->getMessage(),
                'provider' => static::providerName(),
            ];
        }
    }
}
