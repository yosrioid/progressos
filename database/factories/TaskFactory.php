<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(Task::STATUSES);

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(5),
            'notes' => fake()->optional()->paragraph(),
            'status' => $status,
            'priority' => fake()->randomElement(Task::PRIORITIES),
            'due_date' => fake()->boolean(75) ? fake()->dateTimeBetween('-5 days', '+14 days')->format('Y-m-d') : null,
            'completed_at' => $status === 'done' ? now() : null,
        ];
    }
}
