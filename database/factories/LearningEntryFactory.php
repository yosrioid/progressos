<?php

namespace Database\Factories;

use App\Models\LearningEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-45 days')->format('Y-m-d'),
            'topic' => fake()->randomElement(['Laravel architecture', 'React performance', 'English writing', 'Japanese listening']),
            'category' => fake()->randomElement(LearningEntry::CATEGORIES),
            'source_type' => fake()->randomElement(LearningEntry::SOURCE_TYPES),
            'duration_minutes' => fake()->numberBetween(20, 120),
            'progress_notes' => fake()->paragraph(),
            'takeaway' => fake()->sentence(),
            'next_action' => fake()->sentence(),
            'rating' => fake()->numberBetween(3, 5),
        ];
    }
}
