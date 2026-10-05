<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Models\Media;
use App\Models\OrganizationMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', OrganizationMember::class);

        $members = OrganizationMember::query()
            ->with(['media', 'user'])
            ->orderBy('position')
            ->get()
            ->map(fn (OrganizationMember $member) => $this->payload($member))
            ->values();

        return Inertia::render('Organization/Index', [
            'members' => $members,
            'canManage' => request()->user()?->can('create', OrganizationMember::class) ?? false,
        ]);
    }

    public function update(UpdateOrganizationRequest $request): RedirectResponse
    {
        foreach ($request->validated('people') as $index => $person) {
            $member = OrganizationMember::query()->findOrFail($person['id']);
            $this->authorize('update', $member);

            $member->update([
                'name' => $person['name'] ?? null,
                'title' => $person['title'] ?? null,
            ]);

            $photo = $request->file("people.$index.photo");
            if ($photo instanceof UploadedFile) {
                $this->replacePhoto($member, $photo);
            }
        }

        return redirect()->route('organization.index');
    }

    public function photo(OrganizationMember $organizationMember): StreamedResponse
    {
        $this->authorize('view', $organizationMember);

        $photo = $organizationMember->media()->where('collection', OrganizationMember::PHOTO)->first();

        abort_unless($photo instanceof Media && $photo->isStored(), 404);

        return Storage::disk($photo->disk)->response($photo->path, $photo->file_name);
    }

    private function replacePhoto(OrganizationMember $member, UploadedFile $file): void
    {
        $existing = $member->media()->where('collection', OrganizationMember::PHOTO)->first();
        if ($existing?->path) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        $path = $file->store('organization/'.$member->id, 'local');

        $member->media()->updateOrCreate(
            ['collection' => OrganizationMember::PHOTO],
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
     * @return array{id: int, position: int, name: string|null, title: string|null, user_name: string|null, photo_available: bool}
     */
    private function payload(OrganizationMember $member): array
    {
        return [
            'id' => $member->id,
            'position' => $member->position,
            'name' => $member->name,
            'title' => $member->title,
            'user_name' => $member->user?->name,
            'photo_available' => $member->photo()?->isStored() ?? false,
        ];
    }
}
