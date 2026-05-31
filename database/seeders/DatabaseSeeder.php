<?php

namespace Database\Seeders;

use App\Models\DailyProgressEntry;
use App\Models\LearningEntry;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'ProgressOS Demo',
            'email' => 'test@example.com',
            'timezone' => 'Asia/Jakarta',
        ]);

        DailyProgressEntry::factory(18)->for($user)->create();
        WorkLog::factory(35)->for($user)->create();
        LearningEntry::factory(22)->for($user)->create();
        Milestone::factory(8)->for($user)->create();
        Task::factory(24)->for($user)->create();
    }
}
