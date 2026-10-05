<?php

namespace App\Http\Controllers;

use App\Enums\MoldDocument;
use App\Http\Requests\Mold\StoreMoldRequest;
use App\Http\Requests\Mold\UpdateMoldRequest;
use App\Models\Client;
use App\Models\Factory;
use App\Models\Media;
use App\Models\Mold;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MoldController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Mold::class);

        return $this->listing($request, archived: false);
    }

    public function archived(Request $request): Response
    {
        $this->authorize('viewAny', Mold::class);

        return $this->listing($request, archived: true);
    }

    public function create(): Response
    {
        $this->authorize('create', Mold::class);

        return Inertia::render('Molds/Form', [
            'mold' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreMoldRequest $request): RedirectResponse
    {
        $mold = Mold::query()->create($this->attributes($request) + [
            'created_by_user_id' => $request->user()->id,
        ]);
        $this->storeDocuments($request, $mold);

        return redirect()
            ->route('molds.index')
            ->with('success', 'Kalıp kaydedildi.');
    }

    public function edit(Request $request, Mold $mold): Response
    {
        $this->authorize('view', $mold);

        $mold->load(['media', 'client:id,name', 'sourceFactory:id,name']);

        return Inertia::render('Molds/Form', [
            'mold' => [
                'id' => $mold->id,
                'client_id' => $mold->client_id,
                'factory_id' => $mold->factory_id,
                'number' => $mold->number,
                'client_offer_price' => $mold->client_offer_price,
                'factory_offer_price' => $mold->factory_offer_price,
                'due_on' => $mold->due_on?->toDateString(),
                'contact_name' => $mold->contact_name,
                'description' => $mold->description,
                'documents' => $this->documents($mold),
            ],
            'canManage' => $request->user()->can('update', $mold),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateMoldRequest $request, Mold $mold): RedirectResponse
    {
        $mold->update($this->attributes($request));
        $this->storeDocuments($request, $mold);

        return redirect()
            ->route('molds.edit', $mold)
            ->with('success', 'Kalıp güncellendi.');
    }

    public function archive(Mold $mold): RedirectResponse
    {
        $this->authorize('update', $mold);

        $mold->archived_at = now();
        $mold->save();

        return redirect()
            ->route('molds.index')
            ->with('success', 'Kalıp arşivlendi.');
    }

    public function unarchive(Mold $mold): RedirectResponse
    {
        $this->authorize('update', $mold);

        $mold->archived_at = null;
        $mold->save();

        return redirect()
            ->route('molds.archived')
            ->with('success', 'Kalıp arşivden çıkarıldı.');
    }

    public function destroy(Mold $mold): RedirectResponse
    {
        $this->authorize('delete', $mold);

        $mold->delete();

        return redirect()
            ->route($mold->archived_at === null ? 'molds.index' : 'molds.archived')
            ->with('success', 'Kalıp silindi.');
    }

    public function downloadDocument(Mold $mold, Media $medium): StreamedResponse
    {
        $this->authorize('view', $mold);

        abort_unless(
            $medium->model_type === $mold->getMorphClass() && (int) $medium->model_id === $mold->id,
            404,
        );
        abort_unless($medium->isStored(), 404);

        return Storage::disk($medium->disk)->download($medium->path, $medium->file_name);
    }

    private function listing(Request $request, bool $archived): Response
    {
        $search = trim((string) $request->string('search'));
        $factoryId = $request->integer('factory_id') ?: null;

        $molds = Mold::query()
            ->select('molds.*')
            ->leftJoin('clients', 'clients.id', '=', 'molds.client_id')
            ->with(['client:id,name', 'sourceFactory:id,name', 'createdBy:id,name', 'media'])
            ->when($archived, fn ($query) => $query->whereNotNull('molds.archived_at'), fn ($query) => $query->whereNull('molds.archived_at'))
            ->when($factoryId, fn ($query) => $query->where('molds.factory_id', $factoryId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('molds.number', 'like', "%{$search}%")
                        ->orWhere('molds.contact_name', 'like', "%{$search}%")
                        ->orWhere('clients.name', 'like', "%{$search}%");
                });
            })
            ->orderBy('clients.name')
            ->orderBy('molds.id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Mold $mold) => $this->listPayload($mold));

        return Inertia::render('Molds/Index', [
            'molds' => $molds,
            'archived' => $archived,
            'filters' => [
                'search' => $search,
                'factory_id' => $factoryId,
            ],
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('molds.manage'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(Mold $mold): array
    {
        return [
            'id' => $mold->id,
            'number' => $mold->number,
            'client_name' => $mold->client?->name,
            'factory_name' => $mold->sourceFactory?->name,
            'client_offer_price' => $mold->client_offer_price,
            'factory_offer_price' => $mold->factory_offer_price,
            'due_on' => $mold->due_on?->format('d.m.Y'),
            'contact_name' => $mold->contact_name,
            'created_by' => $mold->createdBy?->name,
            'documents' => $this->documents($mold),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreMoldRequest|UpdateMoldRequest $request): array
    {
        return $request->safe()->only([
            'client_id',
            'factory_id',
            'number',
            'client_offer_price',
            'factory_offer_price',
            'due_on',
            'contact_name',
            'description',
        ]);
    }

    /**
     * @return array{clients: array<int, array{id: int, name: string}>, factories: array<int, array{id: int, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
            ])->all(),
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name'])->map(fn (Factory $factory): array => [
                'id' => $factory->id,
                'name' => $factory->name,
            ])->all(),
        ];
    }

    private function storeDocuments(StoreMoldRequest|UpdateMoldRequest $request, Mold $mold): void
    {
        foreach (MoldDocument::cases() as $document) {
            $file = $request->file($document->value);
            if ($file instanceof UploadedFile) {
                $this->replaceDocument($mold, $document, $file);
            }
        }
    }

    private function replaceDocument(Mold $mold, MoldDocument $document, UploadedFile $file): void
    {
        $existing = $mold->media()->where('collection', $document->value)->first();
        if ($existing?->path) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        $path = $file->store('molds/'.$mold->id, 'local');

        $mold->media()->updateOrCreate(
            ['collection' => $document->value],
            [
                'disk' => 'local',
                'path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ],
        );
    }

    /**
     * @return array<int, array{key: string, label: string, file_name: string|null, url: string|null}>
     */
    private function documents(Mold $mold): array
    {
        return collect(MoldDocument::cases())->map(function (MoldDocument $document) use ($mold): array {
            $media = $mold->media->firstWhere('collection', $document->value);

            return [
                'key' => $document->value,
                'label' => $document->label(),
                'file_name' => $media?->file_name,
                'url' => $media !== null && $media->isStored()
                    ? route('molds.documents.download', [$mold, $media])
                    : null,
            ];
        })->all();
    }
}
