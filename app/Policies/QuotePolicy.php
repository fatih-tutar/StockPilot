<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotes.view') || $user->can('quotes.manage');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('quotes.manage');
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->can('quotes.manage');
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->can('quotes.manage');
    }
}
