<?php

namespace App\Console\Commands;

use App\Enums\StaffDocument;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Company;
use App\Models\Media;
use App\Models\Mold;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class AttachLegacyFiles extends Command
{
    protected $signature = 'stockpilot:attach-legacy-files {path : Legacy files directory}';

    protected $description = 'Copy legacy files onto media rows, company logos, and category images that only have a file name';

    public function handle(): int
    {
        $root = realpath((string) $this->argument('path'));

        if ($root === false || ! is_dir($root)) {
            $this->error('Folder not found.');

            return self::FAILURE;
        }

        $index = $this->indexFiles($root);
        $used = [];

        $attached = 0;
        $alreadyStored = 0;
        $missing = 0;
        $ambiguous = 0;

        Media::query()
            ->orderBy('id')
            ->each(function (Media $medium) use ($root, $index, &$used, &$attached, &$alreadyStored, &$missing, &$ambiguous): void {
                if ($medium->path !== null) {
                    $alreadyStored++;
                    $this->markUsed($root, $index, $medium->file_name, $this->preferredFolders($medium->model_type, $medium->collection), $used);

                    return;
                }

                $paths = $index[$medium->file_name] ?? [];

                if ($paths === []) {
                    $missing++;

                    return;
                }

                $source = $this->chooseFile($root, $paths, $this->preferredFolders($medium->model_type, $medium->collection));

                if ($source === null) {
                    $ambiguous++;

                    return;
                }

                $stored = $this->storeCopy($this->storageDirectory($medium->model_type, $medium->model_id), $source);

                if ($stored === null) {
                    $missing++;

                    return;
                }

                $updated = Media::query()->whereKey($medium->id)->whereNull('path')->update([
                    'disk' => 'local',
                    'path' => $stored,
                    'mime_type' => MimeTypes::getDefault()->guessMimeType($source) ?: 'application/octet-stream',
                    'size' => filesize($source) ?: 0,
                    'updated_at' => now(),
                ]);

                if ($updated === 0) {
                    Storage::disk('local')->delete($stored);
                    $alreadyStored++;

                    return;
                }

                $used[$source] = true;
                $attached++;
            });

        [$logosCopied, $logosReady, $logosMissing] = $this->copyNamedFiles(
            Company::query()->whereNotNull('logo')->orderBy('id')->get(['id', 'logo']),
            'logo',
            'companies',
            ['company'],
            $root,
            $index,
            $used,
        );

        [$imagesCopied, $imagesReady, $imagesMissing] = $this->copyNamedFiles(
            Category::query()->withTrashed()->whereNotNull('image')->orderBy('id')->get(['id', 'image']),
            'image',
            'categories',
            ['categories'],
            $root,
            $index,
            $used,
        );

        $unused = 0;
        foreach ($index as $paths) {
            foreach ($paths as $path) {
                if (! isset($used[$path])) {
                    $unused++;
                }
            }
        }

        $this->info('Media attached: '.$attached);
        $this->info('Media already stored: '.$alreadyStored);
        $this->info('Media without a file: '.$missing);
        $this->info('Ambiguous file names: '.$ambiguous);
        $this->info('Company logos copied: '.$logosCopied);
        $this->info('Company logos already stored: '.$logosReady);
        $this->info('Company logos without a file: '.$logosMissing);
        $this->info('Category images copied: '.$imagesCopied);
        $this->info('Category images already stored: '.$imagesReady);
        $this->info('Category images without a file: '.$imagesMissing);
        $this->info('Files left unused: '.$unused);

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<string>>
     */
    private function indexFiles(string $root): array
    {
        $index = [];

        foreach (File::allFiles($root) as $file) {
            $real = $file->getRealPath();
            if ($real === false || ! $this->isInside($root, $real) || str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            $index[$file->getFilename()][] = $real;
        }

        return $index;
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $preferredFolders
     */
    private function chooseFile(string $root, array $paths, array $preferredFolders): ?string
    {
        if (count($paths) === 1) {
            return $paths[0];
        }

        $matches = [];

        foreach ($paths as $path) {
            $relative = ltrim(substr($path, strlen($root)), '/');
            $folder = explode('/', $relative, 2)[0];

            if (in_array($folder, $preferredFolders, true)) {
                $matches[] = $path;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @return list<string>
     */
    private function preferredFolders(string $modelType, string $collection): array
    {
        return match ($modelType) {
            CatalogItem::class => ['prices'],
            Mold::class => ['molds'],
            Vehicle::class => ['vehicles'],
            OrganizationMember::class => ['organization'],
            User::class => $collection === StaffDocument::Photo->value
                ? ['profile', 'img']
                : ['documents', 'img'],
            default => [],
        };
    }

    private function storageDirectory(string $modelType, int $modelId): string
    {
        $folder = match ($modelType) {
            CatalogItem::class => 'catalog-items',
            Mold::class => 'molds',
            Vehicle::class => 'vehicles',
            OrganizationMember::class => 'organization',
            User::class => 'staff',
            default => 'legacy',
        };

        return $folder.'/'.$modelId;
    }

    /**
     * @param  Collection<int, Company|Category>  $rows
     * @param  array<string, list<string>>  $index
     * @param  array<string, true>  $used
     * @param  list<string>  $preferredFolders
     * @return array{0: int, 1: int, 2: int}
     */
    private function copyNamedFiles(Collection $rows, string $column, string $directory, array $preferredFolders, string $root, array $index, array &$used): array
    {
        $copied = 0;
        $ready = 0;
        $missing = 0;

        foreach ($rows as $row) {
            $name = (string) $row->{$column};
            $safe = $this->safeBasename($name);

            if ($safe === null) {
                $missing++;

                continue;
            }

            $destination = $directory.'/'.$row->id.'/'.$safe;

            if (Storage::disk('local')->exists($destination)) {
                $ready++;
                $this->markUsed($root, $index, $name, $preferredFolders, $used);

                continue;
            }

            $paths = $index[$name] ?? [];
            $source = $this->chooseFile($root, $paths, $preferredFolders);

            if ($source === null) {
                $missing++;

                continue;
            }

            if ($this->storeCopy($directory.'/'.$row->id, $source, $safe) === null) {
                $missing++;

                continue;
            }

            $used[$source] = true;
            $copied++;
        }

        return [$copied, $ready, $missing];
    }

    /**
     * @param  array<string, list<string>>  $index
     * @param  list<string>  $preferredFolders
     * @param  array<string, true>  $used
     */
    private function markUsed(string $root, array $index, string $name, array $preferredFolders, array &$used): void
    {
        $source = $this->chooseFile($root, $index[$name] ?? [], $preferredFolders);

        if ($source !== null) {
            $used[$source] = true;
        }
    }

    private function storeCopy(string $directory, string $source, ?string $basename = null): ?string
    {
        $name = $basename ?? $this->storedName($source);
        $path = trim($directory, '/').'/'.$name;

        if (! Storage::disk('local')->put($path, File::get($source))) {
            return null;
        }

        return $path;
    }

    private function storedName(string $source): string
    {
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        if (! preg_match('/^[a-z0-9]{1,8}$/', $extension)) {
            $extension = '';
        }

        return (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
    }

    private function safeBasename(string $name): ?string
    {
        $base = basename(str_replace('\\', '/', $name));

        if ($base === '' || $base === '.' || $base === '..' || $base !== $name) {
            return null;
        }

        return $base;
    }

    private function isInside(string $root, string $path): bool
    {
        return $path === $root || str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }
}
