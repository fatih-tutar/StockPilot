<?php

namespace App\Http\Controllers;

use App\Enums\CatalogImage;
use App\Http\Requests\CatalogItem\StoreCatalogItemRequest;
use App\Http\Requests\CatalogItem\UpdateCatalogItemRequest;
use App\Models\CatalogItem;
use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogItemController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CatalogItem::class);

        $search = trim((string) $request->string('search'));

        return Inertia::render('CatalogItems/Index', [
            'groups' => $this->groups($search, true),
            'filters' => [
                'search' => $search,
            ],
            'canManage' => $request->user()->can('create', CatalogItem::class),
            'pricesVisible' => $this->pricesVisible($request),
            'canTogglePrices' => $request->user()->can('create', CatalogItem::class) && $request->user()->company_id !== null,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', CatalogItem::class);

        return Inertia::render('CatalogItems/Form', [
            'group' => null,
            'canManage' => true,
        ]);
    }

    public function store(StoreCatalogItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $sort = ((int) CatalogItem::withTrashed()->max('sort_order')) + 1;
        $created = [];

        foreach ($data['lines'] as $line) {
            $created[] = CatalogItem::query()->create([
                'product_code' => $data['product_code'],
                'code' => $line['code'] ?? null,
                'model' => $line['model'],
                'quantity' => $line['quantity'] ?? null,
                'price' => $line['price'] ?? null,
                'description' => $data['description'] ?? null,
                'sort_order' => $sort,
            ]);
        }

        foreach ($created as $item) {
            $this->storeImages($request, $item);
        }

        return redirect()
            ->route('catalog-items.index')
            ->with('success', 'Fiyat kaydedildi.');
    }

    public function edit(Request $request, CatalogItem $catalogItem): Response
    {
        $this->authorize('view', $catalogItem);

        return Inertia::render('CatalogItems/Form', [
            'group' => $this->groupPayload($this->siblings($catalogItem), true),
            'canManage' => $request->user()->can('update', $catalogItem),
        ]);
    }

    public function update(UpdateCatalogItemRequest $request, CatalogItem $catalogItem): RedirectResponse
    {
        $data = $request->validated();
        $siblings = $this->siblings($catalogItem);
        $catalogItem->load('media');
        $created = [];

        foreach ($siblings as $sibling) {
            $sibling->product_code = $data['product_code'];
            $sibling->description = $data['description'] ?? null;
            $sibling->save();
        }

        foreach ($data['lines'] as $line) {
            $row = isset($line['id'])
                ? $siblings->firstWhere('id', (int) $line['id'])
                : null;

            if ($row instanceof CatalogItem) {
                $row->update([
                    'code' => $line['code'] ?? null,
                    'model' => $line['model'],
                    'quantity' => $line['quantity'] ?? null,
                    'price' => $line['price'] ?? null,
                ]);

                continue;
            }

            $created[] = CatalogItem::query()->create([
                'product_code' => $data['product_code'],
                'code' => $line['code'] ?? null,
                'model' => $line['model'],
                'quantity' => $line['quantity'] ?? null,
                'price' => $line['price'] ?? null,
                'description' => $data['description'] ?? null,
                'sort_order' => $catalogItem->sort_order,
            ]);
        }

        foreach ($created as $item) {
            $this->copyImages($catalogItem, $item);
        }

        if ($this->hasUploadedImage($request)) {
            foreach ($this->siblings($catalogItem->fresh() ?? $catalogItem) as $item) {
                $this->storeImages($request, $item);
            }
        }

        return redirect()
            ->route('catalog-items.index')
            ->with('success', 'Fiyat güncellendi.');
    }

    public function destroy(CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorize('delete', $catalogItem);

        $catalogItem->delete();

        return redirect()
            ->route('catalog-items.index')
            ->with('success', 'Satır silindi.');
    }

    public function destroyGroup(CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorize('delete', $catalogItem);

        CatalogItem::query()
            ->where('product_code', $catalogItem->product_code)
            ->delete();

        return redirect()
            ->route('catalog-items.index')
            ->with('success', 'Ürün listeden çıkarıldı.');
    }

    public function move(Request $request, CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorize('update', $catalogItem);

        $direction = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ])['direction'];

        $orders = CatalogItem::query()
            ->selectRaw('product_code, min(sort_order) as sort_order')
            ->groupBy('product_code')
            ->orderBy('sort_order')
            ->orderBy('product_code')
            ->get();

        $index = $orders->search(fn ($row): bool => $row->product_code === $catalogItem->product_code);
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! isset($orders[$swapIndex])) {
            return back();
        }

        $currentOrder = (int) $orders[$index]->sort_order;
        $otherOrder = (int) $orders[$swapIndex]->sort_order;
        $otherCode = $orders[$swapIndex]->product_code;

        DB::transaction(function () use ($catalogItem, $currentOrder, $otherOrder, $otherCode): void {
            CatalogItem::query()
                ->where('product_code', $catalogItem->product_code)
                ->update(['sort_order' => $otherOrder]);
            CatalogItem::query()
                ->where('product_code', $otherCode)
                ->update(['sort_order' => $currentOrder]);
        });

        return back();
    }

    public function print(Request $request): Response
    {
        $this->authorize('viewAny', CatalogItem::class);

        $company = $request->user()->company;
        $pricesVisible = $this->pricesVisible($request);

        return Inertia::render('CatalogItems/Print', [
            'groups' => $this->groups('', $pricesVisible),
            'pricesVisible' => $pricesVisible,
            'letterhead' => $company?->letterhead,
            'companyName' => $company?->name,
        ]);
    }

    public function toggleVisibility(Request $request): RedirectResponse
    {
        $this->authorize('create', CatalogItem::class);

        $company = $request->user()->company;
        if ($company === null) {
            return back();
        }

        $company->update([
            'price_list_visible' => ! $company->price_list_visible,
        ]);

        return back()->with('success', $company->price_list_visible
            ? 'Fiyatlar listede görünür.'
            : 'Fiyatlar listede gizlendi.');
    }

    public function downloadImage(CatalogItem $catalogItem, Media $medium): StreamedResponse
    {
        $this->authorize('view', $catalogItem);

        abort_unless(
            $medium->model_type === $catalogItem->getMorphClass() && (int) $medium->model_id === $catalogItem->id,
            404,
        );
        abort_unless($medium->isStored(), 404);

        return Storage::disk($medium->disk)->response($medium->path, $medium->file_name);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groups(string $search, bool $includePrices): array
    {
        $items = CatalogItem::query()
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $codes = $items->filter(function (CatalogItem $item) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $item->product_code,
                    $item->code,
                    $item->model,
                    $item->quantity,
                    $item->price,
                    $item->description,
                ])));

                return str_contains($haystack, $needle);
            })->pluck('product_code')->unique();

            $items = $items->whereIn('product_code', $codes->all())->values();
        }

        return $items
            ->groupBy(fn (CatalogItem $item): string => (string) $item->product_code)
            ->map(fn (Collection $lines): array => $this->groupPayload($lines, $includePrices))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, CatalogItem>  $lines
     * @return array<string, mixed>
     */
    private function groupPayload(Collection $lines, bool $includePrices): array
    {
        /** @var CatalogItem $first */
        $first = $lines->first();

        return [
            'id' => $first->id,
            'product_code' => $first->product_code,
            'description' => $first->description,
            'sort_order' => $first->sort_order,
            'images' => $this->images($first),
            'lines' => $lines->map(fn (CatalogItem $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'model' => $item->model,
                'quantity' => $item->quantity,
                'price' => $includePrices ? $item->price : null,
            ])->values()->all(),
        ];
    }

    /**
     * @return Collection<int, CatalogItem>
     */
    private function siblings(CatalogItem $catalogItem): Collection
    {
        return CatalogItem::query()
            ->with('media')
            ->where('product_code', $catalogItem->product_code)
            ->orderBy('id')
            ->get();
    }

    private function storeImages(Request $request, CatalogItem $item): void
    {
        foreach (CatalogImage::cases() as $image) {
            $file = $request->file($image->value);
            if ($file instanceof UploadedFile) {
                $this->replaceImage($item, $image, $file);
            }
        }
    }

    private function hasUploadedImage(Request $request): bool
    {
        foreach (CatalogImage::cases() as $image) {
            if ($request->hasFile($image->value)) {
                return true;
            }
        }

        return false;
    }

    private function replaceImage(CatalogItem $item, CatalogImage $image, UploadedFile $file): void
    {
        $existing = $item->media()->where('collection', $image->value)->first();
        if ($existing?->path) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        $path = 'catalog-items/'.$item->id.'/'.$file->hashName();
        Storage::disk('local')->put($path, $file->get());

        $item->media()->updateOrCreate(
            ['collection' => $image->value],
            [
                'disk' => 'local',
                'path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ],
        );
    }

    private function copyImages(CatalogItem $from, CatalogItem $to): void
    {
        $from->loadMissing('media');

        foreach ($from->media as $media) {
            $path = null;
            if ($media->isStored()) {
                $path = 'catalog-items/'.$to->id.'/'.basename((string) $media->path);
                Storage::disk($media->disk)->copy((string) $media->path, $path);
            }

            $to->media()->updateOrCreate(
                ['collection' => $media->collection],
                [
                    'disk' => $media->disk ?: 'local',
                    'path' => $path,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ],
            );
        }
    }

    /**
     * @return array<int, array{key: string, label: string, file_name: string|null, url: string|null}>
     */
    private function images(CatalogItem $item): array
    {
        return collect(CatalogImage::cases())->map(function (CatalogImage $image) use ($item): array {
            $media = $item->media->firstWhere('collection', $image->value);

            return [
                'key' => $image->value,
                'label' => $image->label(),
                'file_name' => $media?->file_name,
                'url' => $media !== null && $media->isStored()
                    ? route('catalog-items.images.download', [$item, $media])
                    : null,
            ];
        })->all();
    }

    private function pricesVisible(Request $request): bool
    {
        $company = $request->user()->company;

        return $company === null || $company->price_list_visible;
    }
}
