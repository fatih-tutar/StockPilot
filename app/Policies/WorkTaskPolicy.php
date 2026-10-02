<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkTask;

class WorkTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('work_tasks.view') || $user->can('work_tasks.manage');
    }

    public function view(User $user, WorkTask $workTask): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('work_tasks.manage');
    }

    public function update(User $user, WorkTask $workTask): bool
    {
        return $user->can('work_tasks.manage');
    }

    public function delete(User $user, WorkTask $workTask): bool
    {
        return $user->can('work_tasks.manage');
    }
}
