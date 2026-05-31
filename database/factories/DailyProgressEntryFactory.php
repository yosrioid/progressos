<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyProgressEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-30 days')->format('Y-m-d'),
            'title' => fake()->sentence(4),
            'in_progress' => fake()->paragraph(),
            'todo' => fake()->paragraph(),
            'blockers' => fake()->boolean(25) ? fake()->sentence() : null,
            'notes' => fake()->paragraph(),
            'completed_items' => [fake()->sentence(), fake()->sentence()],
            'mood' => fake()->randomElement(['focused', 'steady', 'blocked', 'energized']),
            'archived' => false,
        ];
    }
}
