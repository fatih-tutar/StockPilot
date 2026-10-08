<?php

namespace App\Policies;

use App\Models\OfferListEntry;
use App\Models\User;

class OfferListEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotes.view') || $user->can('quotes.manage');
    }

    public function view(User $user, OfferListEntry $offerListEntry): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('quotes.manage');
    }

    public function update(User $user, OfferListEntry $offerListEntry): bool
    {
        return $user->can('quotes.manage');
    }

    public function delete(User $user, OfferListEntry $offerListEntry): bool
    {
        return $user->can('quotes.manage');
    }
}
