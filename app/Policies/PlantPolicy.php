<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;

class PlantPolicy
{
    public function view(User $user, Plant $plant): bool
    {
        return $user->isAdmin() || $user->hasRole('supervisor') || $plant->user_id === $user->id;
    }

    public function update(User $user, Plant $plant): bool
    {
        return $user->isAdmin() || $plant->user_id === $user->id;
    }

    public function delete(User $user, Plant $plant): bool
    {
        return $user->isAdmin() || $plant->user_id === $user->id;
    }
}
