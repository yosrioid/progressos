<?php

namespace App\Services\AiAdapters;

class GroqAdapter extends BaseOpenAiCompatibleAdapter
{
    protected static function baseUrl(): string
    {
        return 'https://api.groq.com/openai/v1';
    }

    protected static function providerName(): string
    {
        return 'groq';
    }

    protected static function defaultMaxTokens(): int
    {
        return 600;
    }
}
