<?php

namespace App\Console\Commands;

use App\Enums\VehicleDocument;
use App\Models\Category;
use App\Models\CategoryColumnDefinition;
use App\Models\Product;
use App\Models\Vehicle;
use Carbon\Exceptions\InvalidFormatException;
use Database\Seeders\CategoryColumnDefinitionSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportLegacyCatalog extends Command
{
    protected $signature = 'stockpilot:import-legacy {path : Directory with the legacy CSV exports} {--only= : Import a single file, currently factories}';

    protected $description = 'Import companies, categories, column layout, products, and factories from local CSV exports';

    public function handle(): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

        if ($this->option('only') === 'factories') {
            return $this->importFactoriesOnly($path);
        }

        if ($this->option('only') === 'vehicles') {
            return $this->importVehiclesOnly($path);
        }

        foreach (['companies.csv', 'categories.csv', 'category_columns_definitions.csv', 'category_columns.csv', 'products.csv'] as $file) {
            if (! is_file($path.'/'.$file)) {
                $this->error("Missing {$file} in {$path}");

                return self::FAILURE;
            }
        }

        $this->call(CategoryColumnDefinitionSeeder::class);

        $companies = $this->csv($path.'/companies.csv');
        $categories = $this->csv($path.'/categories.csv');
        $definitions = $this->csv($path.'/category_columns_definitions.csv');
        $assignments = $this->csv($path.'/category_columns.csv');
        $products = $this->csv($path.'/products.csv');

        $definitionIds = CategoryColumnDefinition::query()->pluck('id', 'name');
        $oldDefinitionNames = [];
        foreach ($definitions as $definition) {
            $oldDefinitionNames[$definition['id']] = $definition['name'];
        }

        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[$category['id']] = true;
        }

        $now = now();
        $skippedProducts = 0;

        DB::transaction(function () use ($path, $companies, $categories, $assignments, $products, $definitionIds, $oldDefinitionNames, $categoryIds, $now, &$skippedProducts): void {
            Product::withTrashed()->forceDelete();
            if (Schema::hasTable('factories')) {
                DB::table('factories')->delete();
            }
            DB::table('category_columns')->delete();
            Category::withTrashed()->update(['parent_id' => null]);
            Category::withTrashed()->forceDelete();
            DB::table('companies')->delete();

            DB::table('companies')->insert(array_map(function (array $company) use ($now): array {
                return [
                    'id' => (int) $company['id'],
                    'name' => $company['name'],
                    'letterhead' => $this->blankToNull($company['description']),
                    'logo' => $this->blankToNull($company['photo']),
                    'price_list_visible' => ($company['price_list'] ?? '0') === '0',
                    'usd_rate' => $this->rate($company['dolar'] ?? null),
                    'lme_rate' => $this->rate($company['lme'] ?? null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $companies));

            if (is_file($path.'/factories.csv')) {
                $this->insertFactories($this->csv($path.'/factories.csv'), $now);
            }

            DB::table('categories')->insert(array_map(function (array $category) use ($now): array {
                $deleted = ($category['is_deleted'] ?? '0') === '1';

                return [
                    'id' => (int) $category['id'],
                    'parent_id' => null,
                    'name' => $category['name'],
                    'image' => $this->blankToNull($category['image'] ?? null),
                    'description' => null,
                    'profit_margin' => $this->rate($category['profit_margin'] ?? null),
                    'company_id' => (int) $category['company_id'],
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => $deleted ? $now : null,
                ];
            }, $categories));

            foreach ($categories as $category) {
                $parentId = (int) ($category['parent_id'] ?? 0);
                if ($parentId === 0 || ! isset($categoryIds[(string) $parentId])) {
                    continue;
                }

                DB::table('categories')->where('id', (int) $category['id'])->update([
                    'parent_id' => $parentId,
                ]);
            }

            $pivot = [];
            foreach ($assignments as $assignment) {
                $name = $oldDefinitionNames[$assignment['column_id']] ?? null;
                $definitionId = $name === null ? null : $definitionIds[$name] ?? null;
                if ($definitionId === null || ! isset($categoryIds[$assignment['category_id']])) {
                    continue;
                }

                $pivot[] = [
                    'category_id' => (int) $assignment['category_id'],
                    'category_column_definition_id' => $definitionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($pivot, 200) as $chunk) {
                DB::table('category_columns')->insert($chunk);
            }

            $rows = [];
            foreach ($products as $product) {
                if (! isset($categoryIds[$product['kategori_iki']])) {
                    $skippedProducts++;

                    continue;
                }

                $deleted = ($product['silik'] ?? '0') === '1';
                $rows[] = [
                    'id' => (int) $product['urun_id'],
                    'category_id' => (int) $product['kategori_iki'],
                    'sku' => $this->blankToNull($product['urun_kodu']),
                    'shelf' => $this->blankToNull($product['urun_raf']),
                    'name' => $product['urun_adi'],
                    'description' => $this->blankToNull($product['urun_aciklama']),
                    'unit_weight_kg' => $this->rate($product['urun_birimkg']),
                    'length_measure' => $this->blankToNull($product['urun_boy_olcusu']),
                    'purchase_price' => $this->money($product['urun_alis']),
                    'sale_price' => $this->money($product['satis']),
                    'factory_id' => $this->optionalId($product['urun_fabrika']),
                    'customer_name' => $this->blankToNull($product['musteri_ismi']),
                    'due_on' => $this->legacyDate($product['termin']),
                    'pack_quantity' => $this->optionalId($product['pack_quantity']),
                    'company_id' => (int) $product['sirketid'],
                    'default_order_quantity' => $this->integer($product['urun_stok']),
                    'quantity_piece' => $this->integer($product['urun_adet']),
                    'quantity_pallet' => $this->integer($product['urun_palet']),
                    'warehouse_quantity' => $this->integer($product['urun_depo_adet']),
                    'low_stock_threshold' => $this->optionalId($product['urun_uyari_stok_adedi']),
                    'warehouse_low_stock_threshold' => $this->optionalId($product['urun_depo_uyari_adet']),
                    'is_active' => ! $deleted,
                    'sort_order' => $this->integer($product['urun_sira']),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => $deleted ? $now : null,
                ];
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('products')->insert($chunk);
            }

            foreach (['companies', 'categories', 'products', 'factories'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                DB::statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))",
                );
            }

            $this->attachFactoryForeignKey();

            if (is_file($path.'/vehicles.csv') && Schema::hasTable('vehicles')) {
                $this->insertVehicles($this->csv($path.'/vehicles.csv'));
            }
        });

        $this->info('Imported '.count($companies).' companies, '.count($categories).' categories, '.(count($products) - $skippedProducts).' products.');
        if ($skippedProducts > 0) {
            $this->warn("Skipped {$skippedProducts} products whose category is missing from the export.");
        }

        return self::SUCCESS;
    }

    private function importFactoriesOnly(string $path): int
    {
        $file = $path.'/factories.csv';
        if (! is_file($file)) {
            $this->error("Missing factories.csv in {$path}");

            return self::FAILURE;
        }

        $rows = $this->csv($file);
        $now = now();

        DB::transaction(function () use ($rows, $now): void {
            $this->insertFactories($rows, $now);
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('factories', 'id'), COALESCE((SELECT MAX(id) FROM factories), 1))",
            );
            $this->attachFactoryForeignKey();
        });

        $this->info('Imported '.count($rows).' factories.');

        return self::SUCCESS;
    }

    private function importVehiclesOnly(string $path): int
    {
        $file = $path.'/vehicles.csv';
        if (! is_file($file)) {
            $this->error("Missing vehicles.csv in {$path}");

            return self::FAILURE;
        }

        $count = $this->insertVehicles($this->csv($file));
        $this->info("Imported {$count} vehicles.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function insertVehicles(array $rows): int
    {
        $companyId = DB::table('products')->whereNotNull('company_id')->value('company_id');
        $imported = 0;

        DB::transaction(function () use ($rows, $companyId, &$imported): void {
            foreach ($rows as $row) {
                $name = $this->blankToNull($row['name'] ?? null);
                $plate = $this->blankToNull($row['license_plate'] ?? null);
                if ($name === null && $plate === null) {
                    continue;
                }

                $deleted = ($row['is_deleted'] ?? '0') === '1';
                $createdAt = $this->unixTime($row['time'] ?? null) ?? now();

                DB::table('vehicles')->updateOrInsert(
                    ['id' => (int) $row['id']],
                    [
                        'company_id' => $companyId,
                        'name' => $name ?? $plate,
                        'license_plate' => $plate,
                        'driver_name' => $this->blankToNull($row['driver'] ?? null),
                        'description' => $this->blankToNull($row['description'] ?? null),
                        'is_delivery_vehicle' => ($row['is_transport'] ?? '0') === '1',
                        'casco_expires_on' => $this->optionalDate($row['casco_end_date'] ?? null),
                        'insurance_expires_on' => $this->optionalDate($row['insurance_end_date'] ?? null),
                        'inspection_due_on' => $this->optionalDate($row['inspection_date'] ?? null),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'deleted_at' => $deleted ? $createdAt : null,
                    ],
                );

                $this->rememberVehicleDocument((int) $row['id'], VehicleDocument::Casco, $row['casco_pdf'] ?? null);
                $this->rememberVehicleDocument((int) $row['id'], VehicleDocument::TrafficInsurance, $row['insurance_pdf'] ?? null);
                $this->rememberVehicleDocument((int) $row['id'], VehicleDocument::Registration, $row['registration_pdf'] ?? null);
                $imported++;
            }

            DB::statement(
                "SELECT setval(pg_get_serial_sequence('vehicles', 'id'), COALESCE((SELECT MAX(id) FROM vehicles), 1))",
            );
        });

        return $imported;
    }

    private function rememberVehicleDocument(int $vehicleId, VehicleDocument $document, ?string $fileName): void
    {
        $fileName = $this->blankToNull($fileName);
        if ($fileName === null || ! Schema::hasTable('media')) {
            return;
        }

        $existing = DB::table('media')
            ->where('model_type', Vehicle::class)
            ->where('model_id', $vehicleId)
            ->where('collection', $document->value)
            ->first();

        if ($existing !== null && $existing->path !== null) {
            return;
        }

        $now = now();
        DB::table('media')->updateOrInsert(
            [
                'model_type' => Vehicle::class,
                'model_id' => $vehicleId,
                'collection' => $document->value,
            ],
            [
                'disk' => 'local',
                'path' => null,
                'file_name' => $fileName,
                'mime_type' => null,
                'size' => null,
                'created_at' => $existing->created_at ?? $now,
                'updated_at' => $now,
            ],
        );
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function insertFactories(array $rows, Carbon $now): void
    {
        $companyIds = DB::table('companies')->pluck('id')->flip();

        foreach ($rows as $row) {
            $companyId = (int) ($row['company_id'] ?? 0);
            $deleted = ($row['is_deleted'] ?? '0') === '1';

            DB::table('factories')->updateOrInsert(
                ['id' => (int) $row['id']],
                [
                    'company_id' => $companyIds->has($companyId) ? $companyId : null,
                    'name' => $row['name'],
                    'phone' => $this->blankToNull($row['phone'] ?? null),
                    'email' => $this->blankToNull($row['email'] ?? null),
                    'address' => $this->blankToNull($row['address'] ?? null),
                    'labor_cost' => $this->money($row['labor_cost'] ?? null),
                    'fine_labor_cost' => $this->money($row['fine_labor_cost'] ?? null),
                    'is_active' => ! $deleted,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => $deleted ? $now : null,
                ],
            );
        }
    }

    private function attachFactoryForeignKey(): void
    {
        if (! Schema::hasTable('factories') || Schema::hasForeignKey('products', ['factory_id'])) {
            return;
        }

        $missing = DB::table('products')
            ->whereNotNull('factory_id')
            ->whereNotIn('factory_id', DB::table('factories')->select('id'))
            ->exists();

        if ($missing) {
            $this->warn('Skipped the products.factory_id foreign key because some products point at a missing factory.');

            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('factory_id')->references('id')->on('factories')->nullOnDelete();
        });
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function csv(string $file): array
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);
        $rows = [];
        if ($header === false) {
            fclose($handle);

            return [];
        }

        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, array_pad($line, count($header), null));
        }

        fclose($handle);

        return $rows;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function rate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || (float) $value === 0.0) {
            return null;
        }

        return $value;
    }

    private function money(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '0' : $value;
    }

    private function integer(?string $value): int
    {
        return (int) trim((string) $value);
    }

    private function optionalDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        return $value;
    }

    private function unixTime(?string $value): ?Carbon
    {
        $time = (int) trim((string) $value);
        if ($time <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp($time);
    }

    private function optionalId(?string $value): ?int
    {
        $number = $this->integer($value);

        return $number > 0 ? $number : null;
    }

    private function legacyDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($date !== false) {
                return $date->toDateString();
            }
        }

        return null;
    }
}
