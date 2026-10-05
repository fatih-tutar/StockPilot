<?php

namespace App\Http\Controllers;

use App\Http\Requests\FactoryOrder\StoreFactoryOrderFormRequest;
use App\Models\Factory;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderForm;
use App\Models\MoldNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FactoryOrderFormController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FactoryOrderForm::class);

        $factoryId = $request->integer('factory_id') ?: null;

        $forms = FactoryOrderForm::query()
            ->with(['sourceFactory:id,name', 'preparedBy:id,name'])
            ->withCount('orders')
            ->when($factoryId, fn ($query) => $query->where('factory_id', $factoryId))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FactoryOrderForm $form) => [
                'id' => $form->id,
                'contact_name' => $form->contact_name,
                'orders_count' => $form->orders_count,
                'created_at' => $form->created_at?->timezone(config('app.timezone'))->format('d.m.Y'),
                'factory' => $form->sourceFactory?->only(['id', 'name']),
                'prepared_by' => $form->preparedBy?->only(['id', 'name']),
            ]);

        return Inertia::render('FactoryOrders/Forms', [
            'forms' => $forms,
            'filters' => [
                'factory_id' => $factoryId,
            ],
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('factory_orders.manage'),
        ]);
    }

    public function store(StoreFactoryOrderFormRequest $request): RedirectResponse
    {
        $factoryId = (int) $request->validated('factory_id');
        $lines = FactoryOrder::query()
            ->where('factory_id', $factoryId)
            ->whereNull('factory_order_form_id')
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return back()->with('error', 'Bu fabrikada forma girmemiş sipariş yok.');
        }

        $latest = $lines->sortByDesc('id')->first();

        $form = DB::transaction(function () use ($factoryId, $lines, $latest) {
            $form = FactoryOrderForm::query()->create([
                'factory_id' => $factoryId,
                'prepared_by_user_id' => $latest?->prepared_by_user_id,
                'contact_name' => $latest?->contact_name,
            ]);

            FactoryOrder::query()
                ->whereIn('id', $lines->modelKeys())
                ->update(['factory_order_form_id' => $form->id]);

            return $form;
        });

        return redirect()
            ->route('factory-order-forms.show', $form)
            ->with('success', 'Sipariş formu oluşturuldu.');
    }

    public function show(FactoryOrderForm $factoryOrderForm): Response
    {
        $this->authorize('view', $factoryOrderForm);

        $factoryOrderForm->load([
            'sourceFactory:id,name',
            'preparedBy:id,name',
            'company:id,name,letterhead',
            'orders' => fn ($query) => $query->orderBy('id'),
            'orders.product:id,name,pack_quantity,unit_weight_kg,category_id',
            'orders.product.category:id,name,parent_id',
            'orders.product.category.parent:id,name',
        ]);

        $moldNumbers = MoldNumber::query()
            ->where('factory_id', $factoryOrderForm->factory_id)
            ->whereIn('product_id', $factoryOrderForm->orders->pluck('product_id')->filter()->all())
            ->pluck('number', 'product_id');

        $lines = $factoryOrderForm->orders->values()->map(function (FactoryOrder $order, int $index) use ($moldNumbers) {
            $product = $order->product;
            $weight = $product?->unit_weight_kg;
            $child = $product?->category?->name;
            $parent = $product?->category?->parent?->name;
            $material = $order->product_name;
            if ($child || $parent) {
                $material .= ' '.trim(($child ?? '').($parent ? ' / '.$parent : ''));
            }

            return [
                'id' => $order->id,
                'number' => $index + 1,
                'mold_number' => $order->product_id === null ? null : $moldNumbers->get($order->product_id),
                'material' => $material,
                'length' => $order->length,
                'quantity' => $order->quantity,
                'pack_quantity' => $product?->pack_quantity,
                'pallet_count' => $order->pallet_count,
                'estimated_kg' => $weight === null ? null : round((float) $weight * $order->quantity, 3),
            ];
        });

        return Inertia::render('FactoryOrders/Print', [
            'form' => [
                'id' => $factoryOrderForm->id,
                'created_at' => $factoryOrderForm->created_at?->timezone(config('app.timezone'))->format('d.m.Y'),
                'contact_name' => $factoryOrderForm->contact_name,
                'factory' => $factoryOrderForm->sourceFactory?->only(['id', 'name']),
                'prepared_by' => $factoryOrderForm->preparedBy?->only(['id', 'name']),
                'company_name' => $factoryOrderForm->company?->name,
                'letterhead' => $factoryOrderForm->company?->letterhead,
                'lines' => $lines,
                'total_kg' => round((float) $lines->sum('estimated_kg'), 3),
            ],
            'canManage' => request()->user()?->can('factory_orders.manage') ?? false,
        ]);
    }

    public function destroy(FactoryOrderForm $factoryOrderForm): RedirectResponse
    {
        $this->authorize('delete', $factoryOrderForm);

        $factoryId = $factoryOrderForm->factory_id;

        DB::transaction(function () use ($factoryOrderForm): void {
            $factoryOrderForm->orders()->delete();
            $factoryOrderForm->delete();
        });

        return redirect()
            ->route('factory-order-forms.index', ['factory_id' => $factoryId])
            ->with('success', 'Sipariş formu ve kalemleri silindi.');
    }
}
