<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryColumnDefinition;
use App\Models\Product;
use Carbon\Exceptions\InvalidFormatException;
use Database\Seeders\CategoryColumnDefinitionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportLegacyCatalog extends Command
{
    protected $signature = 'stockpilot:import-legacy {path : Directory with the legacy CSV exports}';

    protected $description = 'Import companies, categories, column layout, and products from local CSV exports';

    public function handle(): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

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

        DB::transaction(function () use ($companies, $categories, $assignments, $products, $definitionIds, $oldDefinitionNames, $categoryIds, $now, &$skippedProducts): void {
            Product::withTrashed()->forceDelete();
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

            foreach (['companies', 'categories', 'products'] as $table) {
                DB::statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))",
                );
            }
        });

        $this->info('Imported '.count($companies).' companies, '.count($categories).' categories, '.(count($products) - $skippedProducts).' products.');
        if ($skippedProducts > 0) {
            $this->warn("Skipped {$skippedProducts} products whose category is missing from the export.");
        }

        return self::SUCCESS;
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
