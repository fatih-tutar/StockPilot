<?php

namespace App\Policies;

use App\Models\OrganizationMember;
use App\Models\User;

class OrganizationMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('organizations.view') || $user->can('organizations.manage');
    }

    public function view(User $user, OrganizationMember $organizationMember): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('organizations.manage');
    }

    public function update(User $user, OrganizationMember $organizationMember): bool
    {
        return $user->can('organizations.manage');
    }
}
