<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-45 days')->format('Y-m-d'),
            'project_name' => fake()->randomElement(['ProgressOS', 'Client Portal', 'Learning Lab']),
            'ticket_code' => fake()->optional()->bothify('PO-###'),
            'title' => fake()->sentence(5),
            'category' => fake()->randomElement(WorkLog::CATEGORIES),
            'status' => fake()->randomElement(WorkLog::STATUSES),
            'priority' => fake()->randomElement(WorkLog::PRIORITIES),
            'description' => fake()->paragraph(),
            'resolution_or_outcome' => fake()->optional()->paragraph(),
            'estimated_duration' => fake()->numberBetween(30, 240),
            'actual_duration' => fake()->numberBetween(20, 300),
        ];
    }
}
