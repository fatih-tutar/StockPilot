<?php

namespace App\Http\Controllers;

use App\Enums\CustomOrderStatus;
use App\Enums\DeliveryMethod;
use App\Http\Requests\CustomOrders\StoreCustomOrderRequest;
use App\Http\Requests\CustomOrders\UpdateCustomOrderRequest;
use App\Models\Client;
use App\Models\CustomOrder;
use App\Models\CustomOrderItem;
use App\Models\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomOrder::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        if ($status === '') {
            $status = CustomOrderStatus::Open->value;
        }

        $orders = CustomOrder::query()
            ->with([
                'client:id,name',
                'items.sourceFactory:id,name',
            ])
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items', function ($itemQuery) use ($search) {
                            $itemQuery->where('product_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('ordered_at')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CustomOrder $order) => $this->orderPayload($order));

        return Inertia::render('CustomOrders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Tümü'],
                ...CustomOrderStatus::options(),
            ],
            'canManage' => $request->user()->can('custom_orders.manage'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', CustomOrder::class);

        return Inertia::render('CustomOrders/Form', [
            'order' => null,
            'clients' => $this->clientOptions(),
            'factories' => $this->factoryOptions(),
            'deliveryOptions' => DeliveryMethod::options(),
            'statusOptions' => CustomOrderStatus::options(),
            'canManage' => true,
        ]);
    }

    public function store(StoreCustomOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($request, $data) {
            $order = CustomOrder::query()->create([
                'client_id' => $data['client_id'],
                'user_id' => $request->user()->id,
                'delivery_method' => $data['delivery_method'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'ordered_at' => $data['ordered_at'],
            ]);

            $this->syncItems($order, $data['items']);

            return $order;
        });

        return redirect()
            ->route('custom-orders.edit', $order)
            ->with('success', 'Özel sipariş oluşturuldu.');
    }

    public function edit(Request $request, CustomOrder $customOrder): Response
    {
        $this->authorize('view', $customOrder);

        $customOrder->load(['items.sourceFactory:id,name', 'client:id,name']);

        return Inertia::render('CustomOrders/Form', [
            'order' => $this->orderPayload($customOrder, detailed: true),
            'clients' => $this->clientOptions(),
            'factories' => $this->factoryOptions(),
            'deliveryOptions' => DeliveryMethod::options(),
            'statusOptions' => CustomOrderStatus::options(),
            'canManage' => $request->user()->can('custom_orders.manage'),
        ]);
    }

    public function update(UpdateCustomOrderRequest $request, CustomOrder $customOrder): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($customOrder, $data) {
            $customOrder->update([
                'client_id' => $data['client_id'],
                'delivery_method' => $data['delivery_method'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'ordered_at' => $data['ordered_at'],
            ]);

            $this->syncItems($customOrder, $data['items']);
        });

        return redirect()
            ->route('custom-orders.edit', $customOrder)
            ->with('success', 'Özel sipariş güncellendi.');
    }

    public function destroy(CustomOrder $customOrder): RedirectResponse
    {
        $this->authorize('delete', $customOrder);

        $customOrder->delete();

        return redirect()
            ->route('custom-orders.index')
            ->with('success', 'Özel sipariş silindi.');
    }

    public function close(CustomOrder $customOrder): RedirectResponse
    {
        $this->authorize('update', $customOrder);

        $customOrder->update(['status' => CustomOrderStatus::Closed]);

        return redirect()
            ->back()
            ->with('success', 'Sipariş kapatıldı.');
    }

    public function reopen(CustomOrder $customOrder): RedirectResponse
    {
        $this->authorize('update', $customOrder);

        $customOrder->update(['status' => CustomOrderStatus::Open]);

        return redirect()
            ->back()
            ->with('success', 'Sipariş yeniden açıldı.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(CustomOrder $order, array $items): void
    {
        $keptIds = [];

        foreach (array_values($items) as $item) {
            $payload = [
                'product_name' => $item['product_name'],
                'length' => $item['length'] ?? null,
                'factory_id' => $item['factory_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'due_on' => $item['due_on'] ?? null,
            ];

            if (! empty($item['id'])) {
                $existing = $order->items()->whereKey($item['id'])->first();
                if ($existing instanceof CustomOrderItem) {
                    $existing->update($payload);
                    $keptIds[] = $existing->id;

                    continue;
                }
            }

            $created = $order->items()->create($payload);
            $keptIds[] = $created->id;
        }

        $order->items()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(CustomOrder $order, bool $detailed = false): array
    {
        $payload = [
            'id' => $order->id,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'delivery_method' => $order->delivery_method->value,
            'delivery_label' => $order->delivery_method->label(),
            'notes' => $order->notes,
            'ordered_at' => $order->ordered_at?->format('Y-m-d\TH:i'),
            'ordered_on' => $order->ordered_at?->toDateString(),
            'client' => [
                'id' => $order->client?->id,
                'name' => $order->client?->name,
            ],
            'items' => $order->items->map(fn (CustomOrderItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'length' => $item->length,
                'factory_id' => $item->factory_id,
                'factory_name' => $item->sourceFactory?->name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'due_on' => $item->due_on?->toDateString(),
            ])->all(),
        ];

        if ($detailed) {
            $payload['client_id'] = $order->client_id;
        }

        return $payload;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function clientOptions(): array
    {
        return Client::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function factoryOptions(): array
    {
        return Factory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Factory $factory) => [
                'id' => $factory->id,
                'name' => $factory->name,
            ])
            ->all();
    }
}
