<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\User;
use App\Services\AiProviderManager;
use App\Services\AiQuotaService;
use App\Services\QuotaNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class QuoteController extends Controller
{
    private const THEMES = [
        'motivation', 'stoic', 'mindfulness', 'productivity',
        'creativity', 'philosophy', 'wisdom', 'life', 'humor',
        'romantic', 'leadership', 'growth',
    ];

    public function __construct(
        private AiProviderManager $aiManager,
        private QuotaNotificationService $quotaNotifier,
        private AiQuotaService $quotaService,
    ) {}

    public function daily(Request $request)
    {
        $user = $request->user();
        $config = $this->quoteConfig($user);

        if (! ($config['enabled'] ?? false)) {
            return ApiResponse::ok(['quote' => null]);
        }

        // Get provider and API key from quote config
        $provider = $config['provider'] ?? 'groq';

        // Quota pre-check so users can't drain their daily budget without warning.
        $quota = $this->quotaService->checkQuota($user, $provider);
        if ($quota['is_exceeded'] ?? false) {
            return ApiResponse::ok(
                ['quote' => null, 'quota_exceeded' => true, 'quota' => $quota],
                'Kuota AI harian telah habis.',
                429,
            );
        }

        $apiKey = $this->getApiKeyForProvider($provider);

        if (! $apiKey) {
            return ApiResponse::ok(['quote' => null]);
        }

        $today = Carbon::now($user->timezone ?? 'Asia/Jakarta')->toDateString();
        $cacheKey = "daily_quote_{$user->id}_{$today}";

        $quote = Cache::remember($cacheKey, Carbon::now()->endOfDay(), function () use ($apiKey, $config, $provider, $user) {
            return $this->generate($user, $apiKey, $config['themes'] ?? ['motivation'], $provider);
        });

        return ApiResponse::ok(['quote' => $quote]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $config = $this->quoteConfig($user);

        // Get provider and API key from quote config
        $provider = $config['provider'] ?? 'groq';

        // Quota pre-check. Manual refresh deserves the same guard as daily.
        $quota = $this->quotaService->checkQuota($user, $provider);
        if ($quota['is_exceeded'] ?? false) {
            return ApiResponse::ok(
                ['quote' => null, 'quota_exceeded' => true, 'quota' => $quota],
                'Kuota AI harian telah habis.',
                429,
            );
        }

        $apiKey = $this->getApiKeyForProvider($provider);

        if (! ($config['enabled'] ?? false) || ! $apiKey) {
            return ApiResponse::ok(['quote' => null]);
        }

        $today = Carbon::now($user->timezone ?? 'Asia/Jakarta')->toDateString();
        $cacheKey = "daily_quote_{$user->id}_{$today}";
        Cache::forget($cacheKey);

        $quote = $this->generate($user, $apiKey, $config['themes'] ?? ['motivation'], $provider);
        Cache::put($cacheKey, $quote, Carbon::now()->endOfDay());

        return ApiResponse::ok(['quote' => $quote]);
    }

    public function saveConfig(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'themes' => ['required', 'array', 'min:1'],
            'themes.*' => ['string', 'max:60'],
            'provider' => ['required', 'in:groq,adacode'],
        ]);

        $existing = Configuration::getValue(null, 'quote', 'groq', []);
        $existing = is_array($existing) ? $existing : [];

        $config = [
            'enabled' => $data['enabled'],
            'themes' => $data['themes'],
            'provider' => $data['provider'],
        ];

        Configuration::setValue(null, 'quote', 'groq', $config, encrypted: true);
        $this->bustQuoteCache($request->user());

        return ApiResponse::ok(['quote_config' => $this->buildPayload($this->quoteConfig($request->user()))], 'Quote settings saved.');
    }

    /**
     * Generate a quote for a user, tracking usage back to the caller.
     *
     * Previously usage tracking was a no-op because the call site wrapped
     * generate() in Cache::remember() and had no access to the user model.
     * By passing $user through, AiProviderManager can now increment today's
     * bucket correctly.
     */
    private function generate(User $user, string $apiKey, array $themes, ?string $provider = null): ?array
    {
        $themeList = implode(', ', $themes);
        $provider = $provider ?? config('ai.default_provider', 'groq');

        $model = 'llama-3.1-8b-instant';
        if ($provider === 'adacode') {
            $aiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
            $aiConfig = is_array($aiConfig) ? $aiConfig : [];
            $model = $aiConfig['model'] ?? config('ai.providers.adacode.chat_model', 'claude-sonnet-4-6');
        }

        try {
            $result = $this->aiManager->call($provider, [
                'apiKey' => $apiKey,
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => "Generate one quote with theme(s): {$themeList}. Use a real quote from a real person when fitting, or craft an original one attributed to Anonymous. Reply ONLY with this JSON: {\"quote\": \"...\", \"author\": \"...\"}"],
                ],
                'maxTokens' => 120,
                'temperature' => 0.9,
            ]);

            if (! $result['success'] ?? false) {
                return null;
            }

            $this->aiManager->trackQuoteUsage($user, (int) ($result['tokens'] ?? 0), $provider);

            $content = (string) ($result['content'] ?? '');
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

    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::now()->toDateString();

        // Aggregate usage across quote-relevant providers (groq, adacode).
        $groqStored = Configuration::getValue($user, 'groq', 'usage', []);
        $groqStored = is_array($groqStored) ? $groqStored : [];
        if (($groqStored['date'] ?? '') !== $today) {
            $groqStored = ['date' => $today, 'requests' => 0, 'tokens' => 0];
        }

        $adacodeStored = Configuration::getValue($user, 'adacode', 'usage', []);
        $adacodeStored = is_array($adacodeStored) ? $adacodeStored : [];
        if (($adacodeStored['date'] ?? '') !== $today) {
            $adacodeStored = ['date' => $today, 'requests' => 0, 'tokens' => 0];
        }

        return ApiResponse::ok([
            'usage' => [
                'date' => $today,
                'groq_requests' => $groqStored['requests'] ?? 0,
                'groq_tokens' => $groqStored['tokens'] ?? 0,
                'adacode_requests' => $adacodeStored['requests'] ?? 0,
                'adacode_tokens' => $adacodeStored['tokens'] ?? 0,
                'total_requests' => ($groqStored['requests'] ?? 0) + ($adacodeStored['requests'] ?? 0),
                'total_tokens' => ($groqStored['tokens'] ?? 0) + ($adacodeStored['tokens'] ?? 0),
                'request_limit' => 14400,
            ],
        ]);
    }

    public static function trackUsageFor($user, int $tokens, string $provider = 'groq'): void
    {
        app(AiProviderManager::class)->trackQuoteUsage($user, $tokens, $provider);
    }

    public function configPayload(Request $request): JsonResponse
    {
        $config = $this->quoteConfig($request->user());

        return ApiResponse::ok(['quote_config' => $this->buildPayload($config)]);
    }

    public function saveUserThemes(Request $request)
    {
        $data = $request->validate([
            'themes' => ['required', 'array', 'min:1'],
            'themes.*' => ['string', 'max:60'],
        ]);

        Configuration::setValue($request->user(), 'quote', 'themes', $data['themes']);
        $this->bustQuoteCache($request->user());

        return ApiResponse::ok(['quote_config' => $this->buildPayload($this->quoteConfig($request->user()))], 'Quote themes saved.');
    }

    public function clearUserThemes(Request $request)
    {
        Configuration::where('user_id', $request->user()->id)->where('group', 'quote')->where('key', 'themes')->delete();
        $this->bustQuoteCache($request->user());

        return ApiResponse::ok(['quote_config' => $this->buildPayload($this->quoteConfig($request->user()))], 'Quote themes reset to global default.');
    }

    private function bustQuoteCache($user): void
    {
        $today = Carbon::now($user->timezone ?? 'Asia/Jakarta')->toDateString();
        Cache::forget("daily_quote_{$user->id}_{$today}");
    }

    private function buildPayload(array $config): array
    {
        return [
            'enabled' => $config['enabled'] ?? false,
            'themes' => $config['themes'] ?? ['motivation'],
            'provider' => $config['provider'] ?? 'groq',
            'has_custom_themes' => $config['has_custom_themes'] ?? false,
        ];
    }

    /**
     * Read the API key for a feature provider from the encrypted provider_config.
     *
     * The old implementation called getValue() without the encrypted: true flag,
     * which always returned null because saveAiConfig() stored provider_config
     * encrypted (line 489 of ConfigurationController).
     */
    private function getApiKeyForProvider(string $provider): ?string
    {
        $aiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
        $aiConfig = is_array($aiConfig) ? $aiConfig : [];

        return match ($provider) {
            'groq' => $aiConfig['groq_api_key'] ?? null,
            'adacode' => $aiConfig['api_key'] ?? null,
            default => null,
        };
    }

    private function quoteConfig($user): array
    {
        $global = Configuration::getValue(null, 'quote', 'groq', []);
        $global = is_array($global) ? $global : [];

        $userThemes = Configuration::getValue($user, 'quote', 'themes');
        if (is_array($userThemes) && count($userThemes) > 0) {
            $global['themes'] = $userThemes;
            $global['has_custom_themes'] = true;
        } else {
            $global['has_custom_themes'] = false;
        }

        return $global;
    }
}
