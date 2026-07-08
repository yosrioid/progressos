<?php

namespace App\Services\AiAdapters;

class AdaCodeAdapter extends BaseOpenAiCompatibleAdapter
{
    protected static function baseUrl(): string
    {
        return 'https://api.adacode.ai/v1';
    }

    protected static function providerName(): string
    {
        return 'adacode';
    }
}
