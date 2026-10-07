<?php

namespace App\Http\Controllers;

use App\Enums\StaffDocument;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Models\Media;
use App\Models\User;
use App\Support\AccessRoles;
use App\Support\StaffAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('search')->trim()->toString();

        $staff = $this->personnel()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'title' => $user->title,
                'phone' => $user->phone,
                'email' => $user->email,
                'access_level' => $user->access_level?->label(),
                'is_active' => $user->is_active,
                'hired_on' => $user->hired_on?->toDateString(),
            ]);

        return Inertia::render('Staff/Index', [
            'staff' => $staff,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Staff/Form', [
            'staffMember' => null,
            'levels' => $this->levels(),
            'flags' => StaffAccess::flags(),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $staff = User::query()->create($this->attributes($request));
        $staff->syncRoles([$request->validated('access_level')]);
        $this->storeDocuments($request, $staff);

        return redirect()
            ->route('staff.edit', $staff)
            ->with('success', 'Personel kaydı oluşturuldu.');
    }

    public function edit(User $staff): Response
    {
        $this->authorize('view', $staff);
        $this->ensurePersonnel($staff);
        $staff->load('media', 'roles');

        return Inertia::render('Staff/Form', [
            'staffMember' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'phone_2' => $staff->phone_2,
                'address' => $staff->address,
                'title' => $staff->title,
                'hired_on' => $staff->hired_on?->toDateString(),
                'access_level' => $staff->roles->first()?->name
                    ?? AccessRoles::roleFor($staff->access_level)
                    ?? 'staff',
                'access_flags' => StaffAccess::fromInput($staff->access_flags ?? []),
                'is_active' => $staff->is_active,
                'documents' => collect(StaffDocument::cases())
                    ->mapWithKeys(fn (StaffDocument $document) => [
                        $document->value => $this->documentPayload($staff, $document),
                    ]),
            ],
            'levels' => $this->levels(),
            'flags' => StaffAccess::flags(),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff): RedirectResponse
    {
        $this->ensurePersonnel($staff);
        $staff->update($this->attributes($request));
        $staff->syncRoles([$request->validated('access_level')]);
        $this->storeDocuments($request, $staff);

        return redirect()
            ->route('staff.edit', $staff)
            ->with('success', 'Personel kaydı güncellendi.');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);
        $this->ensurePersonnel($staff);

        if ($request->user()?->is($staff)) {
            return back()->with('error', 'Kendi hesabınızı buradan silemezsiniz.');
        }

        $staff->delete();

        return redirect()
            ->route('staff.index')
            ->with('success', 'Personel kaydı silindi.');
    }

    public function downloadDocument(User $staff, Media $medium): StreamedResponse
    {
        $this->authorize('view', $staff);
        $this->ensurePersonnel($staff);

        abort_unless($medium->model_type === User::class && (int) $medium->model_id === $staff->id, 404);
        abort_unless($medium->isStored(), 404);

        return Storage::disk($medium->disk)->response($medium->path, $medium->file_name);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreStaffRequest|UpdateStaffRequest $request): array
    {
        $attributes = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'phone_2' => $request->validated('phone_2'),
            'address' => $request->validated('address'),
            'title' => $request->validated('title'),
            'hired_on' => $request->validated('hired_on'),
            'access_level' => AccessRoles::accessLevelFor($request->validated('access_level')),
            'access_flags' => StaffAccess::fromInput($request->validated('access_flags') ?? []),
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $attributes['password'] = $request->validated('password');
        }

        return $attributes;
    }

    private function storeDocuments(Request $request, User $staff): void
    {
        foreach (StaffDocument::cases() as $document) {
            $file = $request->file($document->value);
            if ($file instanceof UploadedFile) {
                $this->replaceDocument($staff, $document, $file);
            }
        }
    }

    private function replaceDocument(User $staff, StaffDocument $document, UploadedFile $file): void
    {
        $existing = $staff->media()->where('collection', $document->value)->first();
        if ($existing?->path) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        $path = $file->store('staff/'.$staff->id, 'local');

        $staff->media()->updateOrCreate(
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
     * @return array{file_name: string|null, download_url: string|null, missing: bool}
     */
    private function documentPayload(User $staff, StaffDocument $document): array
    {
        $media = $staff->document($document->value);
        $stored = $media?->isStored() ?? false;

        return [
            'file_name' => $media?->file_name,
            'download_url' => $stored ? route('staff.documents.download', [$staff, $media]) : null,
            'missing' => $media !== null && ! $stored,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function levels(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'value' => $role->name,
                'label' => AccessRoles::label($role->name),
            ])
            ->all();
    }

    private function personnel(): Builder
    {
        return User::query()->where(function ($query) {
            $query->whereNull('email')
                ->orWhere('email', 'not like', '%@stockpilot.test');
        });
    }

    private function ensurePersonnel(User $staff): void
    {
        abort_unless(
            $staff->email === null || ! str_ends_with($staff->email, '@stockpilot.test'),
            404,
        );
    }
}
