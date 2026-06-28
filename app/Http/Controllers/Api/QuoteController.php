<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class QuoteController extends Controller
{
    private const THEMES = [
        'motivation', 'stoic', 'mindfulness', 'productivity',
        'creativity', 'philosophy', 'wisdom', 'life', 'humor',
        'romantic', 'leadership', 'growth',
    ];

    public function daily(Request $request)
    {
        $user = $request->user();
        $config = $this->quoteConfig($user);

        if (! ($config['enabled'] ?? false)) {
            return ApiResponse::ok(['quote' => null]);
        }

        $apiKey = $config['api_key'] ?? null;
        if (! $apiKey) {
            return ApiResponse::ok(['quote' => null]);
        }

        $today = Carbon::now($user->timezone ?? 'Asia/Jakarta')->toDateString();
        $cacheKey = "daily_quote_{$user->id}_{$today}";

        $quote = Cache::remember($cacheKey, Carbon::now()->endOfDay(), function () use ($apiKey, $config) {
            return $this->generate($apiKey, $config['themes'] ?? ['motivation']);
        });

        return ApiResponse::ok(['quote' => $quote]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $config = $this->quoteConfig($user);
        $apiKey = $config['api_key'] ?? null;

        if (! ($config['enabled'] ?? false) || ! $apiKey) {
            return ApiResponse::ok(['quote' => null]);
        }

        $today = Carbon::now($user->timezone ?? 'Asia/Jakarta')->toDateString();
        $cacheKey = "daily_quote_{$user->id}_{$today}";
        Cache::forget($cacheKey);

        $quote = $this->generate($apiKey, $config['themes'] ?? ['motivation']);
        Cache::put($cacheKey, $quote, Carbon::now()->endOfDay());

        return ApiResponse::ok(['quote' => $quote]);
    }

    public function saveConfig(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'themes' => ['required', 'array', 'min:1'],
            'themes.*' => ['string', 'max:60'],
            'api_key' => ['nullable', 'string', 'max:200'],
        ]);

        $existing = Configuration::getValue($request->user(), 'quote', 'groq', []);
        $existing = is_array($existing) ? $existing : [];

        $config = [
            'enabled' => $data['enabled'],
            'themes' => $data['themes'],
            'api_key' => filled($data['api_key'] ?? null) ? $data['api_key'] : ($existing['api_key'] ?? null),
        ];

        Configuration::setValue($request->user(), 'quote', 'groq', $config, encrypted: true);

        // Bust cache so next load generates fresh with new settings
        $today = Carbon::now()->toDateString();
        Cache::forget("daily_quote_{$request->user()->id}_{$today}");

        return ApiResponse::ok(['quote_config' => $this->buildPayload($config)], 'Quote settings saved.');
    }

    private function generate(string $apiKey, array $themes): ?array
    {
        $themeList = implode(', ', $themes);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant',
                    'max_tokens' => 120,
                    'temperature' => 0.9,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You generate short, meaningful quotes. Always reply with valid JSON only, no explanation.',
                        ],
                        [
                            'role' => 'user',
                            'content' => "Generate one quote with theme(s): {$themeList}. Use a real quote from a real person when fitting, or craft an original one attributed to Anonymous. Reply ONLY with this JSON: {\"quote\": \"...\", \"author\": \"...\"}",
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $this->trackUsage($response->json('usage.total_tokens', 0));

            $content = $response->json('choices.0.message.content', '');
            if (preg_match('/\{[^}]+\}/', $content, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (isset($parsed['quote'], $parsed['author'])) {
                    return [
                        'quote' => $parsed['quote'],
                        'author' => $parsed['author'],
                        'themes' => implode(', ', array_slice(array_map('ucfirst', $themes), 0, 2)),
                    ];
                }
            }
        } catch (\Throwable) {
            // Silently fail — quote is non-critical
        }

        return null;
    }

    public function usage(Request $request): \Illuminate\Http\JsonResponse
    {
        $today = Carbon::now()->toDateString();
        $stored = Configuration::getValue($request->user(), 'groq', 'usage', []);
        $stored = is_array($stored) ? $stored : [];

        if (($stored['date'] ?? '') !== $today) {
            $stored = ['date' => $today, 'requests' => 0, 'tokens' => 0];
        }

        return ApiResponse::ok([
            'usage' => [
                'date' => $today,
                'requests' => $stored['requests'] ?? 0,
                'tokens' => $stored['tokens'] ?? 0,
                'request_limit' => 14400,
            ],
        ]);
    }

    public static function trackUsageFor($user, int $tokens): void
    {
        $today = Carbon::now()->toDateString();
        $stored = Configuration::getValue($user, 'groq', 'usage', []);
        $stored = is_array($stored) ? $stored : [];

        if (($stored['date'] ?? '') !== $today) {
            $stored = ['date' => $today, 'requests' => 0, 'tokens' => 0];
        }

        $stored['requests'] = ($stored['requests'] ?? 0) + 1;
        $stored['tokens'] = ($stored['tokens'] ?? 0) + $tokens;

        Configuration::setValue($user, 'groq', 'usage', $stored);
    }

    private function trackUsage(int $tokens): void
    {
        // no-op here — quote generation runs inside Cache::remember (no user context)
        // usage tracked per-request where user is available
    }

    public function configPayload(Request $request): \Illuminate\Http\JsonResponse
    {
        $config = $this->quoteConfig($request->user());

        return ApiResponse::ok(['quote_config' => $this->buildPayload($config)]);
    }

    private function buildPayload(array $config): array
    {
        return [
            'enabled' => $config['enabled'] ?? false,
            'themes' => $config['themes'] ?? ['motivation'],
            'has_api_key' => filled($config['api_key'] ?? null),
        ];
    }

    private function quoteConfig($user): array
    {
        $stored = Configuration::getValue($user, 'quote', 'groq', []);

        return is_array($stored) ? $stored : [];
    }
}
