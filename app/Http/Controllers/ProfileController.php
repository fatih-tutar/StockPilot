<?php

namespace App\Http\Controllers;

use App\Enums\StaffDocument;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $user->load('media');

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'title' => $user->title,
                'phone' => $user->phone,
                'phone_2' => $user->phone_2,
                'address' => $user->address,
                'hired_on' => $user->hired_on?->toDateString(),
                'email_verified_at' => $user->email_verified_at,
                'documents' => collect(StaffDocument::cases())
                    ->mapWithKeys(fn (StaffDocument $document) => [
                        $document->value => $this->documentPayload($user, $document),
                    ]),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only([
            'name',
            'email',
            'title',
            'phone',
            'phone_2',
            'address',
            'hired_on',
        ]));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $this->storeDocuments($request, $user);

        return Redirect::route('profile.edit');
    }

    public function downloadDocument(Request $request, Media $medium): StreamedResponse
    {
        abort_unless(
            $medium->model_type === User::class && (int) $medium->model_id === $request->user()->id,
            404,
        );
        abort_unless($medium->isStored(), 404);

        return Storage::disk($medium->disk)->response($medium->path, $medium->file_name);
    }

    private function storeDocuments(Request $request, User $user): void
    {
        foreach (StaffDocument::cases() as $document) {
            $file = $request->file($document->value);
            if ($file instanceof UploadedFile) {
                $this->replaceDocument($user, $document, $file);
            }
        }
    }

    private function replaceDocument(User $user, StaffDocument $document, UploadedFile $file): void
    {
        $existing = $user->media()->where('collection', $document->value)->first();
        if ($existing?->path) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        $path = $file->store('staff/'.$user->id, 'local');

        $user->media()->updateOrCreate(
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
    private function documentPayload(User $user, StaffDocument $document): array
    {
        $media = $user->document($document->value);
        $stored = $media?->isStored() ?? false;

        return [
            'file_name' => $media?->file_name,
            'download_url' => $stored ? route('profile.documents.download', $media) : null,
            'missing' => $media !== null && ! $stored,
        ];
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
