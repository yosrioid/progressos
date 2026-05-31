<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;

class ProjectResolver
{
    public function resolve(User $user, ?string $name): ?Project
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => $name],
            ['color' => collect(['teal', 'sky', 'amber', 'rose', 'violet'])->random()]
        );
    }
}
