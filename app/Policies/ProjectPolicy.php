<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

// A project and its tasks belong to the user who created it.
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->user()->is($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->user()->is($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->user()->is($user);
    }
}
