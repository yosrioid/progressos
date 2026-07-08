<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | This file holds default provider settings. Actual values are stored
    | in the Configuration model (group 'ai', key 'provider_config').
    |
    */

    'default_provider' => env('AI_PROVIDER', 'groq'),

    'providers' => [
        'groq' => [
            'name' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1',
            'chat_model' => env('GROQ_CHAT_MODEL', 'llama-3.1-8b-instant'),
            'journal_model' => env('GROQ_JOURNAL_MODEL', 'llama-3.3-70b-versatile'),
            'api_key_env' => 'GROQ_API_KEY',
        ],

        'adacode' => [
            'name' => 'AdaCode.ai',
            'base_url' => 'https://api.adacode.ai/v1',
            'chat_model' => env('ADACODE_MODEL', 'claude-sonnet-4-6'),
            'api_key_env' => 'ADACODE_API_KEY',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal Provider Override
    |--------------------------------------------------------------------------
    |
    | Journaling always uses Groq regardless of the selected provider.
    |
    */

    'journal_provider' => 'groq',

    /*
    |--------------------------------------------------------------------------
    | Usage Limits
    |--------------------------------------------------------------------------
    */

    'request_limit' => env('AI_REQUEST_LIMIT', 14400),
];
