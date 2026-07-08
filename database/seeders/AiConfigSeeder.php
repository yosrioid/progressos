<?php

namespace Database\Seeders;

use App\Models\Configuration;
use Illuminate\Database\Seeder;

class AiConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Set default AI provider configuration (global)
        Configuration::setValue(null, 'ai', 'provider_config', [
            'provider' => 'groq',
            'model' => 'claude-sonnet-4-6',
        ]);
    }
}
