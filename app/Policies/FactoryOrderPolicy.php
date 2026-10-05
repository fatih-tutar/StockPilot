<?php

namespace App\Policies;

use App\Models\FactoryOrder;
use App\Models\User;

class FactoryOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('factory_orders.view') || $user->can('factory_orders.manage');
    }

    public function view(User $user, FactoryOrder $factoryOrder): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('factory_orders.manage');
    }

    public function update(User $user, FactoryOrder $factoryOrder): bool
    {
        return $user->can('factory_orders.manage');
    }

    public function delete(User $user, FactoryOrder $factoryOrder): bool
    {
        return $user->can('factory_orders.manage');
    }
}
