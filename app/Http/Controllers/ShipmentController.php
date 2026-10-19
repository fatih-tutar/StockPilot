<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Requests\Shipments\UpdateShipmentRequest;
use App\Http\Requests\Shipments\UpdateShipmentStatusRequest;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Shipment::class);

        $search = $request->string('search')->trim()->toString();

        return Inertia::render('Shipments/Index', [
            'columns' => collect(ShipmentStatus::tabs())
                ->map(fn (ShipmentStatus $status) => [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'shipments' => $this->shipmentList($status, $search),
                ])
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
            ],
            'canManage' => $request->user()->can('shipments.manage'),
        ]);
    }

    public function archive(Request $request): Response
    {
        $this->authorize('viewAny', Shipment::class);

        $search = $request->string('search')->trim()->toString();

        return Inertia::render('Shipments/Archive', [
            'shipments' => $this->shipmentPage(ShipmentStatus::Archived, $search),
            'filters' => [
                'search' => $search,
            ],
            'canManage' => $request->user()->can('shipments.manage'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shipmentList(ShipmentStatus $status, string $search): array
    {
        return $this->shipmentQuery($status, $search)
            ->latest('ship_date')
            ->latest('id')
            ->get()
            ->map(fn (Shipment $shipment) => $this->cardPayload($shipment))
            ->all();
    }

    public function print(Request $request, Shipment $shipment): Response
    {
        $this->authorize('view', $shipment);

        $shipment->load([
            'client:id,name,phone,email,address',
            'vehicle:id,name',
            'user:id,name',
            'preparedByUser:id,name',
            'invoicedByUser:id,name',
            'items.product:id,name,shelf,category_id',
            'items.product.category:id,name,parent_id',
            'items.product.category.parent:id,name',
            'company:id,name,letterhead,logo',
        ]);

        $company = $shipment->company
            ?? Company::query()->find($request->user()->company_id);

        return Inertia::render('Shipments/Print', [
            'shipment' => $this->printPayload($shipment, $company),
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
                'client_id' => $data['client_id'],
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'user_id' => $request->user()->id,
                'status' => ShipmentStatus::Received,
                'ship_date' => now()->toDateString(),
                'ship_type' => $data['ship_type'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($shipment, $data['items']);
        });

        return redirect()
            ->route('shipments.index')
            ->with('success', 'Müşteri siparişi oluşturuldu.');
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

    public function updateStatus(UpdateShipmentStatusRequest $request, Shipment $shipment): RedirectResponse
    {
        $previous = $shipment->status;
        $target = ShipmentStatus::from($request->validated('status'));
        $updates = ['status' => $target];

        if ($previous === ShipmentStatus::Received && $target === ShipmentStatus::Preparing) {
            $updates['prepared_by'] = $request->user()->id;
        }

        if ($previous === ShipmentStatus::Preparing && $target === ShipmentStatus::Invoiced) {
            $updates['invoiced_by'] = $request->user()->id;
        }

        $shipment->update($updates);

        $message = match (true) {
            $previous === ShipmentStatus::Received && $target === ShipmentStatus::Preparing => 'Sipariş hazırlananlara taşındı.',
            $previous === ShipmentStatus::Preparing && $target === ShipmentStatus::Received => 'Sipariş alınanlara geri alındı.',
            $previous === ShipmentStatus::Preparing && $target === ShipmentStatus::Invoiced => 'Sipariş faturası kesilenlere taşındı.',
            $previous === ShipmentStatus::Invoiced && $target === ShipmentStatus::Preparing => 'Sipariş hazırlananlara geri alındı.',
            $previous === ShipmentStatus::Invoiced && $target === ShipmentStatus::Archived => 'Sipariş arşive gönderildi.',
            $previous === ShipmentStatus::Archived && $target === ShipmentStatus::Invoiced => 'Sipariş arşivden çıkarıldı.',
            default => 'Sipariş durumu güncellendi.',
        };

        return back()->with('success', $message);
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        $this->authorize('delete', $shipment);

        $fromArchive = $shipment->status === ShipmentStatus::Archived;

        $shipment->delete();

        if ($fromArchive) {
            return redirect()
                ->route('shipments.archive')
                ->with('success', 'Müşteri siparişi silindi.');
        }

        return redirect()
            ->route('shipments.index')
            ->with('success', 'Müşteri siparişi silindi.');
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function shipmentPage(ShipmentStatus $status, string $search)
    {
        return $this->shipmentQuery($status, $search)
            ->latest('ship_date')
            ->latest('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Shipment $shipment) => $this->cardPayload($shipment));
    }

    /**
     * @return Builder<Shipment>
     */
    private function shipmentQuery(ShipmentStatus $status, string $search)
    {
        return Shipment::query()
            ->with([
                'client:id,name',
                'vehicle:id,name',
                'user:id,name',
                'items.product:id,name,category_id',
                'items.product.category:id,name,parent_id',
                'items.product.category.parent:id,name',
            ])
            ->where('status', $status)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%");
                    })->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('license_plate', 'like', "%{$search}%");
                    })->orWhereHas('items.product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPayload(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'status' => $shipment->status->value,
            'ship_date' => $shipment->ship_date?->format('d.m.Y'),
            'ship_type' => $shipment->ship_type?->label(),
            'notes' => $shipment->notes,
            'creator_name' => $shipment->user?->name,
            'vehicle' => $shipment->vehicle?->name,
            'client' => [
                'id' => $shipment->client?->id,
                'name' => $shipment->client?->name,
            ],
            'items' => $shipment->items->map(fn (ShipmentItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name,
                'kind' => $item->product?->category?->parent?->name
                    ?? $item->product?->category?->name,
                'quantity_piece' => $item->quantity_piece,
                'unit_price' => $item->unit_price,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function printPayload(Shipment $shipment, ?Company $company): array
    {
        return [
            'id' => $shipment->id,
            'ship_date' => $shipment->ship_date?->format('d.m.Y'),
            'created_time' => $shipment->created_at?->format('H:i'),
            'ship_type' => $shipment->ship_type?->label(),
            'notes' => $shipment->notes,
            'creator_name' => $shipment->user?->name,
            'preparer_name' => $shipment->preparedByUser?->name,
            'invoicer_name' => $shipment->invoicedByUser?->name,
            'client' => [
                'name' => $shipment->client?->name,
                'address' => $shipment->client?->address,
                'phone' => $shipment->client?->phone,
                'email' => $shipment->client?->email,
            ],
            'company' => [
                'name' => $company?->name,
                'letterhead' => $company?->letterhead,
                'logo' => $company?->logo,
            ],
            'items' => $shipment->items->map(function (ShipmentItem $item) {
                $category = $item->product?->category;

                return [
                    'product_name' => $item->product?->name,
                    'kind' => $category?->parent?->name ?? $category?->name,
                    'type' => $category?->parent_id ? $category->name : null,
                    'shelf' => $item->product?->shelf,
                    'quantity_piece' => $item->quantity_piece,
                    'unit_price' => $item->unit_price,
                ];
            })->values(),
        ];
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
