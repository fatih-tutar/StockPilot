<?php

namespace App\Http\Controllers;

use App\Actions\Stock\AdjustProductStock;
use App\Http\Requests\Catalog\AdjustStockRequest;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $products = Product::query()
            ->with('category:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity_piece' => $product->quantity_piece,
                'quantity_pallet' => $product->quantity_pallet,
                'low_stock_threshold' => $product->low_stock_threshold,
                'is_low_stock' => $product->isLowStock(),
                'is_active' => $product->is_active,
                'category' => $product->category?->only(['id', 'name']),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
            ],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('stock.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Form', [
            'product' => null,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['quantity_piece'] = $data['quantity_piece'] ?? 0;
        $data['quantity_pallet'] = $data['quantity_pallet'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        $product = Product::query()->create($data);

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Product created.');
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);

        $product->load('category:id,name');

        $movements = $product->stockMovements()
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'type' => $movement->type,
                'quantity_piece_delta' => $movement->quantity_piece_delta,
                'quantity_pallet_delta' => $movement->quantity_pallet_delta,
                'note' => $movement->note,
                'user' => $movement->user?->only(['id', 'name']),
                'created_at' => $movement->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Products/Form', [
            'product' => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'quantity_piece' => $product->quantity_piece,
                'quantity_pallet' => $product->quantity_pallet,
                'low_stock_threshold' => $product->low_stock_threshold,
                'is_active' => $product->is_active,
                'is_low_stock' => $product->isLowStock(),
                'category' => $product->category?->only(['id', 'name']),
            ],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'movements' => $movements,
            'canManage' => $request->user()->can('stock.manage'),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()
            ->route('products.edit', $product)
            ->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        if ($product->quantity_piece > 0 || $product->quantity_pallet > 0) {
            return redirect()
                ->route('products.edit', $product)
                ->with('error', 'Zero out stock before deleting this product.');
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted.');
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
            ->with('success', 'Stock adjusted.');
    }
}
