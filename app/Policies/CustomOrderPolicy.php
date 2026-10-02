<?php

namespace App\Policies;

use App\Models\CustomOrder;
use App\Models\User;

class CustomOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('custom_orders.view') || $user->can('custom_orders.manage');
    }

    public function view(User $user, CustomOrder $customOrder): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('custom_orders.manage');
    }

    public function update(User $user, CustomOrder $customOrder): bool
    {
        return $user->can('custom_orders.manage');
    }

    public function delete(User $user, CustomOrder $customOrder): bool
    {
        return $user->can('custom_orders.manage');
    }
}
