<?php

namespace App\Policies;

use App\Models\CatalogItem;
use App\Models\User;

class CatalogItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.view') || $user->can('catalog.manage');
    }

    public function view(User $user, CatalogItem $catalogItem): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, CatalogItem $catalogItem): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, CatalogItem $catalogItem): bool
    {
        return $user->can('catalog.manage');
    }
}
