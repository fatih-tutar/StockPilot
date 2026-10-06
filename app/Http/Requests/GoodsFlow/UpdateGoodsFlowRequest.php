<?php

namespace App\Http\Requests\GoodsFlow;

use App\Models\GoodsFlow;

class UpdateGoodsFlowRequest extends StoreGoodsFlowRequest
{
    public function authorize(): bool
    {
        $flow = $this->route('goods_flow');

        return $flow instanceof GoodsFlow && ($this->user()?->can('update', $flow) ?? false);
    }

    protected function ignoreId(): ?int
    {
        $flow = $this->route('goods_flow');

        return $flow instanceof GoodsFlow ? $flow->id : null;
    }
}
