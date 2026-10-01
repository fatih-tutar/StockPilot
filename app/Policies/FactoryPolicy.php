<?php

namespace App\Policies;

use App\Models\Factory;
use App\Models\User;

class FactoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('factories.view') || $user->can('factories.manage');
    }

    public function view(User $user, Factory $factory): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('factories.manage');
    }

    public function update(User $user, Factory $factory): bool
    {
        return $user->can('factories.manage');
    }

    public function delete(User $user, Factory $factory): bool
    {
        return $user->can('factories.manage');
    }
}
