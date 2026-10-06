<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockActivity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $actor = $request->user();
        $productId = $request->integer('product_id') ?: null;
        $product = $productId === null
            ? null
            : Product::withTrashed()->find($productId);

        $activities = StockActivity::query()
            ->with([
                'user:id,name',
                'product' => fn ($query) => $query->withTrashed()->select('id', 'name'),
            ])
            ->when($actor->company_id !== null, fn ($query) => $query->where('company_id', $actor->company_id))
            ->when($product !== null, fn ($query) => $query->where('product_id', $product->id))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (StockActivity $activity) => [
                'id' => $activity->id,
                'place' => $activity->place->label(),
                'previous_quantity' => $activity->previous_quantity,
                'new_quantity' => $activity->new_quantity,
                'difference' => $activity->new_quantity - $activity->previous_quantity,
                'recorded_at' => $activity->recorded_at?->format('d.m.Y H:i'),
                'user' => $activity->user?->only(['id', 'name']),
                'product' => $activity->product?->only(['id', 'name']),
            ]);

        return Inertia::render('StockActivities/Index', [
            'activities' => $activities,
            'product' => $product === null ? null : [
                'id' => $product->id,
                'name' => $product->name,
            ],
        ]);
    }
}
