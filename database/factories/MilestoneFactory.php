<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MilestoneFactory extends Factory
{
    public function definition(): array
    {
        $target = fake()->numberBetween(10, 100);

        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement(['Ship ProgressOS MVP', 'Log 40 focused work sessions', 'Study 30 hours this month']),
            'category' => fake()->randomElement(['product', 'career', 'learning', 'health']),
            'target_type' => fake()->randomElement(Milestone::TARGET_TYPES),
            'source_type' => 'manual',
            'source_filter' => null,
            'target_value' => $target,
            'current_value' => fake()->numberBetween(0, $target),
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(4)->toDateString(),
            'status' => fake()->randomElement(['active', 'active', 'paused', 'completed']),
            'notes' => fake()->paragraph(),
        ];
    }
}
