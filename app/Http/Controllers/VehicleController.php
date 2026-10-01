<?php

namespace App\Http\Controllers;

use App\Enums\VehicleDocument;
use App\Http\Requests\Catalog\StoreVehicleRequest;
use App\Http\Requests\Catalog\UpdateVehicleRequest;
use App\Models\Media;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vehicle::class);

        $search = $request->string('search')->trim()->toString();

        $vehicles = Vehicle::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%")
                        ->orWhere('driver_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license_plate' => $vehicle->license_plate,
                'driver_name' => $vehicle->driver_name,
                'is_delivery_vehicle' => $vehicle->is_delivery_vehicle,
                'casco_expires_on' => $vehicle->casco_expires_on?->toDateString(),
                'insurance_expires_on' => $vehicle->insurance_expires_on?->toDateString(),
                'inspection_due_on' => $vehicle->inspection_due_on?->toDateString(),
            ]);

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters' => [
                'search' => $search,
            ],
            'canManage' => $request->user()->can('vehicles.manage'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Vehicle::class);

        return Inertia::render('Vehicles/Form', [
            'vehicle' => null,
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::query()->create($this->attributes($request));
        $this->storeDocuments($request, $vehicle);

        return redirect()
            ->route('vehicles.edit', $vehicle)
            ->with('success', 'Araç oluşturuldu.');
    }

    public function edit(Request $request, Vehicle $vehicle): Response
    {
        $this->authorize('view', $vehicle);

        $vehicle->load('media');

        return Inertia::render('Vehicles/Form', [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'license_plate' => $vehicle->license_plate,
                'driver_name' => $vehicle->driver_name,
                'description' => $vehicle->description,
                'is_delivery_vehicle' => $vehicle->is_delivery_vehicle,
                'casco_expires_on' => $vehicle->casco_expires_on?->toDateString(),
                'insurance_expires_on' => $vehicle->insurance_expires_on?->toDateString(),
                'inspection_due_on' => $vehicle->inspection_due_on?->toDateString(),
                'documents' => collect(VehicleDocument::cases())
                    ->mapWithKeys(fn (VehicleDocument $document) => [
                        $document->value => $this->documentPayload($vehicle, $document),
                    ]),
            ],
            'canManage' => $request->user()->can('vehicles.manage'),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($this->attributes($request));
        $this->storeDocuments($request, $vehicle);

        return redirect()
            ->route('vehicles.edit', $vehicle)
            ->with('success', 'Araç güncellendi.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Araç silindi.');
    }

    public function downloadDocument(Vehicle $vehicle, Media $medium): StreamedResponse
    {
        $this->authorize('view', $vehicle);

        abort_unless(
            $medium->model_type === $vehicle->getMorphClass() && (int) $medium->model_id === $vehicle->id,
            404,
        );
        abort_unless($medium->isStored(), 404);

        return Storage::disk($medium->disk)->download($medium->path, $medium->file_name);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreVehicleRequest|UpdateVehicleRequest $request): array
    {
        return $request->safe()->only([
            'name',
            'license_plate',
            'driver_name',
            'description',
            'casco_expires_on',
            'insurance_expires_on',
            'inspection_due_on',
        ]) + [
            'is_delivery_vehicle' => $request->boolean('is_delivery_vehicle'),
        ];
    }

    private function storeDocuments(StoreVehicleRequest|UpdateVehicleRequest $request, Vehicle $vehicle): void
    {
        foreach (VehicleDocument::cases() as $document) {
            $file = $request->file($document->value);
            if ($file instanceof UploadedFile) {
                $this->replaceDocument($vehicle, $document, $file);
            }
        }
    }

    private function replaceDocument(Vehicle $vehicle, VehicleDocument $document, UploadedFile $file): void
    {
        $existing = $vehicle->media()->where('collection', $document->value)->first();
        if ($existing?->path) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        $path = $file->store('vehicles/'.$vehicle->id, 'local');

        $vehicle->media()->updateOrCreate(
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
     * @return array{id: int, file_name: string, available: bool}|null
     */
    private function documentPayload(Vehicle $vehicle, VehicleDocument $document): ?array
    {
        $media = $vehicle->media->firstWhere('collection', $document->value);
        if ($media === null) {
            return null;
        }

        return [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'available' => $media->isStored(),
        ];
    }
}
