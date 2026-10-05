<?php

namespace App\Policies;

use App\Models\FactoryOrderForm;
use App\Models\User;

class FactoryOrderFormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('factory_orders.view') || $user->can('factory_orders.manage');
    }

    public function view(User $user, FactoryOrderForm $factoryOrderForm): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('factory_orders.manage');
    }

    public function delete(User $user, FactoryOrderForm $factoryOrderForm): bool
    {
        return $user->can('factory_orders.manage');
    }
}
