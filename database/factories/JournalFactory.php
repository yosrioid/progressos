<?php

namespace Database\Factories;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-6 months'),
            'body' => fake()->paragraphs(2, true),
            'mood' => fake()->randomElement(['positif', 'netral', 'negatif', 'lelah', 'senang']),
            'tema' => fake()->words(2, true),
            'analyzed_at' => null,
        ];
    }
}