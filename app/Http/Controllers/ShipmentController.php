<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Requests\Shipments\UpdateShipmentRequest;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
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
            ->with(['client:id,name', 'vehicle:id,name,license_plate', 'quote:id,number'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                            $vehicleQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('license_plate', 'like', "%{$search}%");
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
                'vehicle' => $shipment->vehicle ? [
                    'id' => $shipment->vehicle->id,
                    'name' => $shipment->vehicle->name,
                    'license_plate' => $shipment->vehicle->license_plate,
                ] : null,
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
            ...$this->formOptions(),
            'canManage' => true,
        ]);
    }

    public function createData(): JsonResponse
    {
        $this->authorize('create', Shipment::class);

        return response()->json($this->formOptions());
    }

    public function store(StoreShipmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data): void {
            $shipment = Shipment::query()->create([
                'number' => Shipment::nextNumber(),
                'client_id' => $data['client_id'],
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'user_id' => $request->user()->id,
                'status' => ShipmentStatus::Scheduled,
                'ship_date' => now()->toDateString(),
                'ship_type' => $data['ship_type'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($shipment, $data['items']);
        });

        return back()->with('success', 'Müşteri siparişi oluşturuldu.');
    }

    public function edit(Request $request, Shipment $shipment): Response
    {
        $this->authorize('view', $shipment);

        return Inertia::render('Shipments/Form', $this->editorPayload($request, $shipment));
    }

    public function editor(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        return response()->json($this->editorPayload($request, $shipment));
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($shipment, $data): void {
            $shipment->update([
                'client_id' => $data['client_id'],
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'ship_type' => $data['ship_type'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($shipment, $data['items']);
        });

        return back()->with('success', 'Müşteri siparişi güncellendi.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        $this->authorize('delete', $shipment);

        $shipment->delete();

        return redirect()
            ->route('shipments.index')
            ->with('success', 'Müşteri siparişi silindi.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Shipment $shipment, array $items): void
    {
        $keptIds = [];

        foreach (array_values($items) as $index => $item) {
            $payload = [
                'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
                'quantity_piece' => (int) ($item['quantity_piece'] ?? 0),
                'quantity_pallet' => 0,
                'unit_price' => $item['unit_price'] ?? null,
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
     * @return array<string, mixed>
     */
    private function editorPayload(Request $request, Shipment $shipment): array
    {
        $shipment->load(['items.product:id,name,sku', 'client:id,name']);

        return [
            'shipment' => [
                'id' => $shipment->id,
                'number' => $shipment->number,
                'client_id' => $shipment->client_id,
                'client_name' => $shipment->client?->name,
                'ship_type' => $shipment->ship_type?->value,
                'vehicle_id' => $shipment->vehicle_id,
                'notes' => $shipment->notes,
                'items' => $shipment->items->map(fn (ShipmentItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'quantity_piece' => $item->quantity_piece,
                    'unit_price' => $item->unit_price,
                ])->values(),
            ],
            ...$this->formOptions(),
            'canManage' => $request->user()->can('shipments.manage'),
        ];
    }

    /**
     * @return array{vehicles: array<int, array{id: int, name: string}>, shipTypeOptions: array<int, array{value: string, label: string}>}
     */
    private function formOptions(): array
    {
        return [
            'vehicles' => Vehicle::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                ])
                ->all(),
            'shipTypeOptions' => ShipmentType::options(),
        ];
    }
}
