<?php

namespace App\Policies;

use App\Models\Leave;
use App\Models\User;

class LeavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leaves.view') || $user->can('leaves.manage');
    }

    public function view(User $user, Leave $leave): bool
    {
        return $this->viewAny($user) && $this->sameCompany($user, $leave);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Leave $leave): bool
    {
        return $user->can('leaves.manage') && $this->sameCompany($user, $leave);
    }

    public function delete(User $user, Leave $leave): bool
    {
        return $this->update($user, $leave);
    }

    private function sameCompany(User $user, Leave $leave): bool
    {
        if ($user->company_id === null) {
            return true;
        }

        return $leave->company_id === $user->company_id;
    }
}
