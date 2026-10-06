<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoodsFlow\StoreGoodsFlowRequest;
use App\Http\Requests\GoodsFlow\UpdateGoodsFlowRequest;
use App\Models\GoodsFlow;
use App\Models\Product;
use App\Models\User;
use App\Support\GoodsFlowTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GoodsFlowController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', GoodsFlow::class);

        $flows = $this->flows($request->user());

        return Inertia::render('GoodsFlows/Index', [
            'rows' => GoodsFlowTimeline::rows($flows, $request->query('view') !== 'weekly'),
            'chart' => GoodsFlowTimeline::chart($flows),
            'weights' => $this->weights($request->user()->company_id),
            'weekly' => $request->query('view') === 'weekly',
            'canManage' => $request->user()->can('goods_flows.manage'),
        ]);
    }

    public function report(Request $request): Response
    {
        $this->authorize('viewAny', GoodsFlow::class);

        $company = $request->user()->company;

        return Inertia::render('GoodsFlows/Report', [
            'rows' => GoodsFlowTimeline::rows($this->flows($request->user()), false),
            'weights' => $this->weights($request->user()->company_id),
            'companyName' => $company?->name,
            'letterhead' => $company?->letterhead,
        ]);
    }

    public function store(StoreGoodsFlowRequest $request): RedirectResponse
    {
        GoodsFlow::query()->create($request->safe()->only([
            'recorded_on',
            'store_incoming',
            'store_outgoing',
            'warehouse_incoming',
            'warehouse_outgoing',
        ]));

        return redirect()
            ->route('goods-flows.index')
            ->with('success', 'Gün kaydedildi.');
    }

    public function update(UpdateGoodsFlowRequest $request, GoodsFlow $goodsFlow): RedirectResponse
    {
        $goodsFlow->update($request->safe()->only([
            'recorded_on',
            'store_incoming',
            'store_outgoing',
            'warehouse_incoming',
            'warehouse_outgoing',
        ]));

        return redirect()
            ->route('goods-flows.index')
            ->with('success', 'Gün güncellendi.');
    }

    public function destroy(GoodsFlow $goodsFlow): RedirectResponse
    {
        $this->authorize('delete', $goodsFlow);

        $goodsFlow->delete();

        return redirect()
            ->route('goods-flows.index')
            ->with('success', 'Gün silindi.');
    }

    /**
     * @return Collection<int, GoodsFlow>
     */
    private function flows(User $user)
    {
        return GoodsFlow::query()
            ->when($user->company_id !== null, fn ($query) => $query->where('company_id', $user->company_id))
            ->orderByDesc('recorded_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{store: string, warehouse: string, total: string}
     */
    private function weights(?int $companyId): array
    {
        $products = Product::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId));

        $store = (float) (clone $products)
            ->selectRaw('COALESCE(SUM(quantity_piece * unit_weight_kg), 0) as kg')
            ->value('kg');
        $warehouse = (float) (clone $products)
            ->selectRaw('COALESCE(SUM((warehouse_quantity + quantity_pallet) * unit_weight_kg), 0) as kg')
            ->value('kg');

        return [
            'store' => GoodsFlowTimeline::kilos($store),
            'warehouse' => GoodsFlowTimeline::kilos($warehouse),
            'total' => GoodsFlowTimeline::kilos($store + $warehouse),
        ];
    }
}
