<?php

namespace App\Http\Controllers;

use App\Enums\OfferListStatus;
use App\Http\Requests\OfferLists\StoreOfferListEntryRequest;
use App\Http\Requests\OfferLists\UpdateOfferListEntryRequest;
use App\Models\OfferListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferListController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OfferListEntry::class);

        return $this->renderList($request, archived: false);
    }

    public function archive(Request $request): Response
    {
        $this->authorize('viewAny', OfferListEntry::class);

        return $this->renderList($request, archived: true);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', OfferListEntry::class);

        return Inertia::render('OfferLists/Form', [
            'entry' => null,
            'canManage' => true,
        ]);
    }

    public function store(StoreOfferListEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $entry = OfferListEntry::query()->create([
            'customer_name' => $data['customer_name'],
            'contact_name' => $data['contact_name'] ?? null,
            'product_quantity' => $data['product_quantity'] ?? null,
            'price' => $data['price'] ?? null,
            'factory_name' => $data['factory_name'] ?? null,
            'factory_price' => $data['factory_price'] ?? null,
            'notes' => $data['notes'] ?? null,
            'offered_at' => $data['offered_on'],
            'offered_by_user_id' => $request->user()->id,
            'status' => OfferListStatus::Open,
        ]);

        return redirect()
            ->route('offer-lists.edit', $entry)
            ->with('success', 'Teklif listesine eklendi.');
    }

    public function edit(Request $request, OfferListEntry $offerListEntry): Response
    {
        $this->authorize('view', $offerListEntry);

        $offerListEntry->load('offeredBy:id,name');

        return Inertia::render('OfferLists/Form', [
            'entry' => $this->formEntry($offerListEntry),
            'canManage' => $request->user()->can('update', $offerListEntry),
        ]);
    }

    public function update(UpdateOfferListEntryRequest $request, OfferListEntry $offerListEntry): RedirectResponse
    {
        $data = $request->validated();

        $offerListEntry->update([
            'customer_name' => $data['customer_name'],
            'contact_name' => $data['contact_name'] ?? null,
            'product_quantity' => $data['product_quantity'] ?? null,
            'price' => $data['price'] ?? null,
            'factory_name' => $data['factory_name'] ?? null,
            'factory_price' => $data['factory_price'] ?? null,
            'notes' => $data['notes'] ?? null,
            'offered_at' => $data['offered_on'],
        ]);

        return redirect()
            ->route('offer-lists.edit', $offerListEntry)
            ->with('success', 'Teklif listesi kaydı güncellendi.');
    }

    public function archivePositive(OfferListEntry $offerListEntry): RedirectResponse
    {
        $this->authorize('update', $offerListEntry);

        $offerListEntry->update(['status' => OfferListStatus::ArchivedPositive]);

        return redirect()
            ->route('offer-lists.archive')
            ->with('success', 'Kayıt arşive alındı.');
    }

    public function archiveNegative(OfferListEntry $offerListEntry): RedirectResponse
    {
        $this->authorize('update', $offerListEntry);

        $offerListEntry->update(['status' => OfferListStatus::ArchivedNegative]);

        return redirect()
            ->route('offer-lists.archive')
            ->with('success', 'Kayıt arşive alındı.');
    }

    public function restore(OfferListEntry $offerListEntry): RedirectResponse
    {
        $this->authorize('update', $offerListEntry);

        $offerListEntry->update(['status' => OfferListStatus::Open]);

        return redirect()
            ->route('offer-lists.index')
            ->with('success', 'Kayıt listeye alındı.');
    }

    public function destroy(OfferListEntry $offerListEntry): RedirectResponse
    {
        $this->authorize('delete', $offerListEntry);

        $offerListEntry->update(['status' => OfferListStatus::Removed]);

        return redirect()
            ->route('offer-lists.archive')
            ->with('success', 'Kayıt listeden çıkarıldı.');
    }

    private function renderList(Request $request, bool $archived): Response
    {
        $search = $request->string('search')->trim()->toString();
        $statuses = $archived
            ? OfferListStatus::archived()
            : [OfferListStatus::Open];

        $entries = OfferListEntry::query()
            ->with('offeredBy:id,name')
            ->whereIn('status', $statuses)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('product_quantity', 'like', "%{$search}%")
                        ->orWhere('factory_name', 'like', "%{$search}%");
                });
            })
            ->latest('offered_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (OfferListEntry $entry) => $this->listEntry($entry));

        return Inertia::render('OfferLists/Index', [
            'entries' => $entries,
            'filters' => [
                'search' => $search,
            ],
            'archived' => $archived,
            'canManage' => $request->user()->can('quotes.manage'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listEntry(OfferListEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'customer_name' => $entry->customer_name,
            'contact_name' => $entry->contact_name,
            'product_quantity' => $entry->product_quantity,
            'price' => $entry->price,
            'factory_name' => $entry->factory_name,
            'factory_price' => $entry->factory_price,
            'offered_by' => $entry->offeredBy?->name,
            'offered_on' => $entry->offered_at?->timezone('Europe/Istanbul')->format('d.m.Y'),
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formEntry(OfferListEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'customer_name' => $entry->customer_name,
            'contact_name' => $entry->contact_name,
            'product_quantity' => $entry->product_quantity,
            'price' => $entry->price,
            'factory_name' => $entry->factory_name,
            'factory_price' => $entry->factory_price,
            'notes' => $entry->notes,
            'offered_on' => $entry->offered_at?->timezone('Europe/Istanbul')->toDateString(),
            'offered_by' => $entry->offeredBy?->name,
            'status_label' => $entry->status->label(),
        ];
    }
}
