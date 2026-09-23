<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Requests\Shipments\UpdateShipmentRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Shipment::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        $shipments = Shipment::query()
            ->with(['client:id,name', 'quote:id,number'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('vehicle_plate', 'like', "%{$search}%")
                        ->orWhere('driver_name', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('ship_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'number' => $shipment->number,
                'status' => $shipment->status->value,
                'status_label' => $shipment->status->label(),
                'ship_date' => $shipment->ship_date?->toDateString(),
                'delivery_date' => $shipment->delivery_date?->toDateString(),
                'vehicle_plate' => $shipment->vehicle_plate,
                'driver_name' => $shipment->driver_name,
                'client' => [
                    'id' => $shipment->client?->id,
                    'name' => $shipment->client?->name,
                ],
                'quote' => $shipment->quote ? [
                    'id' => $shipment->quote->id,
                    'number' => $shipment->quote->number,
                ] : null,
            ]);

        return Inertia::render('Shipments/Index', [
            'shipments' => $shipments,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => ShipmentStatus::options(),
            'canManage' => $request->user()->can('shipments.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Shipment::class);

        return Inertia::render('Shipments/Form', [
            'shipment' => null,
            'clients' => $this->clientOptions(),
            'quotes' => $this->quoteOptions(),
            'products' => $this->productOptions(),
            'statusOptions' => ShipmentStatus::options(),
            'canManage' => true,
        ]);
    }

    public function store(StoreShipmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $shipment = DB::transaction(function () use ($request, $data) {
            $shipment = Shipment::query()->create([
                'number' => Shipment::nextNumber(),
                'client_id' => $data['client_id'],
                'quote_id' => $data['quote_id'] ?? null,
                'user_id' => $request->user()->id,
                'status' => $data['status'],
                'ship_date' => $data['ship_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($shipment, $data['items']);

            return $shipment;
        });

        return redirect()
            ->route('shipments.edit', $shipment)
            ->with('success', 'Sevkiyat oluşturuldu.');
    }

    public function edit(Request $request, Shipment $shipment): Response
    {
        $this->authorize('view', $shipment);

        $shipment->load(['items.product:id,name,sku', 'client:id,name', 'quote:id,number']);

        return Inertia::render('Shipments/Form', [
            'shipment' => [
                'id' => $shipment->id,
                'number' => $shipment->number,
                'client_id' => $shipment->client_id,
                'quote_id' => $shipment->quote_id,
                'status' => $shipment->status->value,
                'ship_date' => $shipment->ship_date?->toDateString(),
                'delivery_date' => $shipment->delivery_date?->toDateString(),
                'vehicle_plate' => $shipment->vehicle_plate,
                'driver_name' => $shipment->driver_name,
                'shipping_address' => $shipment->shipping_address,
                'notes' => $shipment->notes,
                'items' => $shipment->items->map(fn (ShipmentItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity_piece' => $item->quantity_piece,
                    'quantity_pallet' => $item->quantity_pallet,
                ])->values(),
            ],
            'clients' => $this->clientOptions(),
            'quotes' => $this->quoteOptions($shipment->quote_id),
            'products' => $this->productOptions(),
            'statusOptions' => ShipmentStatus::options(),
            'canManage' => $request->user()->can('shipments.manage'),
        ]);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($shipment, $data) {
            $shipment->update([
                'client_id' => $data['client_id'],
                'quote_id' => $data['quote_id'] ?? null,
                'status' => $data['status'],
                'ship_date' => $data['ship_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($shipment, $data['items']);
        });

        return redirect()
            ->route('shipments.edit', $shipment)
            ->with('success', 'Sevkiyat güncellendi.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        $this->authorize('delete', $shipment);

        $shipment->delete();

        return redirect()
            ->route('shipments.index')
            ->with('success', 'Sevkiyat silindi.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Shipment $shipment, array $items): void
    {
        $keptIds = [];

        foreach (array_values($items) as $index => $item) {
            $payload = [
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity_piece' => (int) ($item['quantity_piece'] ?? 0),
                'quantity_pallet' => (int) ($item['quantity_pallet'] ?? 0),
                'sort_order' => $index,
            ];

            if (! empty($item['id'])) {
                $existing = $shipment->items()->whereKey($item['id'])->first();
                if ($existing) {
                    $existing->update($payload);
                    $keptIds[] = $existing->id;
                    continue;
                }
            }

            $created = $shipment->items()->create($payload);
            $keptIds[] = $created->id;
        }

        $shipment->items()->whereNotIn('id', $keptIds)->delete();
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
     * @return array<int, array{id: int, number: string, client_id: int}>
     */
    private function quoteOptions(?int $includeQuoteId = null): array
    {
        return Quote::query()
            ->when($includeQuoteId, function ($query) use ($includeQuoteId) {
                $query->where(function ($inner) use ($includeQuoteId) {
                    $inner->whereIn('status', ['draft', 'sent', 'accepted'])
                        ->orWhereKey($includeQuoteId);
                });
            }, function ($query) {
                $query->whereIn('status', ['draft', 'sent', 'accepted']);
            })
            ->orderByDesc('quote_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'number', 'client_id'])
            ->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'number' => $quote->number,
                'client_id' => $quote->client_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, sku: string|null}>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ])
            ->all();
    }
}
