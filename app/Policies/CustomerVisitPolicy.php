<?php

namespace App\Policies;

use App\Models\CustomerVisit;
use App\Models\User;

class CustomerVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('visits.view') || $user->can('visits.manage');
    }

    public function view(User $user, CustomerVisit $customerVisit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('visits.manage');
    }

    public function update(User $user, CustomerVisit $customerVisit): bool
    {
        return $user->can('visits.manage');
    }

    public function delete(User $user, CustomerVisit $customerVisit): bool
    {
        return $user->can('visits.manage');
    }
}
