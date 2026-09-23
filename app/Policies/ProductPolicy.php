<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.view') || $user->can('stock.manage');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('stock.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('stock.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('stock.manage');
    }

    public function adjust(User $user, Product $product): bool
    {
        return $user->can('stock.manage');
    }
}
