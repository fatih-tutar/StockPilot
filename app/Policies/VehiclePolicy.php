<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vehicles.view') || $user->can('vehicles.manage');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('vehicles.manage');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.manage');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.manage');
    }
}
