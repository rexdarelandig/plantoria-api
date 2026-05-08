<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;

class PlantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Plant $plant): bool
    {
        return $user->id === $plant->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Plant $plant): bool
    {
        return $user->id === $plant->user_id;
    }

    public function delete(User $user, Plant $plant): bool
    {
        return $user->id === $plant->user_id;
    }
}
