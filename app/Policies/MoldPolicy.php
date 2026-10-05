<?php

namespace App\Policies;

use App\Models\Mold;
use App\Models\User;

class MoldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('molds.view') || $user->can('molds.manage');
    }

    public function view(User $user, Mold $mold): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('molds.manage');
    }

    public function update(User $user, Mold $mold): bool
    {
        return $user->can('molds.manage');
    }

    public function delete(User $user, Mold $mold): bool
    {
        return $user->can('molds.manage');
    }
}
