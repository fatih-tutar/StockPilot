<?php

namespace Tests\Feature;

use App\Enums\StaffDocument;
use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachLegacyFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_files_attach_to_media_logos_and_category_images(): void
    {
        Storage::fake('local');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $directory = storage_path('framework/testing/legacy-files');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory.'/profile');
        File::ensureDirectoryExists($directory.'/documents');
        File::ensureDirectoryExists($directory.'/vehicles');
        File::ensureDirectoryExists($directory.'/prices');
        File::ensureDirectoryExists($directory.'/company');
        File::ensureDirectoryExists($directory.'/categories');
        File::ensureDirectoryExists($directory.'/sidebar');
        File::put($directory.'/profile/portre.png', $png);
        File::put($directory.'/profile/ayni.png', 'profil');
        File::put($directory.'/documents/ayni.png', 'belge');
        File::put($directory.'/vehicles/cift.pdf', 'arac');
        File::put($directory.'/prices/cift.pdf', 'fiyat');
        File::put($directory.'/documents/mevcut.png', 'yeni');
        File::put($directory.'/company/marka.png', $png);
        File::put($directory.'/categories/grup.png', $png);
        File::put($directory.'/categories/eski-grup.png', $png);
        File::put($directory.'/sidebar/ikon.png', $png);

        $photo = User::factory()->create();
        $photo->media()->create([
            'collection' => StaffDocument::Photo->value,
            'disk' => 'local',
            'path' => null,
            'file_name' => 'portre.png',
        ]);

        $preferred = User::factory()->create();
        $preferred->media()->create([
            'collection' => StaffDocument::Photo->value,
            'disk' => 'local',
            'path' => null,
            'file_name' => 'ayni.png',
        ]);

        $ambiguous = User::factory()->create();
        $ambiguous->media()->create([
            'collection' => StaffDocument::IdentityCard->value,
            'disk' => 'local',
            'path' => null,
            'file_name' => 'cift.pdf',
        ]);

        $kept = User::factory()->create();
        $kept->media()->create([
            'collection' => StaffDocument::IdentityCard->value,
            'disk' => 'local',
            'path' => 'staff/'.$kept->id.'/eski.png',
            'file_name' => 'mevcut.png',
        ]);
        Storage::disk('local')->put('staff/'.$kept->id.'/eski.png', 'old');

        $missing = User::factory()->create();
        $missing->media()->create([
            'collection' => StaffDocument::Photo->value,
            'disk' => 'local',
            'path' => null,
            'file_name' => 'yok.png',
        ]);

        $company = Company::factory()->create(['logo' => 'marka.png']);
        $category = Category::factory()->create(['image' => 'grup.png']);
        $archived = Category::factory()->create(['image' => 'eski-grup.png']);
        $archived->delete();

        try {
            $this->artisan('stockpilot:attach-legacy-files', ['path' => $directory])
                ->expectsOutputToContain('Media attached: 2')
                ->expectsOutputToContain('Media already stored: 1')
                ->expectsOutputToContain('Media without a file: 1')
                ->expectsOutputToContain('Ambiguous file names: 1')
                ->expectsOutputToContain('Company logos copied: 1')
                ->expectsOutputToContain('Category images copied: 2')
                ->expectsOutputToContain('Files left unused: 4')
                ->assertSuccessful();

            $stored = $photo->media()->first();
            $this->assertNotNull($stored->path);
            $this->assertSame('local', $stored->disk);
            $this->assertSame('image/png', $stored->mime_type);
            $this->assertSame(strlen($png), $stored->size);
            $this->assertSame($png, Storage::disk('local')->get($stored->path));
            $this->assertTrue($stored->isStored());

            $chosen = $preferred->media()->first();
            $this->assertSame('profil', Storage::disk('local')->get($chosen->path));
            $this->assertNull($ambiguous->media()->first()->path);
            $this->assertSame('staff/'.$kept->id.'/eski.png', $kept->media()->first()->path);
            $this->assertSame('old', Storage::disk('local')->get('staff/'.$kept->id.'/eski.png'));
            $this->assertSame($png, Storage::disk('local')->get('companies/'.$company->id.'/marka.png'));
            $this->assertSame($png, Storage::disk('local')->get('categories/'.$category->id.'/grup.png'));
            $this->assertSame($png, Storage::disk('local')->get('categories/'.$archived->id.'/eski-grup.png'));

            $this->artisan('stockpilot:attach-legacy-files', ['path' => $directory])
                ->expectsOutputToContain('Media attached: 0')
                ->expectsOutputToContain('Media already stored: 3')
                ->expectsOutputToContain('Company logos already stored: 1')
                ->expectsOutputToContain('Category images already stored: 2')
                ->expectsOutputToContain('Files left unused: 4')
                ->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
