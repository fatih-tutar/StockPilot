<?php

namespace App\Policies;

use App\Models\GoodsFlow;
use App\Models\User;

class GoodsFlowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('goods_flows.view') || $user->can('goods_flows.manage');
    }

    public function view(User $user, GoodsFlow $goodsFlow): bool
    {
        return $this->viewAny($user) && $this->sameCompany($user, $goodsFlow);
    }

    public function create(User $user): bool
    {
        return $user->can('goods_flows.manage');
    }

    public function update(User $user, GoodsFlow $goodsFlow): bool
    {
        return $user->can('goods_flows.manage') && $this->sameCompany($user, $goodsFlow);
    }

    public function delete(User $user, GoodsFlow $goodsFlow): bool
    {
        return $this->update($user, $goodsFlow);
    }

    private function sameCompany(User $user, GoodsFlow $goodsFlow): bool
    {
        if ($user->company_id === null) {
            return true;
        }

        return $goodsFlow->company_id === $user->company_id;
    }
}
