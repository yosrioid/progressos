<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('role', 'admin')->exists()) {
            $this->command->info('Admin user already exists, skipping AdminSeeder.');

            return;
        }

        User::create([
            'name' => config('app.admin_name', 'Admin'),
            'email' => config('app.admin_email', 'admin@progressos.local'),
            'password' => Hash::make((string) config('app.admin_password', 'changeme123')),
            'role' => 'admin',
            'timezone' => 'Asia/Jakarta',
            'theme' => 'system',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin user created: '.config('app.admin_email', 'admin@progressos.local'));
    }
}
