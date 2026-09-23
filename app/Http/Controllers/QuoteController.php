<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Http\Requests\Quotes\UpdateQuoteRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Quote::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        $quotes = Quote::query()
            ->with(['client:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('quote_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Quote $quote) => [
                'id' => $quote->id,
                'number' => $quote->number,
                'status' => $quote->status->value,
                'status_label' => $quote->status->label(),
                'quote_date' => $quote->quote_date?->toDateString(),
                'valid_until' => $quote->valid_until?->toDateString(),
                'total' => (float) $quote->total,
                'currency' => $quote->currency,
                'client' => [
                    'id' => $quote->client?->id,
                    'name' => $quote->client?->name,
                ],
            ]);

        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => QuoteStatus::options(),
            'canManage' => $request->user()->can('quotes.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Quote::class);

        return Inertia::render('Quotes/Form', [
            'quote' => null,
            'clients' => $this->clientOptions(),
            'products' => $this->productOptions(),
            'statusOptions' => QuoteStatus::options(),
            'canManage' => true,
        ]);
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $quote = DB::transaction(function () use ($request, $data) {
            $quote = Quote::query()->create([
                'number' => Quote::nextNumber(),
                'client_id' => $data['client_id'],
                'user_id' => $request->user()->id,
                'status' => $data['status'],
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'currency' => strtoupper($data['currency']),
                'tax_rate' => $data['tax_rate'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]);

            $this->syncItems($quote, $data['items']);
            $quote->recalculateTotals();

            return $quote;
        });

        return redirect()
            ->route('quotes.edit', $quote)
            ->with('success', 'Teklif oluşturuldu.');
    }

    public function edit(Request $request, Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $quote->load(['items.product:id,name,sku', 'client:id,name']);

        return Inertia::render('Quotes/Form', [
            'quote' => [
                'id' => $quote->id,
                'number' => $quote->number,
                'client_id' => $quote->client_id,
                'status' => $quote->status->value,
                'quote_date' => $quote->quote_date?->toDateString(),
                'valid_until' => $quote->valid_until?->toDateString(),
                'currency' => $quote->currency,
                'tax_rate' => (float) $quote->tax_rate,
                'subtotal' => (float) $quote->subtotal,
                'tax_amount' => (float) $quote->tax_amount,
                'total' => (float) $quote->total,
                'notes' => $quote->notes,
                'items' => $quote->items->map(fn (QuoteItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity_piece' => $item->quantity_piece,
                    'quantity_pallet' => $item->quantity_pallet,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ])->values(),
            ],
            'clients' => $this->clientOptions(),
            'products' => $this->productOptions(),
            'statusOptions' => QuoteStatus::options(),
            'canManage' => $request->user()->can('quotes.manage'),
        ]);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($quote, $data) {
            $quote->update([
                'client_id' => $data['client_id'],
                'status' => $data['status'],
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'currency' => strtoupper($data['currency']),
                'tax_rate' => $data['tax_rate'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($quote, $data['items']);
            $quote->recalculateTotals();
        });

        return redirect()
            ->route('quotes.edit', $quote)
            ->with('success', 'Teklif güncellendi.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return redirect()
            ->route('quotes.index')
            ->with('success', 'Teklif silindi.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Quote $quote, array $items): void
    {
        $keptIds = [];

        foreach (array_values($items) as $index => $item) {
            $quantityPiece = (int) ($item['quantity_piece'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $payload = [
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity_piece' => $quantityPiece,
                'quantity_pallet' => (int) ($item['quantity_pallet'] ?? 0),
                'unit_price' => $unitPrice,
                'line_total' => QuoteItem::calculateLineTotal($quantityPiece, $unitPrice),
                'sort_order' => $index,
            ];

            if (! empty($item['id'])) {
                $existing = $quote->items()->whereKey($item['id'])->first();
                if ($existing) {
                    $existing->update($payload);
                    $keptIds[] = $existing->id;
                    continue;
                }
            }

            $created = $quote->items()->create($payload);
            $keptIds[] = $created->id;
        }

        $quote->items()->whereNotIn('id', $keptIds)->delete();
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
