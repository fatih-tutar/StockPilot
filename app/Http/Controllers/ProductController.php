<?php

namespace App\Http\Controllers;

use App\Actions\Stock\AdjustProductStock;
use App\Enums\FactoryOrderStatus;
use App\Enums\QuoteStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Enums\StockActivityPlace;
use App\Http\Requests\Catalog\AdjustStockRequest;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\Factory;
use App\Models\FactoryOrder;
use App\Models\MoldNumber;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Shipment;
use App\Models\StockActivity;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AccessRoles;
use App\Support\CategoryColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = $request->string('search')->trim()->toString();
        $categoryId = $request->integer('category_id') ?: null;
        $columns = AccessRoles::visibleColumns($request->user());

        $category = $categoryId === null
            ? null
            : Category::query()->with('parent')->find($categoryId);
        $sheet = $this->sheet($category, $request->user());

        $products = Product::query()
            ->with(['category:id,name', 'sourceFactory:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity_piece' => $columns['piece'] ? $product->quantity_piece : null,
                'quantity_pallet' => $columns['pallet'] ? $product->quantity_pallet : null,
                'low_stock_threshold' => $product->low_stock_threshold,
                'is_low_stock' => $product->isLowStock(),
                'is_active' => $product->is_active,
                'category' => $product->category?->only(['id', 'name']),
                'cells' => collect($sheet['columns'])->mapWithKeys(
                    fn (array $column) => [$column['name'] => CategoryColumns::listValue($product, $column['name'])],
                )->all(),
                'sale_price' => $product->sale_price,
                'default_order_quantity' => $product->default_order_quantity,
                'length_measure' => $product->length_measure,
                'factory_id' => $product->factory_id,
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
            ],
            'categories' => $this->categoryChoices($request->user()),
            'sheet' => $category === null ? null : $sheet,
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name']),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('stock.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Form', [
            'product' => null,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'fields' => [],
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name']),
            'staff' => [],
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $moldNumber = $data['mold_number'] ?? null;
        unset($data['mold_number']);
        $category = Category::query()->with('parent')->find($data['category_id']);
        $data = $this->writable($data, $category, $request->user());
        $data['quantity_piece'] = $data['quantity_piece'] ?? 0;
        $data['quantity_pallet'] = $data['quantity_pallet'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        $product = Product::query()->create($data);

        if ($moldNumber !== null && $product->factory_id !== null) {
            MoldNumber::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'factory_id' => $product->factory_id,
                ],
                ['number' => $moldNumber],
            );
        }

        return redirect()
            ->back()
            ->with('success', 'Ürün oluşturuldu.');
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);

        return Inertia::render('Products/Form', [
            ...$this->editorPayload($product, $request->user()),
            'activities' => $this->recentActivities($product, $request->user()),
        ]);
    }

    public function editor(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json($this->editorPayload($product, $request->user()));
    }

    public function activities(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json($this->recentActivities($product, $request->user()));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $hasMoldNumber = array_key_exists('mold_number', $data);
        $moldNumber = $data['mold_number'] ?? null;
        unset($data['mold_number']);

        $product->load('category.parent');
        $product->update($this->writable($data, $product->category, $request->user()));

        if ($hasMoldNumber && $product->factory_id !== null) {
            MoldNumber::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'factory_id' => $product->factory_id,
                ],
                ['number' => $moldNumber],
            );
        }

        return back()->with('success', 'Ürün güncellendi.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        if ($product->quantity_piece > 0 || $product->quantity_pallet > 0) {
            return back()->with('error', 'Ürünü silmeden önce stoğu sıfırlayın.');
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Ürün silindi.');
    }

    public function adjust(
        AdjustStockRequest $request,
        Product $product,
        AdjustProductStock $adjustProductStock,
    ): RedirectResponse {
        try {
            $adjustProductStock->handle(
                $product,
                [
                    'quantity_piece_delta' => (int) $request->input('quantity_piece_delta', 0),
                    'quantity_pallet_delta' => (int) $request->input('quantity_pallet_delta', 0),
                    'note' => $request->input('note'),
                    'type' => StockMovement::TYPE_ADJUSTMENT,
                ],
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'quantity_piece_delta' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Stok güncellendi.');
    }

    public function quote(Request $request, Product $product): RedirectResponse
    {
        $this->requireColumn($product, 'offer_button', $request->user());
        $this->authorize('create', Quote::class);

        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'quantity_piece' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ], [
            'client_name.required' => 'Müşteri adı zorunludur.',
            'quantity_piece.required' => 'Adet zorunludur.',
            'unit_price.required' => 'Fiyat zorunludur.',
        ]);

        $client = Client::query()->where('name', $data['client_name'])->first();
        if ($client === null) {
            throw ValidationException::withMessages([
                'client_name' => 'Bu isimde bir müşteri yok.',
            ]);
        }

        $weight = (float) ($product->unit_weight_kg ?? 0);
        $lineTotal = $weight > 0
            ? round($data['quantity_piece'] * $weight * (float) $data['unit_price'], 2)
            : round($data['quantity_piece'] * (float) $data['unit_price'], 2);

        DB::transaction(function () use ($request, $product, $client, $data, $lineTotal): void {
            $quote = Quote::query()->create([
                'number' => Quote::nextNumber(),
                'client_id' => $client->id,
                'user_id' => $request->user()->id,
                'status' => QuoteStatus::Draft,
                'quote_date' => now()->toDateString(),
                'currency' => 'TRY',
                'tax_rate' => 20,
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]);
            $quote->items()->create([
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity_piece' => $data['quantity_piece'],
                'quantity_pallet' => 0,
                'unit_price' => $data['unit_price'],
                'line_total' => $lineTotal,
                'sort_order' => 1,
            ]);
            $quote->recalculateTotals();
        });

        return back()->with('success', 'Teklif taslağı oluşturuldu.');
    }

    public function order(Request $request, Product $product): RedirectResponse
    {
        $this->requireColumn($product, 'order_button', $request->user());
        $this->authorize('create', FactoryOrder::class);

        $data = $request->validate([
            'factory_id' => ['required', 'integer', 'exists:factories,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'length' => ['nullable', 'string', 'max:64'],
            'pallet_count' => ['nullable', 'integer', 'min:0'],
            'due_on' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'prepared_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'factory_id.required' => 'Fabrika seçin.',
            'quantity.required' => 'Adet zorunludur.',
        ]);

        FactoryOrder::query()->create([
            'factory_id' => $data['factory_id'],
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $data['quantity'],
            'length' => $data['length'] ?? $product->length_measure,
            'pallet_count' => $data['pallet_count'] ?? 0,
            'due_on' => $data['due_on'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'prepared_by_user_id' => $data['prepared_by_user_id'] ?? $request->user()->id,
            'status' => FactoryOrderStatus::Open,
        ]);

        return back()->with('success', 'Fabrika siparişi eklendi.');
    }

    public function ship(Request $request, Product $product): RedirectResponse
    {
        $this->requireColumn($product, 'shipment_button', $request->user());
        $this->authorize('create', Shipment::class);

        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'quantity_piece' => ['required', 'integer', 'min:1'],
            'ship_type' => ['required', Rule::enum(ShipmentType::class)],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'vehicle_id' => ['nullable', 'integer', Rule::exists(Vehicle::class, 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'client_name.required' => 'Müşteri adı zorunludur.',
            'quantity_piece.required' => 'Adet zorunludur.',
            'ship_type.required' => 'Sevk tipi seçin.',
            'unit_price.required' => 'Fiyat zorunludur.',
        ]);

        $client = Client::query()->where('name', $data['client_name'])->first();
        if ($client === null) {
            throw ValidationException::withMessages([
                'client_name' => 'Bu isimde bir müşteri yok.',
            ]);
        }

        $vehicle = isset($data['vehicle_id'])
            ? Vehicle::query()->find($data['vehicle_id'])
            : null;

        DB::transaction(function () use ($request, $product, $client, $data, $vehicle): void {
            $shipment = Shipment::query()
                ->where('client_id', $client->id)
                ->where('status', ShipmentStatus::Scheduled)
                ->latest('id')
                ->first();

            if ($shipment === null) {
                $shipment = Shipment::query()->create([
                    'number' => Shipment::nextNumber(),
                    'client_id' => $client->id,
                    'user_id' => $request->user()->id,
                    'status' => ShipmentStatus::Scheduled,
                    'ship_date' => now()->toDateString(),
                    'ship_type' => $data['ship_type'],
                    'vehicle_plate' => $vehicle?->license_plate,
                    'driver_name' => $vehicle?->driver_name,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            $shipment->items()->create([
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity_piece' => $data['quantity_piece'],
                'quantity_pallet' => 0,
                'unit_price' => $data['unit_price'],
                'sort_order' => $shipment->items()->count() + 1,
            ]);
        });

        return back()->with('success', 'Sevkiyat planlandı.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function writable(array $data, ?Category $category, User $user): array
    {
        $allowed = [];
        foreach (CategoryColumns::visibleNames($category, $user) as $name) {
            $attribute = CategoryColumns::attributeFor($name);
            if ($attribute !== null) {
                $allowed[] = $attribute;
            }
        }

        $kept = array_intersect_key($data, array_flip([
            'category_id',
            'name',
            'description',
            'is_active',
            'sku',
            ...$allowed,
        ]));

        foreach ([
            'purchase_price',
            'sale_price',
            'default_order_quantity',
            'warehouse_quantity',
            'quantity_piece',
            'quantity_pallet',
        ] as $column) {
            if (array_key_exists($column, $kept) && $kept[$column] === null) {
                $kept[$column] = 0;
            }
        }

        return $kept;
    }

    /**
     * @return list<array{id: int, name: string, parent_id: int|null, fields: list<string>}>
     */
    private function categoryChoices(User $user): array
    {
        $categories = Category::query()
            ->with('columnDefinitions:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
        $byId = $categories->keyBy('id');

        return $categories->map(function (Category $category) use ($byId, $user): array {
            $owner = $category->parent_id === null
                ? $category
                : ($byId->get($category->parent_id) ?? $category);

            return [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'fields' => $owner->columnDefinitions
                    ->pluck('name')
                    ->filter(fn (string $name): bool => CategoryColumns::allowed($name, $user))
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /**
     * @return array{columns: list<array{name: string, label: string}>, actions: list<string>}
     */
    private function sheet(?Category $category, User $user): array
    {
        $visible = CategoryColumns::visibleNames($category, $user);
        $actions = array_values(array_intersect(
            ['offer_button', 'order_button', 'shipment_button', 'edit_button'],
            $visible,
        ));

        $formOnly = ['order_quantity', 'warning_count', 'warehouse_warning_count'];

        $columns = CategoryColumns::definitions($category)
            ->filter(fn ($definition) => in_array($definition->name, $visible, true)
                && ! in_array($definition->name, $actions, true)
                && ! in_array($definition->name, $formOnly, true))
            ->map(fn ($definition) => [
                'name' => $definition->name,
                'label' => $definition->label,
            ])
            ->values()
            ->all();

        return [
            'columns' => $columns,
            'actions' => $actions,
        ];
    }

    /**
     * @return array{product: array<string, mixed>, categories: mixed, fields: mixed, factories: mixed, canManage: bool}
     */
    private function editorPayload(Product $product, User $user): array
    {
        $columns = AccessRoles::visibleColumns($user);
        $product->load(['category.parent', 'sourceFactory:id,name']);
        $moldNumber = $product->factory_id === null
            ? null
            : MoldNumber::query()
                ->where('product_id', $product->id)
                ->where('factory_id', $product->factory_id)
                ->value('number');

        return [
            'product' => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'quantity_piece' => $columns['piece'] ? $product->quantity_piece : null,
                'quantity_pallet' => $columns['pallet'] ? $product->quantity_pallet : null,
                'warehouse_quantity' => $columns['alkop'] ? $product->warehouse_quantity : null,
                'shelf' => $product->shelf,
                'unit_weight_kg' => $product->unit_weight_kg,
                'length_measure' => $product->length_measure,
                'purchase_price' => $columns['purchase'] ? $product->purchase_price : null,
                'sale_price' => $columns['sale'] ? $product->sale_price : null,
                'customer_name' => $product->customer_name,
                'due_on' => $product->due_on?->toDateString(),
                'default_order_quantity' => $product->default_order_quantity,
                'warehouse_low_stock_threshold' => $product->warehouse_low_stock_threshold,
                'low_stock_threshold' => $product->low_stock_threshold,
                'is_active' => $product->is_active,
                'is_low_stock' => $product->isLowStock(),
                'factory_id' => $product->factory_id,
                'factory_name' => $product->sourceFactory?->name,
                'mold_number' => $moldNumber,
                'category' => $product->category?->only(['id', 'name']),
            ],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'fields' => CategoryColumns::visibleNames($product->category, $user),
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $user->can('stock.manage'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivities(Product $product, User $user): array
    {
        $columns = AccessRoles::visibleColumns($user);
        $places = array_values(array_filter([
            $columns['piece'] ? StockActivityPlace::Store->value : null,
            $columns['pallet'] ? StockActivityPlace::Pallet->value : null,
            $columns['alkop'] ? StockActivityPlace::Warehouse->value : null,
        ], fn ($place) => $place !== null));

        return $product->stockActivities()
            ->with('user:id,name')
            ->when($places !== [], fn ($query) => $query->whereIn('place', $places))
            ->when($places === [], fn ($query) => $query->whereRaw('0 = 1'))
            ->latest('recorded_at')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (StockActivity $activity) => [
                'id' => $activity->id,
                'place' => $activity->place->label(),
                'previous_quantity' => $activity->previous_quantity,
                'new_quantity' => $activity->new_quantity,
                'difference' => $activity->new_quantity - $activity->previous_quantity,
                'note' => $activity->note,
                'user' => $activity->user?->only(['id', 'name']),
                'recorded_at' => $activity->recorded_at?->format('d.m.Y H:i'),
            ])
            ->all();
    }

    private function requireColumn(Product $product, string $name, User $user): void
    {
        $product->loadMissing('category.parent');
        abort_unless(in_array($name, CategoryColumns::visibleNames($product->category, $user), true), 403);
    }
}
