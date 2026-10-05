<?php

namespace App\Http\Controllers;

use App\Actions\Stock\AdjustProductStock;
use App\Enums\FactoryOrderDestination;
use App\Enums\FactoryOrderStatus;
use App\Http\Requests\FactoryOrder\ReceiveFactoryOrderRequest;
use App\Http\Requests\FactoryOrder\StoreFactoryOrderRequest;
use App\Http\Requests\FactoryOrder\UpdateFactoryOrderRequest;
use App\Models\Factory;
use App\Models\FactoryOrder;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FactoryOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FactoryOrder::class);

        $factoryId = $request->integer('factory_id') ?: null;
        $status = $request->string('status')->toString();
        $printed = $request->string('printed')->toString();
        $search = $request->string('search')->trim()->toString();

        $orders = $this->filteredOrders($factoryId, $status, $printed, $search);
        $pieces = (clone $orders)->sum('quantity');
        $kilos = (clone $orders)
            ->leftJoin('products', 'products.id', '=', 'factory_orders.product_id')
            ->sum(DB::raw('factory_orders.quantity * coalesce(products.unit_weight_kg, 0)'));

        $page = $orders
            ->with(['sourceFactory:id,name', 'preparedBy:id,name', 'product:id,unit_weight_kg', 'form:id'])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FactoryOrder $order) => $this->orderPayload($order));

        return Inertia::render('FactoryOrders/Index', [
            'orders' => $page,
            'filters' => [
                'factory_id' => $factoryId,
                'status' => $status,
                'printed' => $printed,
                'search' => $search,
            ],
            'totals' => [
                'pieces' => (int) $pieces,
                'kilos' => round((float) $kilos, 3),
            ],
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => FactoryOrderStatus::options(),
            'destinations' => FactoryOrderDestination::options(),
            'canManage' => $request->user()->can('factory_orders.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', FactoryOrder::class);

        return Inertia::render('FactoryOrders/Form', $this->formProps($request));
    }

    public function store(StoreFactoryOrderRequest $request): RedirectResponse
    {
        $order = FactoryOrder::query()->create($this->orderAttributes($request->validated(), $request->user()));

        return redirect()
            ->route('factory-orders.index', ['factory_id' => $order->factory_id])
            ->with('success', 'Fabrika siparişi eklendi.');
    }

    public function edit(Request $request, FactoryOrder $factoryOrder): Response
    {
        $this->authorize('update', $factoryOrder);

        return Inertia::render('FactoryOrders/Form', [
            ...$this->formProps($request, $factoryOrder),
            'order' => $this->orderPayload($factoryOrder->load(['sourceFactory:id,name', 'preparedBy:id,name', 'product:id,name,factory_id'])),
        ]);
    }

    public function update(UpdateFactoryOrderRequest $request, FactoryOrder $factoryOrder): RedirectResponse
    {
        $factoryOrder->update($this->orderAttributes($request->validated(), $request->user(), $factoryOrder));

        return redirect()
            ->route('factory-orders.index', ['factory_id' => $factoryOrder->factory_id])
            ->with('success', 'Fabrika siparişi güncellendi.');
    }

    public function destroy(FactoryOrder $factoryOrder): RedirectResponse
    {
        $this->authorize('delete', $factoryOrder);

        $factoryId = $factoryOrder->factory_id;
        $factoryOrder->delete();

        return redirect()
            ->route('factory-orders.index', ['factory_id' => $factoryId])
            ->with('success', 'Fabrika siparişi silindi.');
    }

    public function receive(
        ReceiveFactoryOrderRequest $request,
        FactoryOrder $factoryOrder,
        AdjustProductStock $adjustProductStock,
    ): RedirectResponse {
        if ($factoryOrder->status === FactoryOrderStatus::Received) {
            return back()->with('error', 'Bu sipariş zaten teslim alınmış.');
        }

        $product = $factoryOrder->product;
        if ($product === null) {
            return back()->with('error', 'Bu sipariş bir ürüne bağlı değil.');
        }

        $quantity = (int) $request->validated('quantity');
        $destination = FactoryOrderDestination::from($request->validated('destination'));

        if ($destination === FactoryOrderDestination::Warehouse) {
            $this->receiveIntoWarehouse($product, $quantity, $request->user());
        } else {
            $adjustProductStock->handle(
                $product,
                [
                    'quantity_piece_delta' => $destination === FactoryOrderDestination::Piece ? $quantity : 0,
                    'quantity_pallet_delta' => $destination === FactoryOrderDestination::Pallet ? $quantity : 0,
                    'type' => StockMovement::TYPE_IN,
                    'note' => 'Fabrika siparişi teslim alındı',
                ],
                $request->user(),
            );
        }

        $factoryOrder->update(['status' => FactoryOrderStatus::Received]);

        return back()->with('success', 'Sipariş teslim alındı ve stok güncellendi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Request $request, ?FactoryOrder $order = null): array
    {
        $search = $request->string('product')->trim()->toString();
        $selectedId = $request->integer('product_id') ?: $order?->product_id;

        $products = Product::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }, function ($query) use ($selectedId) {
                $query->whereKey($selectedId ?: 0);
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'sku', 'factory_id']);

        if ($selectedId && $products->doesntContain('id', $selectedId)) {
            $selected = Product::query()->find($selectedId, ['id', 'name', 'sku', 'factory_id']);
            if ($selected !== null) {
                $products->prepend($selected);
            }
        }

        return [
            'order' => null,
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'products' => $products,
            'selectedFactoryId' => $products->firstWhere('id', $selectedId)?->factory_id,
            'filters' => [
                'product' => $search,
                'product_id' => $selectedId,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function orderAttributes(array $data, User $user, ?FactoryOrder $order = null): array
    {
        $product = Product::query()->findOrFail($data['product_id']);

        return [
            'factory_id' => $data['factory_id'],
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $data['quantity'],
            'length' => $data['length'] ?? null,
            'pallet_count' => $data['pallet_count'] ?? 0,
            'due_on' => $data['due_on'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'prepared_by_user_id' => $data['prepared_by_user_id'] ?? $order?->prepared_by_user_id ?? $user->id,
            'status' => $order?->status ?? FactoryOrderStatus::Open,
        ];
    }

    private function receiveIntoWarehouse(Product $product, int $quantity, User $user): void
    {
        DB::transaction(function () use ($product, $quantity, $user): void {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'warehouse_quantity' => $locked->warehouse_quantity + $quantity,
            ]);

            StockMovement::query()->create([
                'product_id' => $locked->id,
                'user_id' => $user->id,
                'type' => StockMovement::TYPE_IN,
                'quantity_piece_delta' => 0,
                'quantity_pallet_delta' => 0,
                'note' => 'Depo stoğuna '.$quantity.' adet',
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(FactoryOrder $order): array
    {
        $weight = $order->product?->unit_weight_kg;

        return [
            'id' => $order->id,
            'factory_id' => $order->factory_id,
            'product_id' => $order->product_id,
            'factory_order_form_id' => $order->factory_order_form_id,
            'product_name' => $order->product_name,
            'quantity' => $order->quantity,
            'length' => $order->length,
            'pallet_count' => $order->pallet_count,
            'due_on' => $order->due_on?->toDateString(),
            'contact_name' => $order->contact_name,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'estimated_kg' => $weight === null ? null : round((float) $weight * $order->quantity, 3),
            'factory' => $order->sourceFactory?->only(['id', 'name']),
            'prepared_by' => $order->preparedBy?->only(['id', 'name']),
            'prepared_by_user_id' => $order->prepared_by_user_id,
        ];
    }

    private function filteredOrders(?int $factoryId, string $status, string $printed, string $search): Builder
    {
        return FactoryOrder::query()
            ->when($factoryId, fn ($query) => $query->where('factory_id', $factoryId))
            ->when(
                in_array($status, [FactoryOrderStatus::Open->value, FactoryOrderStatus::Received->value], true),
                fn ($query) => $query->where('status', $status),
            )
            ->when($printed === 'no', fn ($query) => $query->whereNull('factory_order_form_id'))
            ->when($printed === 'yes', fn ($query) => $query->whereNotNull('factory_order_form_id'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('product_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%");
                });
            });
    }
}
