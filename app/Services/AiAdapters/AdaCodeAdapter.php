<?php

namespace App\Services\AiAdapters;

use Illuminate\Support\Facades\Http;

class AdaCodeAdapter
{
    private const BASE_URL = 'https://api.adacode.ai/v1';

    public static function chat(
        string $apiKey,
        string $model,
        array $messages,
        int $maxTokens = 1024,
        float $temperature = 0.8,
        ?string $systemPrompt = null,
    ): array {
        try {
            $payload = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'messages' => $messages,
            ];

            if ($systemPrompt) {
                array_unshift($payload['messages'], ['role' => 'system', 'content' => $systemPrompt]);
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::BASE_URL.'/chat/completions', $payload);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'error_code' => $response->json('error.code'),
                    'error_message' => $response->json('error.message', 'Request failed'),
                ];
            }

            return [
                'success' => true,
                'content' => $response->json('choices.0.message.content', ''),
                'tokens' => $response->json('usage.total_tokens', 0),
                'provider' => 'adacode',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'error_code' => 'internal_error',
                'error_message' => $e->getMessage(),
            ];
        }
    }
}
