<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shipments.view') || $user->can('shipments.manage');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('shipments.manage');
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.manage');
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.manage');
    }
}
