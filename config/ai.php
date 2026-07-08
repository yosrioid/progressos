<?php

use App\Services\AiAdapters\AdaCodeAdapter;
use App\Services\AiAdapters\GroqAdapter;

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Single source of truth for every supported AI provider. The
    | AiProviderManager, adapters, controllers, and Vue configuration store
    | all read from this file so that adding a new provider means changing
    | ONE place — not the manager, the quota service, the validation rule,
    | and the frontend payload.
    |
    | Actual runtime values (api keys, per-admin overrides) are stored in
    | the Configuration model (group 'ai', key 'provider_config').
    |
    | Provider keys (e.g. 'groq', 'adacode') are referenced by:
    |   - Database storage buckets (Configuration rows)
    |   - Validation rules in controllers
    |   - Provider routing in AiProviderManager::adapterFor()
    |   - Quota accounting in AiProviderManager::storageKeyFor()
    |
    | To add a new provider:
    |   1. Create app/Services/AiAdapters/<Name>Adapter.php extending
    |      BaseOpenAiCompatibleAdapter and implementing baseUrl() + providerName().
    |   2. Add a block below.
    |   3. Add it to the adapterFor() match expression.
    |   4. That's it — limits, models, and storage keys are derived from here.
    |
    */

    'default_provider' => env('AI_PROVIDER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | Provider Registry
    |--------------------------------------------------------------------------
    |
    | Per-provider metadata. Each entry MUST contain:
    |   - name:            Human-readable label
    |   - base_url:        OpenAI-compatible root (no trailing slash)
    |   - adapter:         FQCN of the adapter (must extend BaseOpenAiCompatibleAdapter)
    |   - chat_model:      Default chat model
    |   - allowed_models:  Whitelist for admin input validation
    |   - limits:          Per-user daily limits (request + token counts)
    |   - default_max_tokens: Default response cap when caller doesn't specify
    |   - storage_key:     Bucket suffix used in Configuration rows for per-user
    |                      usage tracking. Keep stable; changing it resets user
    |                      usage counters.
    |   - supports_streaming: Whether the upstream supports SSE chat completions
    |
    */

    'providers' => [

        'groq' => [
            'name' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1',
            'adapter' => GroqAdapter::class,
            'chat_model' => env('GROQ_CHAT_MODEL', 'llama-3.1-8b-instant'),
            'journal_model' => env('GROQ_JOURNAL_MODEL', 'llama-3.3-70b-versatile'),
            'allowed_models' => [
                'llama-3.1-8b-instant',
                'llama-3.3-70b-versatile',
                'llama-3.1-70b-versatile',
                'mixtral-8x7b-32768',
                'gemma2-9b-it',
            ],
            'limits' => [
                'request_limit' => env('GROQ_REQUEST_LIMIT', 14400),
                'token_limit' => env('GROQ_TOKEN_LIMIT', 10000000),
            ],
            'default_max_tokens' => 600,
            'storage_key' => 'groq',
            'supports_streaming' => true,
        ],

        'adacode' => [
            'name' => 'AdaCode.ai',
            'base_url' => 'https://api.adacode.ai/v1',
            'adapter' => AdaCodeAdapter::class,
            'chat_model' => env('ADACODE_MODEL', 'claude-sonnet-4-6'),
            'allowed_models' => [
                'claude-sonnet-4-6',
                'claude-haiku-4-5',
                'gpt-4o-mini',
                'gpt-4o',
            ],
            'limits' => [
                'request_limit' => env('ADACODE_REQUEST_LIMIT', 1000),
                'token_limit' => env('ADACODE_TOKEN_LIMIT', 1000000),
            ],
            'default_max_tokens' => 600,
            'storage_key' => 'adacode',
            'supports_streaming' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Policy
    |--------------------------------------------------------------------------
    |
    | Applied to every provider via BaseOpenAiCompatibleAdapter. Only retries
    | idempotent failures (429, 5xx, network timeouts). 4xx (other than 429)
    | and billing errors short-circuit immediately.
    |
    */

    'retry' => [
        'max_attempts' => env('AI_RETRY_MAX_ATTEMPTS', 2),
        'initial_backoff_ms' => env('AI_RETRY_BACKOFF_MS', 400),
        'backoff_multiplier' => 2.0,
        'max_backoff_ms' => 4000,
        'jitter_ms' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming
    |--------------------------------------------------------------------------
    |
    | SSE chunk delivery cadence. The adapter emits chunks as they arrive from
    | the upstream; this just controls the per-chunk flush so the client sees
    | progressive rendering.
    |
    */

    'streaming' => [
        'flush_every_n_chunks' => 1,
        'idle_timeout_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature-Provider Overrides
    |--------------------------------------------------------------------------
    |
    | Each AI feature (chat, journal, quote) can be routed to a different
    | provider. The 'default' key is used when a feature-specific override is
    | not configured.
    |
    */

    'feature_providers' => [
        'chat' => env('AI_CHAT_PROVIDER'),
        'journal' => env('AI_JOURNAL_PROVIDER'),
        'quote' => env('AI_QUOTE_PROVIDER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal Provider Override (legacy)
    |--------------------------------------------------------------------------
    */

    'journal_provider' => env('AI_JOURNAL_PROVIDER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | Legacy Top-Level Limit (back-compat for AiQuotaService pre-refactor)
    |--------------------------------------------------------------------------
    */

    'request_limit' => env('AI_REQUEST_LIMIT', 14400),
];
