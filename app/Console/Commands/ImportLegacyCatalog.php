<?php

namespace App\Console\Commands;

use App\Enums\CatalogImage;
use App\Enums\CustomOrderStatus;
use App\Enums\DeliveryMethod;
use App\Enums\LeaveStatus;
use App\Enums\MoldDocument;
use App\Enums\QuoteStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StaffDocument;
use App\Enums\UserAccessLevel;
use App\Enums\VehicleDocument;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\CategoryColumnDefinition;
use App\Models\Mold;
use App\Models\OrganizationMember;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\LeaveRules;
use App\Support\StaffAccess;
use Carbon\Exceptions\InvalidFormatException;
use Database\Seeders\CategoryColumnDefinitionSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportLegacyCatalog extends Command
{
    protected $signature = 'stockpilot:import-legacy {path : Directory with the legacy CSV exports} {--only= : Import one dataset: factories, vehicles, custom-orders, clients, visits, jobs, organizations, users, factory-orders, molds, catalog, leaves, movements, stock-activities, quotes, shipments, or inventories}';

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

        if ($this->option('only') === 'custom-orders') {
            return $this->importCustomOrdersOnly($path);
        }

        if ($this->option('only') === 'clients') {
            return $this->importClientsOnly($path);
        }

        if ($this->option('only') === 'visits') {
            return $this->importVisitsOnly($path);
        }

        if ($this->option('only') === 'jobs') {
            return $this->importJobsOnly($path);
        }

        if ($this->option('only') === 'organizations') {
            return $this->importOrganizationsOnly($path);
        }

        if ($this->option('only') === 'users') {
            return $this->importUsersOnly($path);
        }

        if ($this->option('only') === 'factory-orders') {
            return $this->importFactoryOrdersOnly($path);
        }

        if ($this->option('only') === 'molds') {
            return $this->importMoldsOnly($path);
        }

        if ($this->option('only') === 'catalog') {
            return $this->importCatalogOnly($path);
        }

        if ($this->option('only') === 'leaves') {
            return $this->importLeavesOnly($path);
        }

        if ($this->option('only') === 'movements') {
            return $this->importMovementsOnly($path);
        }

        if ($this->option('only') === 'stock-activities') {
            return $this->importStockActivitiesOnly($path);
        }

        if ($this->option('only') === 'quotes') {
            return $this->importQuotesOnly($path);
        }

        if ($this->option('only') === 'shipments') {
            return $this->importShipmentsOnly($path);
        }

        if ($this->option('only') === 'inventories') {
            return $this->importInventoriesOnly($path);
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

            if (is_file($path.'/clients.csv') && Schema::hasTable('clients')) {
                $this->insertClients($this->csv($path.'/clients.csv'));
            }

            if (
                is_file($path.'/customer_visit_categories.csv')
                && is_file($path.'/ziyaretler.csv')
                && Schema::hasTable('customer_visits')
            ) {
                $this->insertVisits(
                    $this->csv($path.'/customer_visit_categories.csv'),
                    $this->csv($path.'/ziyaretler.csv'),
                );
            }

            if (is_file($path.'/jobs.csv') && Schema::hasTable('work_tasks')) {
                $this->insertJobs($this->csv($path.'/jobs.csv'));
            }

            if (is_file($path.'/users.csv') && Schema::hasTable('users')) {
                $this->insertUsers($this->csv($path.'/users.csv'));
            }

            if (is_file($path.'/organizations.csv') && Schema::hasTable('organization_members')) {
                $this->insertOrganizations($this->csv($path.'/organizations.csv'));
            }

            if (
                is_file($path.'/custom_orders.csv')
                && is_file($path.'/custom_order_items.csv')
                && Schema::hasTable('custom_orders')
            ) {
                $this->insertCustomOrders(
                    $this->csv($path.'/custom_orders.csv'),
                    $this->csv($path.'/custom_order_items.csv'),
                );
            }

            if (is_file($path.'/teklif.csv') && is_file($path.'/teklifformlari.csv')) {
                $this->insertQuotes(
                    $this->csv($path.'/teklifformlari.csv'),
                    $this->csv($path.'/teklif.csv'),
                    is_file($path.'/teklif_listesi.csv') ? $this->csv($path.'/teklif_listesi.csv') : [],
                );
            }

            if (is_file($path.'/sevkiyat.csv')) {
                $this->insertShipments($this->csv($path.'/sevkiyat.csv'));
            }

            if (is_file($path.'/inventories.csv')) {
                $this->insertInventories($this->csv($path.'/inventories.csv'));
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

    private function importClientsOnly(string $path): int
    {
        $file = $path.'/clients.csv';
        if (! is_file($file)) {
            $this->error("Missing clients.csv in {$path}");

            return self::FAILURE;
        }

        $count = $this->insertClients($this->csv($file));
        $this->info("Imported {$count} clients.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function insertClients(array $rows): int
    {
        $companyIds = DB::table('companies')->pluck('id')->flip();
        $existingIds = DB::table('clients')->pluck('id')->flip();
        $pending = array_values(array_filter(
            $rows,
            fn (array $row): bool => ! $existingIds->has((int) $row['id']),
        ));
        $imported = 0;

        $this->info('Clients already stored: '.(count($rows) - count($pending)).'. Remaining: '.count($pending).'.');

        foreach (array_chunk($pending, 50) as $chunk) {
            $imported += DB::transaction(function () use ($chunk, $companyIds): int {
                $count = 0;

                foreach ($chunk as $row) {
                    $name = $this->blankToNull($row['name'] ?? null);
                    if ($name === null) {
                        continue;
                    }

                    $deleted = ($row['is_deleted'] ?? '0') === '1';
                    $companyId = (int) ($row['company_id'] ?? 0);
                    $now = now();

                    DB::table('clients')->updateOrInsert(
                        ['id' => (int) $row['id']],
                        [
                            'company_id' => $companyIds->has($companyId) ? $companyId : null,
                            'name' => $name,
                            'phone' => $this->blankToNull($row['phone'] ?? null),
                            'email' => $this->blankToNull($row['email'] ?? null),
                            'address' => $this->blankToNull($row['address'] ?? null),
                            'is_active' => ! $deleted,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => $deleted ? $now : null,
                        ],
                    );
                    $count++;
                }

                return $count;
            });

            $this->info("Clients written: {$imported}");
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence('clients', 'id'), COALESCE((SELECT MAX(id) FROM clients), 1))",
        );

        return $imported;
    }

    private function importVisitsOnly(string $path): int
    {
        $categoriesFile = $path.'/customer_visit_categories.csv';
        $visitsFile = $path.'/ziyaretler.csv';
        if (! is_file($categoriesFile) || ! is_file($visitsFile)) {
            $this->error("Missing customer_visit_categories.csv or ziyaretler.csv in {$path}");

            return self::FAILURE;
        }

        $counts = $this->insertVisits($this->csv($categoriesFile), $this->csv($visitsFile));
        $this->info("Imported {$counts['categories']} visit categories and {$counts['visits']} visits.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $categories
     * @param  array<int, array<string, string|null>>  $visits
     * @return array{categories: int, visits: int}
     */
    private function insertVisits(array $categories, array $visits): array
    {
        $companyIds = DB::table('companies')->pluck('id')->flip();
        $fallbackCompanyId = DB::table('products')->whereNotNull('company_id')->value('company_id');
        $categoryCount = 0;
        $visitCount = 0;

        DB::transaction(function () use ($categories, $visits, $companyIds, $fallbackCompanyId, &$categoryCount, &$visitCount): void {
            foreach ($categories as $row) {
                $name = $this->blankToNull($row['name'] ?? null);
                if ($name === null) {
                    continue;
                }

                $deleted = ($row['is_deleted'] ?? '0') === '1';
                $companyId = (int) ($row['company_id'] ?? 0);
                $createdAt = $this->timestamp($row['date'] ?? null) ?? now();

                DB::table('customer_visit_categories')->updateOrInsert(
                    ['id' => (int) $row['id']],
                    [
                        'company_id' => $companyIds->has($companyId) ? $companyId : null,
                        'name' => $name,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'deleted_at' => $deleted ? $createdAt : null,
                    ],
                );
                $categoryCount++;
            }

            $categoryCompanies = DB::table('customer_visit_categories')->pluck('company_id', 'id');

            foreach ($visits as $row) {
                $customerName = $this->blankToNull($row['musteriismi'] ?? null);
                if ($customerName === null) {
                    continue;
                }

                $deleted = ($row['silik'] ?? '0') === '1';
                $categoryId = (int) ($row['iskolu'] ?? 0);
                $createdAt = $this->unixTime($row['saniye'] ?? null) ?? now();
                $companyId = $categoryCompanies->get($categoryId) ?: $fallbackCompanyId;

                DB::table('customer_visits')->updateOrInsert(
                    ['id' => (int) $row['id']],
                    [
                        'company_id' => $companyId,
                        'customer_visit_category_id' => $categoryCompanies->has($categoryId) ? $categoryId : null,
                        'city' => $this->blankToNull($row['il'] ?? null),
                        'district' => $this->blankToNull($row['ilce'] ?? null),
                        'customer_name' => $customerName,
                        'contact_name' => $this->blankToNull($row['yetkilikisi'] ?? null),
                        'phone' => $this->blankToNull($row['telefon'] ?? null),
                        'visited_on' => $this->legacyDay($row['ziyarettarihi'] ?? null),
                        'planned_on' => $this->legacyDay($row['planlanantarih'] ?? null),
                        'address' => $this->blankToNull($row['acikadres'] ?? null),
                        'notes' => $this->blankToNull($row['ziyaretnotu'] ?? null),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'deleted_at' => $deleted ? $createdAt : null,
                    ],
                );
                $visitCount++;
            }

            foreach (['customer_visit_categories', 'customer_visits'] as $table) {
                DB::statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))",
                );
            }
        });

        return [
            'categories' => $categoryCount,
            'visits' => $visitCount,
        ];
    }

    private function importJobsOnly(string $path): int
    {
        $file = $path.'/jobs.csv';
        if (! is_file($file)) {
            $this->error("Missing jobs.csv in {$path}");

            return self::FAILURE;
        }

        $count = $this->insertJobs($this->csv($file));
        $this->info("Imported {$count} jobs.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function insertJobs(array $rows): int
    {
        $fallbackCompanyId = DB::table('products')->whereNotNull('company_id')->value('company_id');
        $count = 0;

        DB::transaction(function () use ($rows, $fallbackCompanyId, &$count): void {
            foreach ($rows as $row) {
                $title = $this->blankToNull($row['job'] ?? null);
                if ($title === null) {
                    continue;
                }

                $deleted = ($row['is_deleted'] ?? '0') === '1';
                $createdAt = $this->timestamp($row['created_at'] ?? null) ?? now();

                DB::table('work_tasks')->updateOrInsert(
                    ['id' => (int) $row['id']],
                    [
                        'company_id' => $fallbackCompanyId,
                        'title' => $title,
                        'due_on' => $this->legacyDay($row['due_date'] ?? null),
                        'repeats_monthly' => ($row['is_repeated'] ?? '0') === '1',
                        'status' => ($row['status'] ?? '0') === '1' ? 'completed' : 'open',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'deleted_at' => $deleted ? $createdAt : null,
                    ],
                );
                $count++;
            }

            DB::statement(
                "SELECT setval(pg_get_serial_sequence('work_tasks', 'id'), COALESCE((SELECT MAX(id) FROM work_tasks), 1))",
            );
        });

        return $count;
    }

    private function importUsersOnly(string $path): int
    {
        $file = $path.'/users.csv';
        if (! is_file($file)) {
            $this->error("Missing users.csv in {$path}");

            return self::FAILURE;
        }

        $count = $this->insertUsers($this->csv($file));
        $this->info("Imported {$count} users.");

        $organizations = $path.'/organizations.csv';
        if (is_file($organizations) && Schema::hasTable('organization_members')) {
            $this->insertOrganizations($this->csv($organizations));
            $linked = DB::table('organization_members')->whereNotNull('user_id')->count();
            $this->info("Organization cards linked to a user: {$linked}.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function insertUsers(array $rows): int
    {
        $companyIds = DB::table('companies')->pluck('id')->flip();
        $reservedIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $reservedIds[] = $id;
            }
        }

        $count = 0;

        DB::transaction(function () use ($rows, $companyIds, $reservedIds, &$count): void {
            $this->parkDemoAccounts($reservedIds);

            $emails = DB::table('users')->pluck('email', 'id');

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $existing = DB::table('users')->where('id', $id)->first();
                if ($existing !== null && str_ends_with((string) $existing->email, '@stockpilot.test')) {
                    $this->warn("Skipped user {$id}: that id belongs to a demo account.");

                    continue;
                }

                $email = $this->blankToNull($row['email'] ?? null);
                if ($email === null) {
                    $this->warn("Skipped user {$id}: an email address is required.");

                    continue;
                }

                $ownerId = $emails->search($email);
                if ($ownerId !== false && (int) $ownerId !== $id) {
                    $this->warn("Skipped user {$id}: that email is already used.");

                    continue;
                }

                $companyId = (int) ($row['company_id'] ?? 0);
                $now = now();
                $deleted = ($row['is_deleted'] ?? '0') === '1';

                $payload = [
                    'company_id' => $companyIds->has($companyId) ? $companyId : null,
                    'name' => $this->blankToNull($row['name'] ?? null) ?? 'Personel',
                    'email' => $email,
                    'phone' => $this->blankToNull($row['phone'] ?? null),
                    'phone_2' => $this->blankToNull($row['phone_2'] ?? null),
                    'address' => $this->blankToNull($row['address'] ?? null),
                    'title' => $this->blankToNull($row['title'] ?? null),
                    'hired_on' => $this->legacyDay($row['hire_date'] ?? null),
                    'access_level' => UserAccessLevel::fromLegacy((int) ($row['type'] ?? 0))->value,
                    'access_flags' => json_encode(StaffAccess::fromLegacy($row['permissions'] ?? null)),
                    'is_active' => ($row['is_passive'] ?? '0') !== '1',
                    'deleted_at' => $deleted ? $now : null,
                    'updated_at' => $now,
                ];

                if ($existing === null) {
                    $payload['password'] = Hash::make(Str::password(40));
                    $payload['created_at'] = $now;
                }

                DB::table('users')->updateOrInsert(['id' => $id], $payload);

                foreach (StaffDocument::cases() as $document) {
                    $this->rememberStaffFile($id, $document, $row[$document->value] ?? null);
                }

                $emails[$id] = $email;
                $count++;
            }

            $this->syncSequence('users');
        });

        return $count;
    }

    /**
     * Demo logins occupy low ids. Move them so legacy staff ids can be preserved.
     *
     * @param  list<int>  $reservedIds
     */
    private function parkDemoAccounts(array $reservedIds): void
    {
        if ($reservedIds === []) {
            return;
        }

        $demos = DB::table('users')
            ->where('email', 'like', '%@stockpilot.test')
            ->whereIn('id', $reservedIds)
            ->orderBy('id')
            ->get();

        if ($demos->isEmpty()) {
            return;
        }

        $nextId = max($reservedIds);
        $currentMax = (int) DB::table('users')->max('id');
        if ($currentMax > $nextId) {
            $nextId = $currentMax;
        }

        foreach ($demos as $demo) {
            $oldId = (int) $demo->id;
            $nextId++;
            $email = $demo->email;

            DB::table('users')->where('id', $oldId)->update([
                'email' => 'moved-'.$oldId.'@stockpilot.test',
            ]);

            $copy = (array) $demo;
            $copy['id'] = $nextId;
            $copy['email'] = $email;
            DB::table('users')->insert($copy);

            foreach (['quotes', 'shipments', 'stock_movements', 'custom_orders', 'organization_members'] as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                    continue;
                }

                DB::table($table)->where('user_id', $oldId)->update(['user_id' => $nextId]);
            }

            foreach (['model_has_roles', 'model_has_permissions'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)
                    ->where('model_type', User::class)
                    ->where('model_id', $oldId)
                    ->update(['model_id' => $nextId]);
            }

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $oldId)->delete();
            }

            DB::table('users')->where('id', $oldId)->delete();
            $this->line("Moved a demo account off staff id {$oldId} to {$nextId}.");
        }
    }

    private function rememberStaffFile(int $userId, StaffDocument $document, ?string $fileName): void
    {
        $fileName = $this->blankToNull($fileName);
        if ($fileName === null || ! Schema::hasTable('media')) {
            return;
        }

        $existing = DB::table('media')
            ->where('model_type', User::class)
            ->where('model_id', $userId)
            ->where('collection', $document->value)
            ->first();

        if ($existing !== null && $existing->path !== null) {
            return;
        }

        $now = now();
        DB::table('media')->updateOrInsert(
            [
                'model_type' => User::class,
                'model_id' => $userId,
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

    private function importCatalogOnly(string $path): int
    {
        $file = $path.'/catalog.csv';

        if (! is_file($file)) {
            $this->error("Missing catalog.csv in {$path}");

            return self::FAILURE;
        }

        $this->info('Reading catalog file.');
        $written = $this->insertCatalogItems($this->csv($file));
        $this->info("Imported {$written} catalog rows.");

        return self::SUCCESS;
    }

    private function importLeavesOnly(string $path): int
    {
        $file = $path.'/leaves.csv';

        if (! is_file($file)) {
            $this->error("Missing leaves.csv in {$path}");

            return self::FAILURE;
        }

        $this->info('Reading leave file.');
        $result = $this->insertLeaves($this->csv($file));
        $this->info("Imported {$result['written']} leave rows.");

        if ($result['skipped'] > 0) {
            $this->warn("{$result['skipped']} leave rows were skipped because the staff record or the dates are missing.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{written: int, skipped: int}
     */
    private function insertLeaves(array $rows): array
    {
        $existing = DB::table('leaves')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $users = DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $companies = DB::table('companies')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $now = now();
        $pending = [];
        $already = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $userId = (int) ($row['user_id'] ?? 0);
            $start = $this->plainDate($row['start_date'] ?? null);
            $return = $this->plainDate($row['return_date'] ?? null);
            $status = LeaveStatus::tryFrom((int) ($row['status'] ?? -1));

            if ($id < 1 || ! isset($users[$userId]) || $start === null || $return === null || $status === null) {
                $skipped++;

                continue;
            }

            if (isset($existing[$id])) {
                $already++;

                continue;
            }

            $companyId = (int) ($row['company_id'] ?? 0);
            $createdAt = $this->unixTimestamp($row['time'] ?? null);
            $pending[] = [
                'id' => $id,
                'company_id' => isset($companies[$companyId]) ? $companyId : null,
                'user_id' => $userId,
                'start_on' => $start,
                'return_on' => $return,
                'leave_days' => LeaveRules::dayCount(Carbon::parse($start), Carbon::parse($return)),
                'status' => $status->value,
                'in_office' => ($row['office'] ?? '0') === '1',
                'created_at' => $createdAt?->toDateTimeString() ?? $now,
                'updated_at' => $now,
                'deleted_at' => ($row['is_deleted'] ?? '0') === '1' ? $now : null,
            ];
        }

        $this->info('Leave rows already stored: '.$already.'. Remaining: '.count($pending).'.');
        $written = 0;

        foreach (array_chunk($pending, 100) as $chunk) {
            DB::table('leaves')->insert($chunk);
            $written += count($chunk);
            $this->info("Leave rows written: {$written}");
        }

        $this->syncSequence('leaves');

        return ['written' => $written, 'skipped' => $skipped];
    }

    private function importMovementsOnly(string $path): int
    {
        $file = $path.'/movements.csv';

        if (! is_file($file)) {
            $this->error("Missing movements.csv in {$path}");

            return self::FAILURE;
        }

        $this->info('Reading movement file.');
        $result = $this->insertMovements($this->csv($file));
        $this->info("Imported {$result['written']} movement rows.");

        if ($result['skipped'] > 0) {
            $this->warn("{$result['skipped']} movement rows were skipped because the date is missing.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{written: int, skipped: int}
     */
    private function insertMovements(array $rows): array
    {
        $existing = DB::table('goods_flows')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $companies = DB::table('companies')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $now = now();
        $pending = [];
        $already = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $recordedOn = $this->plainDate($row['date'] ?? null);

            if ($id < 1 || $recordedOn === null) {
                $skipped++;

                continue;
            }

            if (isset($existing[$id])) {
                $already++;

                continue;
            }

            $companyId = (int) ($row['company_id'] ?? 0);
            $pending[] = [
                'id' => $id,
                'company_id' => isset($companies[$companyId]) ? $companyId : null,
                'recorded_on' => $recordedOn,
                'store_incoming' => $this->weight($row['incoming'] ?? null),
                'store_outgoing' => $this->weight($row['outgoing'] ?? null),
                'warehouse_incoming' => $this->weight($row['warehouse_incoming'] ?? null),
                'warehouse_outgoing' => $this->weight($row['warehouse_outgoing'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => ($row['is_deleted'] ?? '0') === '1' ? $now : null,
            ];
        }

        $this->info('Movement rows already stored: '.$already.'. Remaining: '.count($pending).'.');
        $written = 0;

        foreach (array_chunk($pending, 100) as $chunk) {
            DB::table('goods_flows')->insert($chunk);
            $written += count($chunk);
            $this->info("Movement rows written: {$written}");
        }

        $this->syncSequence('goods_flows');

        return ['written' => $written, 'skipped' => $skipped];
    }

    private function importStockActivitiesOnly(string $path): int
    {
        $file = $path.'/stock_activities.csv';

        if (! is_file($file)) {
            $this->error("Missing stock_activities.csv in {$path}");

            return self::FAILURE;
        }

        $this->info('Reading stock activity file.');
        $result = $this->insertStockActivities($file);
        $this->info("Imported {$result['written']} stock activity rows.");

        if ($result['skipped'] > 0) {
            $this->warn("{$result['skipped']} stock activity rows were skipped because the product or the row is missing.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{written: int, skipped: int}
     */
    private function insertStockActivities(string $file): array
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return ['written' => 0, 'skipped' => 0];
        }

        $header = fgetcsv($handle, null, ',', '"', '\\');

        if ($header === false) {
            fclose($handle);

            return ['written' => 0, 'skipped' => 0];
        }

        $existing = DB::table('stock_activities')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $products = DB::table('products')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $users = DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $companies = DB::table('companies')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $now = now();
        $pending = [];
        $already = 0;
        $skipped = 0;
        $written = 0;

        while (($line = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $row = array_combine($header, array_pad($line, count($header), null));

            if ($row === false) {
                $skipped++;

                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $productId = (int) ($row['product_id'] ?? 0);
            $place = (int) ($row['type'] ?? -1);
            $recordedAt = $this->activityTimestamp($row['datetime'] ?? null);

            if ($id < 1 || $recordedAt === null || ! in_array($place, [0, 1, 2], true) || ! isset($products[$productId])) {
                $skipped++;

                continue;
            }

            if (isset($existing[$id])) {
                $already++;

                continue;
            }

            $userId = (int) ($row['created_by'] ?? 0);
            $companyId = (int) ($row['company_id'] ?? 0);
            $pending[] = [
                'id' => $id,
                'company_id' => isset($companies[$companyId]) ? $companyId : null,
                'product_id' => $productId,
                'user_id' => isset($users[$userId]) ? $userId : null,
                'place' => $place,
                'previous_quantity' => (int) ($row['prev_quantity'] ?? 0),
                'new_quantity' => (int) ($row['new_quantity'] ?? 0),
                'note' => null,
                'recorded_at' => $recordedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($pending) < 500) {
                continue;
            }

            DB::table('stock_activities')->insert($pending);
            $written += count($pending);
            $pending = [];

            if ($written % 5000 === 0) {
                $this->info("Stock activity rows written: {$written}");
            }
        }

        fclose($handle);

        if ($pending !== []) {
            DB::table('stock_activities')->insert($pending);
            $written += count($pending);
        }

        $this->info('Stock activity rows already stored: '.$already.'. Written: '.$written.'.');

        if ($written > 0) {
            $this->info("Stock activity rows written: {$written}");
        }

        $this->syncSequence('stock_activities');

        return ['written' => $written, 'skipped' => $skipped];
    }

    private function activityTimestamp(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1 ? $value : null;
    }

    private function weight(?string $value): string
    {
        $value = str_replace(',', '.', trim((string) $value));

        return is_numeric($value) ? $value : '0';
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function insertCatalogItems(array $rows): int
    {
        $existing = DB::table('catalog_items')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $now = now();
        $pending = [];
        $files = [];
        $already = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $createdAt = $this->unixTimestamp($row['created_at'] ?? null);
            $record = [
                'id' => $id,
                'company_id' => null,
                'product_code' => $this->blankToNull($row['product'] ?? null),
                'code' => $this->blankToNull($row['code'] ?? null),
                'model' => $this->blankToNull($row['model'] ?? null),
                'quantity' => $this->blankToNull($row['quantity'] ?? null),
                'price' => $this->blankToNull($row['price'] ?? null),
                'description' => $this->blankToNull($row['description'] ?? null),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => $createdAt?->toDateTimeString() ?? $now,
                'updated_at' => $now,
                'deleted_at' => ($row['is_deleted'] ?? '0') === '1' ? $now : null,
            ];

            if (isset($existing[$id])) {
                $already++;
            } else {
                $pending[] = $record;
            }

            foreach ([
                CatalogImage::Primary->value => $row['image_1'] ?? null,
                CatalogImage::Secondary->value => $row['image_2'] ?? null,
            ] as $collection => $fileName) {
                $fileName = $this->blankToNull($fileName);
                if ($fileName !== null) {
                    $files[] = [$id, $collection, $fileName];
                }
            }
        }

        $this->info('Catalog rows already stored: '.$already.'. Remaining: '.count($pending).'.');
        $written = 0;
        foreach (array_chunk($pending, 100) as $chunk) {
            DB::table('catalog_items')->insert($chunk);
            $written += count($chunk);
            $this->info("Catalog rows written: {$written}");
        }

        foreach ($files as [$id, $collection, $fileName]) {
            $this->rememberCatalogFile($id, $collection, $fileName);
        }

        $this->syncSequence('catalog_items');

        return $written;
    }

    private function rememberCatalogFile(int $catalogItemId, string $collection, string $fileName): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $existing = DB::table('media')
            ->where('model_type', CatalogItem::class)
            ->where('model_id', $catalogItemId)
            ->where('collection', $collection)
            ->first();

        if ($existing !== null && $existing->path !== null) {
            return;
        }

        $now = now();
        DB::table('media')->updateOrInsert(
            [
                'model_type' => CatalogItem::class,
                'model_id' => $catalogItemId,
                'collection' => $collection,
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

    private function importMoldsOnly(string $path): int
    {
        $moldsFile = $path.'/molds.csv';
        $numbersFile = $path.'/mold_numbers.csv';

        if (! is_file($moldsFile) || ! is_file($numbersFile)) {
            $this->error("Missing molds.csv or mold_numbers.csv in {$path}");

            return self::FAILURE;
        }

        $this->info('Reading mold files.');
        $result = $this->insertMolds($this->csv($moldsFile), $this->csv($numbersFile));
        $this->info("Imported {$result['molds']} molds and {$result['numbers']} mold numbers.");

        if ($result['skipped_molds'] > 0) {
            $this->warn("{$result['skipped_molds']} molds were skipped because the client or factory is missing.");
        }

        if ($result['skipped_numbers'] > 0) {
            $this->warn("{$result['skipped_numbers']} mold numbers were skipped because the product or factory is missing, or that product already has a number at the factory.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $molds
     * @param  array<int, array<string, string|null>>  $numbers
     * @return array{molds: int, numbers: int, skipped_molds: int, skipped_numbers: int}
     */
    private function insertMolds(array $molds, array $numbers): array
    {
        $existingMolds = DB::table('molds')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $clientIds = DB::table('clients')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $factoryIds = DB::table('factories')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $userIds = DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $companyIds = DB::table('companies')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $now = now();
        $rows = [];
        $files = [];
        $skippedMolds = 0;
        $already = 0;

        foreach ($molds as $mold) {
            $id = (int) $mold['id'];
            $clientId = (int) $mold['client_id'];
            $factoryId = (int) $mold['factory_id'];

            if (! isset($clientIds[$clientId]) || ! isset($factoryIds[$factoryId])) {
                $skippedMolds++;

                continue;
            }

            $createdBy = (int) ($mold['created_by'] ?? 0);
            $companyId = (int) ($mold['company_id'] ?? 0);
            $record = [
                'id' => $id,
                'company_id' => isset($companyIds[$companyId]) ? $companyId : null,
                'client_id' => $clientId,
                'factory_id' => $factoryId,
                'number' => $this->blankToNull($mold['number'] ?? null),
                'client_offer_price' => $this->blankToNull($mold['client_offer_price'] ?? null),
                'factory_offer_price' => $this->blankToNull($mold['factory_offer_price'] ?? null),
                'due_on' => $this->plainDate($mold['due_date'] ?? null),
                'contact_name' => $this->blankToNull($mold['contact_person'] ?? null),
                'description' => $this->blankToNull($mold['description'] ?? null),
                'created_by_user_id' => isset($userIds[$createdBy]) ? $createdBy : null,
                'archived_at' => ($mold['is_archived'] ?? '0') === '1' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => ($mold['is_deleted'] ?? '0') === '1' ? $now : null,
            ];

            if (isset($existingMolds[$id])) {
                $already++;
            } else {
                $rows[] = $record;
            }

            foreach ([
                MoldDocument::FactoryApproval->value => $mold['factory_pdf'] ?? null,
                MoldDocument::ClientApproval->value => $mold['client_pdf'] ?? null,
                MoldDocument::Contract->value => $mold['contract_pdf'] ?? null,
            ] as $collection => $fileName) {
                $fileName = $this->blankToNull($fileName);
                if ($fileName !== null) {
                    $files[] = [$id, $collection, $fileName];
                }
            }
        }

        $this->info('Molds already stored: '.$already.'. Remaining: '.count($rows).'.');
        $written = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('molds')->insert($chunk);
            $written += count($chunk);
            $this->info("Molds written: {$written}");
        }

        foreach ($files as [$id, $collection, $fileName]) {
            $this->rememberMoldFile($id, $collection, $fileName);
        }

        $this->syncSequence('molds');

        $existingNumbers = DB::table('mold_numbers')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $productIds = DB::table('products')->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $pairs = [];
        foreach (DB::table('mold_numbers')->get(['product_id', 'factory_id']) as $pair) {
            $pairs[(int) $pair->product_id.'-'.(int) $pair->factory_id] = true;
        }

        $numberRows = [];
        $skippedNumbers = 0;
        $numbersAlready = 0;

        foreach ($numbers as $number) {
            $id = (int) $number['id'];
            $productId = (int) $number['product_id'];
            $factoryId = (int) $number['factory_id'];
            $pair = $productId.'-'.$factoryId;

            if (! isset($productIds[$productId]) || ! isset($factoryIds[$factoryId])) {
                $skippedNumbers++;

                continue;
            }

            if (isset($existingNumbers[$id])) {
                $numbersAlready++;

                continue;
            }

            if (isset($pairs[$pair])) {
                $skippedNumbers++;

                continue;
            }

            $numberRows[] = [
                'id' => $id,
                'product_id' => $productId,
                'factory_id' => $factoryId,
                'number' => $this->blankToNull($number['number'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $pairs[$pair] = true;
        }

        $this->info('Mold numbers already stored: '.$numbersAlready.'. Remaining: '.count($numberRows).'.');
        $numbersWritten = 0;
        foreach (array_chunk($numberRows, 200) as $chunk) {
            DB::table('mold_numbers')->insert($chunk);
            $numbersWritten += count($chunk);
            $this->info("Mold numbers written: {$numbersWritten}");
        }

        $this->syncSequence('mold_numbers');

        return [
            'molds' => $written,
            'numbers' => $numbersWritten,
            'skipped_molds' => $skippedMolds,
            'skipped_numbers' => $skippedNumbers,
        ];
    }

    private function rememberMoldFile(int $moldId, string $collection, string $fileName): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $existing = DB::table('media')
            ->where('model_type', Mold::class)
            ->where('model_id', $moldId)
            ->where('collection', $collection)
            ->first();

        if ($existing !== null && $existing->path !== null) {
            return;
        }

        $now = now();
        DB::table('media')->updateOrInsert(
            [
                'model_type' => Mold::class,
                'model_id' => $moldId,
                'collection' => $collection,
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

    private function plainDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || str_starts_with($value, '0000')) {
            return null;
        }

        return $value;
    }

    private function importFactoryOrdersOnly(string $path): int
    {
        $ordersFile = $path.'/siparis.csv';
        $formsFile = $path.'/siparisformlari.csv';

        if (! is_file($ordersFile) || ! is_file($formsFile)) {
            $this->error("Missing siparis.csv or siparisformlari.csv in {$path}");

            return self::FAILURE;
        }

        $this->info('Reading factory order files.');
        $result = $this->insertFactoryOrders($this->csv($formsFile), $this->csv($ordersFile));
        $this->info("Imported {$result['forms']} factory order forms and {$result['orders']} factory orders.");

        if ($result['unmatched'] > 0) {
            $this->warn("{$result['unmatched']} orders have no single matching user for the preparer; that link was left empty.");
        }

        if ($result['ambiguous'] > 0) {
            $this->warn("{$result['ambiguous']} orders match more than one user; that link was left empty.");
        }

        if ($result['skipped_factory'] > 0) {
            $this->warn("Skipped {$result['skipped_factory']} rows whose factory is not in the database.");
        }

        if ($result['duplicate_form_links'] > 0) {
            $this->warn("{$result['duplicate_form_links']} order lines were listed on more than one form; the earliest form was kept.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $forms
     * @param  array<int, array<string, string|null>>  $orders
     * @return array{forms: int, orders: int, unmatched: int, ambiguous: int, skipped_factory: int, duplicate_form_links: int}
     */
    private function insertFactoryOrders(array $forms, array $orders): array
    {
        $factoryIds = DB::table('factories')->pluck('id')->flip();
        $productIds = DB::table('products')->pluck('id')->flip();
        $companyIds = DB::table('companies')->pluck('id')->flip();
        $existingForms = DB::table('factory_order_forms')->pluck('id')->flip();
        $existingOrders = DB::table('factory_orders')->pluck('id')->flip();
        $formsAlready = count($existingForms);
        $ordersAlready = count($existingOrders);
        $usersByName = [];
        $ambiguousNames = [];

        foreach (DB::table('users')->get(['id', 'name']) as $user) {
            $key = $this->personKey((string) $user->name);
            if ($key === '') {
                continue;
            }

            if (isset($ambiguousNames[$key])) {
                continue;
            }

            if (isset($usersByName[$key])) {
                unset($usersByName[$key]);
                $ambiguousNames[$key] = true;

                continue;
            }

            $usersByName[$key] = (int) $user->id;
        }

        $now = now();
        $formRows = [];
        $newFormIds = [];
        $skippedFactory = 0;

        foreach ($forms as $form) {
            $id = (int) $form['formid'];
            $factoryId = (int) $form['fabrikaid'];

            if ($id === 0 || isset($existingForms[$id])) {
                continue;
            }

            if (! isset($factoryIds[$factoryId])) {
                $skippedFactory++;

                continue;
            }

            $createdAt = $this->unixTimestamp($form['saniye']) ?? $now;
            $companyId = (int) $form['sirketid'];
            $formRows[] = [
                'id' => $id,
                'company_id' => isset($companyIds[$companyId]) ? $companyId : null,
                'factory_id' => $factoryId,
                'prepared_by_user_id' => null,
                'contact_name' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'deleted_at' => ((string) ($form['silik'] ?? '0')) === '1' ? $createdAt : null,
            ];
            $newFormIds[$id] = true;
            $existingForms[$id] = true;
        }

        $this->info('Factory order forms already stored: '.$formsAlready.'. Remaining: '.count($formRows).'.');
        $formsWritten = 0;
        foreach (array_chunk($formRows, 200) as $chunk) {
            DB::table('factory_order_forms')->insert($chunk);
            $formsWritten += count($chunk);
            $this->info("Factory order forms written: {$formsWritten}");
        }

        $orderRows = [];
        $unmatched = 0;
        $ambiguous = 0;
        $orderPreparer = [];

        foreach ($orders as $order) {
            $id = (int) $order['siparis_id'];
            $factoryId = (int) $order['urun_fabrika_id'];
            $preparerKey = $this->personKey((string) ($order['hazirlayankisi'] ?? ''));
            $preparerId = $usersByName[$preparerKey] ?? null;

            if ($id !== 0) {
                $orderPreparer[$id] = [
                    'prepared_by_user_id' => $preparerId,
                    'contact_name' => $this->blankToNull($order['ilgilikisi']),
                ];
            }

            if ($id === 0 || isset($existingOrders[$id])) {
                continue;
            }

            if (! isset($factoryIds[$factoryId])) {
                $skippedFactory++;

                continue;
            }

            $createdAt = $this->unixTimestamp($order['siparissaniye']) ?? $now;
            $dueOn = $this->unixTimestamp($order['terminsaniye']);
            $companyId = (int) $order['sirketid'];
            $productId = (int) $order['urun_id'];

            if ($preparerKey !== '' && $preparerId === null) {
                if (isset($ambiguousNames[$preparerKey])) {
                    $ambiguous++;
                } else {
                    $unmatched++;
                }
            }

            $orderRows[] = [
                'id' => $id,
                'company_id' => isset($companyIds[$companyId]) ? $companyId : null,
                'factory_id' => $factoryId,
                'product_id' => isset($productIds[$productId]) ? $productId : null,
                'factory_order_form_id' => null,
                'prepared_by_user_id' => $preparerId,
                'product_name' => $this->blankToNull($order['urun_adi']) ?? 'Ürün',
                'contact_name' => $this->blankToNull($order['ilgilikisi']),
                'quantity' => max(0, (int) $order['urun_siparis_aded']),
                'length' => $this->blankToNull($order['siparisboy']),
                'pallet_count' => max(0, (int) ($order['palet'] ?? 0)),
                'due_on' => $dueOn?->toDateString(),
                'status' => ((string) $order['taslak']) === '1' ? 'open' : 'received',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'deleted_at' => ((string) ($order['silik'] ?? '0')) === '1' ? $createdAt : null,
            ];
            $existingOrders[$id] = true;
        }

        $this->info('Factory orders already stored: '.$ordersAlready.'. Remaining: '.count($orderRows).'.');
        $ordersWritten = 0;
        foreach (array_chunk($orderRows, 200) as $chunk) {
            DB::table('factory_orders')->insert($chunk);
            $ordersWritten += count($chunk);
            $this->info("Factory orders written: {$ordersWritten}");
        }

        usort($forms, fn (array $left, array $right): int => ((int) $left['formid']) <=> ((int) $right['formid']));

        $assignment = [];
        $duplicateLinks = 0;

        foreach ($forms as $form) {
            $formId = (int) $form['formid'];
            if (! isset($existingForms[$formId])) {
                continue;
            }

            foreach (explode(',', (string) ($form['siparisler'] ?? '')) as $part) {
                $orderId = (int) trim($part);
                if ($orderId === 0 || ! isset($existingOrders[$orderId])) {
                    continue;
                }

                if (isset($assignment[$orderId])) {
                    $duplicateLinks++;

                    continue;
                }

                $assignment[$orderId] = $formId;
            }
        }

        $byForm = [];
        foreach ($assignment as $orderId => $formId) {
            $byForm[$formId][] = $orderId;
        }

        $this->info('Linking order lines to '.count($byForm).' forms.');
        $linkedForms = 0;
        foreach ($byForm as $formId => $orderIds) {
            foreach (array_chunk($orderIds, 200) as $chunk) {
                DB::table('factory_orders')
                    ->whereIn('id', $chunk)
                    ->whereNull('factory_order_form_id')
                    ->update(['factory_order_form_id' => $formId]);
            }
            $linkedForms++;
            if ($linkedForms % 100 === 0) {
                $this->info("Forms linked: {$linkedForms}");
            }
        }
        $this->info("Forms linked: {$linkedForms}");

        $blankFormIds = DB::table('factory_order_forms')
            ->whereNull('prepared_by_user_id')
            ->whereNull('contact_name')
            ->pluck('id')
            ->flip();
        $headers = [];
        foreach ($assignment as $orderId => $formId) {
            if (! isset($newFormIds[$formId]) && ! isset($blankFormIds[$formId])) {
                continue;
            }

            if (! isset($orderPreparer[$orderId])) {
                continue;
            }

            if (! isset($headers[$formId]) || $orderId > $headers[$formId]['id']) {
                $headers[$formId] = [
                    'id' => $orderId,
                    'prepared_by_user_id' => $orderPreparer[$orderId]['prepared_by_user_id'],
                    'contact_name' => $orderPreparer[$orderId]['contact_name'],
                ];
            }
        }

        $this->writeFactoryOrderFormHeaders($headers);

        $this->syncSequence('factory_order_forms');
        $this->syncSequence('factory_orders');

        return [
            'forms' => count($formRows),
            'orders' => count($orderRows),
            'unmatched' => $unmatched,
            'ambiguous' => $ambiguous,
            'skipped_factory' => $skippedFactory,
            'duplicate_form_links' => $duplicateLinks,
        ];
    }

    /**
     * @param  array<int, array{id: int, prepared_by_user_id: int|null, contact_name: string|null}>  $headers
     */
    private function writeFactoryOrderFormHeaders(array $headers): void
    {
        $written = 0;
        $this->info('Form headers remaining: '.count($headers).'.');

        foreach (array_chunk($headers, 100, true) as $chunk) {
            if (DB::getDriverName() === 'pgsql') {
                $values = [];
                $bindings = [];
                foreach ($chunk as $formId => $header) {
                    $values[] = '(?::bigint, ?::bigint, ?)';
                    $bindings[] = $formId;
                    $bindings[] = $header['prepared_by_user_id'];
                    $bindings[] = $header['contact_name'];
                }

                DB::update(
                    'UPDATE factory_order_forms AS f
                    SET prepared_by_user_id = v.prepared_by_user_id, contact_name = v.contact_name
                    FROM (VALUES '.implode(', ', $values).') AS v(id, prepared_by_user_id, contact_name)
                    WHERE f.id = v.id',
                    $bindings,
                );
            } else {
                foreach ($chunk as $formId => $header) {
                    DB::table('factory_order_forms')->where('id', $formId)->update([
                        'prepared_by_user_id' => $header['prepared_by_user_id'],
                        'contact_name' => $header['contact_name'],
                    ]);
                }
            }

            $written += count($chunk);
            $this->info("Form headers written: {$written}");
        }
    }

    private function personKey(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtoupper($name, 'UTF-8');
    }

    private function unixTimestamp(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0' || ! ctype_digit($value)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value);
    }

    private function importQuotesOnly(string $path): int
    {
        $linesFile = $path.'/teklif.csv';
        $formsFile = $path.'/teklifformlari.csv';

        if (! is_file($linesFile) || ! is_file($formsFile)) {
            $this->error("Missing teklif.csv or teklifformlari.csv in {$path}");

            return self::FAILURE;
        }

        stream_set_write_buffer(STDOUT, 0);
        $this->info('Reading quote files.');

        $listFile = $path.'/teklif_listesi.csv';
        $result = $this->insertQuotes(
            $this->csv($formsFile),
            $this->csv($linesFile),
            is_file($listFile) ? $this->csv($listFile) : [],
        );

        $this->info("Imported {$result['quotes']} quotes and {$result['items']} quote lines.");

        if ($result['skipped_client'] > 0) {
            $this->warn("Skipped {$result['skipped_client']} quotes whose customer is not in the database.");
        }

        if ($result['skipped_user'] > 0) {
            $this->warn('Skipped the quote import because there is no user to attach them to.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $forms
     * @param  array<int, array<string, string|null>>  $lines
     * @param  array<int, array<string, string|null>>  $looseOffers
     * @return array{quotes: int, items: int, skipped_client: int, skipped_user: int}
     */
    private function insertQuotes(array $forms, array $lines, array $looseOffers): array
    {
        $empty = ['quotes' => 0, 'items' => 0, 'skipped_client' => 0, 'skipped_user' => 0];
        $userId = DB::table('users')->orderBy('id')->value('id');

        if ($userId === null) {
            $empty['skipped_user'] = 1;

            return $empty;
        }

        $clientIds = DB::table('clients')->pluck('id')->flip();
        $clientsByName = [];
        foreach (DB::table('clients')->select('id', 'name')->get() as $client) {
            $clientsByName[$this->personKey((string) $client->name)] ??= (int) $client->id;
        }
        $productNames = DB::table('products')->pluck('name', 'id');
        $companyIds = DB::table('companies')->pluck('id')->flip();
        $existingIds = DB::table('quotes')->pluck('id')->flip();
        $existingNumbers = DB::table('quotes')->pluck('number')->flip();
        $linesById = [];

        foreach ($lines as $line) {
            $lineId = (int) ($line['teklifid'] ?? 0);
            if ($lineId > 0) {
                $linesById[$lineId] = $line;
            }
        }

        $quotes = 0;
        $items = 0;
        $skippedClient = 0;
        $usedLines = [];
        $quoteBatch = [];
        $itemBatch = [];
        $now = now();

        usort($forms, fn (array $left, array $right): int => ((int) ($left['tformid'] ?? 0)) <=> ((int) ($right['tformid'] ?? 0)));

        foreach ($forms as $form) {
            $id = (int) ($form['tformid'] ?? 0);
            $clientId = (int) ($form['firmaid'] ?? 0);

            $number = 'F-'.$id;
            $lineIds = array_values(array_filter(array_map(
                intval(...),
                $this->legacyList($form['tekliflistesi'] ?? null),
            )));

            if ($id === 0) {
                continue;
            }

            if (isset($existingIds[$id]) || isset($existingNumbers[$number])) {
                foreach ($lineIds as $lineId) {
                    $usedLines[$lineId] = true;
                }

                continue;
            }

            if (! isset($clientIds[$clientId])) {
                $skippedClient++;

                continue;
            }

            $deleted = ($form['silik'] ?? '0') === '1';
            $withholding = ($form['withholding'] ?? '0') === '1';
            $taxRate = $withholding ? 6 : 20;
            $recordedAt = $this->unixTimestamp($form['saniye'] ?? null) ?? $now;
            $companyId = (int) ($form['sirketid'] ?? 0);
            $itemRows = [];
            $subtotal = 0.0;

            foreach ($lineIds as $sort => $lineId) {
                if (isset($usedLines[$lineId]) || ! isset($linesById[$lineId])) {
                    continue;
                }

                $usedLines[$lineId] = true;
                $line = $linesById[$lineId];
                $productId = (int) ($line['turunid'] ?? 0);
                $knownProduct = $productId > 0 && isset($productNames[$productId]);
                $quantity = $this->integer($line['tadet'] ?? null);
                $unitPrice = (float) $this->money($line['tsatisfiyati'] ?? null);
                $lineTotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineTotal;
                $itemRows[] = [
                    'quote_id' => $id,
                    'product_id' => $knownProduct ? $productId : null,
                    'description' => $this->clip($knownProduct ? (string) $productNames[$productId] : ($productId > 0 ? 'Ürün '.$productId : 'Kalem')),
                    'quantity_piece' => $quantity,
                    'quantity_pallet' => 0,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                    'sort_order' => $sort,
                    'created_at' => $recordedAt,
                    'updated_at' => $recordedAt,
                ];
            }

            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $quoteBatch[] = [
                'id' => $id,
                'company_id' => isset($companyIds[$companyId]) ? $companyId : null,
                'number' => $number,
                'client_id' => $clientId,
                'user_id' => (int) $userId,
                'status' => $deleted ? QuoteStatus::Cancelled->value : QuoteStatus::Sent->value,
                'quote_date' => $recordedAt->toDateString(),
                'valid_until' => null,
                'currency' => 'TRY',
                'tax_rate' => $taxRate,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'total' => number_format($subtotal + $taxAmount, 2, '.', ''),
                'notes' => $this->blankToNull($form['explanation'] ?? null),
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
                'deleted_at' => $deleted ? $recordedAt : null,
            ];
            array_push($itemBatch, ...$itemRows);
            $existingIds[$id] = true;
            $existingNumbers[$number] = true;
            $quotes++;
            $items += count($itemRows);

            if (count($quoteBatch) >= 100) {
                $this->writeInChunks('quotes', $quoteBatch);
                $this->writeInChunks('quote_items', $itemBatch);
                $quoteBatch = [];
                $itemBatch = [];
                $this->reportProgress("Quotes written: {$quotes}");
            }
        }

        if ($quoteBatch !== []) {
            $this->writeInChunks('quotes', $quoteBatch);
            $this->writeInChunks('quote_items', $itemBatch);
            $this->reportProgress("Quotes written: {$quotes}");
        }

        $this->syncSequence('quotes');

        foreach ($linesById as $lineId => $line) {
            if (isset($usedLines[$lineId])) {
                continue;
            }

            $clientId = (int) ($line['tverilenfirma'] ?? 0);
            $number = 'L-'.$lineId;

            if (! isset($clientIds[$clientId]) || isset($existingNumbers[$number])) {
                if (! isset($clientIds[$clientId])) {
                    $skippedClient++;
                }

                continue;
            }

            $deleted = ($line['silik'] ?? '0') === '1';
            $recordedAt = $this->unixTimestamp($line['tsaniye'] ?? null) ?? $now;
            $companyId = (int) ($line['sirketid'] ?? 0);
            $productId = (int) ($line['turunid'] ?? 0);
            $knownProduct = $productId > 0 && isset($productNames[$productId]);
            $quantity = $this->integer($line['tadet'] ?? null);
            $unitPrice = (float) $this->money($line['tsatisfiyati'] ?? null);
            $lineTotal = round($quantity * $unitPrice, 2);
            $taxAmount = round($lineTotal * 0.20, 2);
            $quoteId = DB::table('quotes')->insertGetId([
                'company_id' => isset($companyIds[$companyId]) ? $companyId : null,
                'number' => $number,
                'client_id' => $clientId,
                'user_id' => (int) $userId,
                'status' => $deleted ? QuoteStatus::Cancelled->value : QuoteStatus::Draft->value,
                'quote_date' => $recordedAt->toDateString(),
                'valid_until' => null,
                'currency' => 'TRY',
                'tax_rate' => 20,
                'subtotal' => number_format($lineTotal, 2, '.', ''),
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'total' => number_format($lineTotal + $taxAmount, 2, '.', ''),
                'notes' => null,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
                'deleted_at' => $deleted ? $recordedAt : null,
            ]);
            DB::table('quote_items')->insert([
                'quote_id' => $quoteId,
                'product_id' => $knownProduct ? $productId : null,
                'description' => $this->clip($knownProduct ? (string) $productNames[$productId] : ($productId > 0 ? 'Ürün '.$productId : 'Kalem')),
                'quantity_piece' => $quantity,
                'quantity_pallet' => 0,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'sort_order' => 0,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ]);
            $existingNumbers[$number] = true;
            $quotes++;
            $items++;
        }

        foreach ($looseOffers as $offer) {
            $offerId = (int) ($offer['teklifid'] ?? 0);
            $number = 'TL-'.$offerId;
            $clientId = $clientsByName[$this->personKey((string) ($offer['musteri'] ?? ''))] ?? null;

            if ($offerId === 0 || isset($existingNumbers[$number])) {
                continue;
            }

            if ($clientId === null) {
                $skippedClient++;

                continue;
            }

            $recordedAt = $this->plainDate($offer['tarih'] ?? null);
            $quoteDate = $recordedAt ?? $now->toDateString();
            $stamp = $recordedAt === null ? $now : Carbon::parse($recordedAt);
            $archived = ($offer['silik'] ?? '0') !== '0';
            $unitPrice = (float) $this->money($offer['fiyat'] ?? null);
            $lineTotal = round($unitPrice, 2);
            $taxAmount = round($lineTotal * 0.20, 2);
            $notes = array_values(array_filter([
                $this->blankToNull($offer['aciklama'] ?? null),
                $this->blankToNull($offer['ilgilikisi'] ?? null),
                $this->blankToNull($offer['fabrika'] ?? null),
                ($offer['fabrikafiyat'] ?? '') !== '' ? 'Fabrika fiyatı: '.$offer['fabrikafiyat'] : null,
                $this->blankToNull($offer['teklifveren'] ?? null),
            ]));
            $quoteId = DB::table('quotes')->insertGetId([
                'company_id' => null,
                'number' => $number,
                'client_id' => (int) $clientId,
                'user_id' => (int) $userId,
                'status' => $archived ? QuoteStatus::Accepted->value : QuoteStatus::Sent->value,
                'quote_date' => $quoteDate,
                'valid_until' => null,
                'currency' => 'TRY',
                'tax_rate' => 20,
                'subtotal' => number_format($lineTotal, 2, '.', ''),
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'total' => number_format($lineTotal + $taxAmount, 2, '.', ''),
                'notes' => $notes === [] ? null : implode("\n", $notes),
                'created_at' => $stamp,
                'updated_at' => $stamp,
                'deleted_at' => null,
            ]);
            DB::table('quote_items')->insert([
                'quote_id' => $quoteId,
                'product_id' => null,
                'description' => $this->clip($this->blankToNull($offer['urunmiktar'] ?? null) ?? 'Kalem'),
                'quantity_piece' => 1,
                'quantity_pallet' => 0,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'sort_order' => 0,
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ]);
            $existingNumbers[$number] = true;
            $quotes++;
            $items++;
        }

        $this->syncSequence('quotes');
        $this->syncSequence('quote_items');

        return [
            'quotes' => $quotes,
            'items' => $items,
            'skipped_client' => $skippedClient,
            'skipped_user' => 0,
        ];
    }

    private function importShipmentsOnly(string $path): int
    {
        $file = $path.'/sevkiyat.csv';

        if (! is_file($file)) {
            $this->error("Missing sevkiyat.csv in {$path}");

            return self::FAILURE;
        }

        stream_set_write_buffer(STDOUT, 0);
        $this->info('Reading shipment file.');
        $result = $this->insertShipments($this->csv($file));
        $this->info("Imported {$result['shipments']} shipments and {$result['items']} shipment lines.");

        if ($result['skipped_client'] > 0) {
            $this->warn("Skipped {$result['skipped_client']} shipments whose customer is not in the database.");
        }

        if ($result['skipped_user'] > 0) {
            $this->warn('Skipped the shipment import because there is no user to attach them to.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{shipments: int, items: int, skipped_client: int, skipped_user: int}
     */
    private function insertShipments(array $rows): array
    {
        $empty = ['shipments' => 0, 'items' => 0, 'skipped_client' => 0, 'skipped_user' => 0];
        $userId = DB::table('users')->orderBy('id')->value('id');

        if ($userId === null) {
            $empty['skipped_user'] = 1;

            return $empty;
        }

        $userIds = DB::table('users')->pluck('id')->flip();
        $clientIds = DB::table('clients')->pluck('id')->flip();
        $productNames = DB::table('products')->pluck('name', 'id');
        $companyIds = DB::table('companies')->pluck('id')->flip();
        $vehicles = DB::table('vehicles')->get(['id', 'license_plate', 'driver_name'])->keyBy('id');
        $existingIds = DB::table('shipments')->pluck('id')->flip();
        $existingNumbers = DB::table('shipments')->pluck('number')->flip();
        $deliveryLabels = [
            '0' => 'Müşteri Çağlayan',
            '1' => 'Müşteri Alkop',
            '2' => 'Tarafımızca sevk',
            '3' => 'Ambara tarafımızca sevk',
            '4' => 'Kargo teslim',
        ];
        $shipments = 0;
        $items = 0;
        $skippedClient = 0;
        $shipmentBatch = [];
        $itemBatch = [];
        $now = now();

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $clientId = (int) ($row['firma_id'] ?? 0);

            if ($id === 0 || isset($existingIds[$id])) {
                continue;
            }

            if (! isset($clientIds[$clientId])) {
                $skippedClient++;

                continue;
            }

            $number = 'SV-'.$id;
            if (isset($existingNumbers[$number])) {
                continue;
            }

            $creatorId = (int) ($row['olusturan'] ?? 0);
            $ownerId = isset($userIds[$creatorId]) ? $creatorId : (int) $userId;
            $deleted = ($row['silik'] ?? '0') === '1';
            $recordedAt = $this->unixTimestamp($row['saniye'] ?? null) ?? $now;
            $companyId = (int) ($row['sirket_id'] ?? 0);
            $vehicle = $vehicles->get((int) ($row['arac_id'] ?? 0));
            $delivery = $deliveryLabels[$row['sevk_tipi'] ?? ''] ?? null;
            $notes = array_values(array_filter([
                $this->blankToNull($row['aciklama'] ?? null),
                $delivery,
                ($row['manuel'] ?? '0') === '1' ? 'Manuel kayıt' : null,
                ($row['durum'] ?? '') === '1' ? 'Hazırlanıyor' : null,
                $this->blankToNull($row['kilolar'] ?? null) !== null ? 'Kilo: '.trim((string) $row['kilolar']) : null,
            ]));
            $status = match ($row['durum'] ?? '0') {
                '2', '3' => ShipmentStatus::Delivered->value,
                default => ShipmentStatus::Scheduled->value,
            };
            $productIds = $this->legacyList($row['urunler'] ?? null);
            $quantities = $this->legacyList($row['adetler'] ?? null);
            $prices = $this->legacyList($row['fiyatlar'] ?? null, '-');
            $itemRows = [];

            foreach ($productIds as $sort => $productValue) {
                $productId = (int) $productValue;
                $knownProduct = $productId > 0 && isset($productNames[$productId]);
                $price = trim((string) ($prices[$sort] ?? ''));
                $name = $knownProduct ? (string) $productNames[$productId] : ($productId > 0 ? 'Ürün '.$productId : 'Kalem');
                $itemRows[] = [
                    'shipment_id' => $id,
                    'product_id' => $knownProduct ? $productId : null,
                    'description' => $this->clip($price === '' ? $name : $name.' · '.$price.' TL'),
                    'quantity_piece' => $this->integer($quantities[$sort] ?? null),
                    'quantity_pallet' => 0,
                    'sort_order' => $sort,
                    'created_at' => $recordedAt,
                    'updated_at' => $recordedAt,
                ];
            }

            $shipmentBatch[] = [
                'id' => $id,
                'company_id' => isset($companyIds[$companyId]) ? $companyId : null,
                'number' => $number,
                'client_id' => $clientId,
                'quote_id' => null,
                'user_id' => $ownerId,
                'status' => $deleted ? ShipmentStatus::Cancelled->value : $status,
                'ship_date' => $recordedAt->toDateString(),
                'delivery_date' => null,
                'vehicle_plate' => $vehicle?->license_plate,
                'driver_name' => $vehicle?->driver_name,
                'shipping_address' => null,
                'notes' => $notes === [] ? null : implode("\n", $notes),
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
                'deleted_at' => $deleted ? $recordedAt : null,
            ];
            array_push($itemBatch, ...$itemRows);
            $existingIds[$id] = true;
            $existingNumbers[$number] = true;
            $shipments++;
            $items += count($itemRows);

            if (count($shipmentBatch) >= 100) {
                $this->writeInChunks('shipments', $shipmentBatch);
                $this->writeInChunks('shipment_items', $itemBatch);
                $shipmentBatch = [];
                $itemBatch = [];
                $this->reportProgress("Shipments written: {$shipments}");
            }
        }

        if ($shipmentBatch !== []) {
            $this->writeInChunks('shipments', $shipmentBatch);
            $this->writeInChunks('shipment_items', $itemBatch);
            $this->reportProgress("Shipments written: {$shipments}");
        }

        $this->syncSequence('shipments');
        $this->syncSequence('shipment_items');

        return [
            'shipments' => $shipments,
            'items' => $items,
            'skipped_client' => $skippedClient,
            'skipped_user' => 0,
        ];
    }

    private function importInventoriesOnly(string $path): int
    {
        $file = $path.'/inventories.csv';

        if (! is_file($file)) {
            $this->error("Missing inventories.csv in {$path}");

            return self::FAILURE;
        }

        $result = $this->insertInventories($this->csv($file));
        $this->info("Imported {$result['written']} inventory rows.");

        if ($result['skipped'] > 0) {
            $this->warn("Skipped {$result['skipped']} inventory rows whose category is not in the database.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{written: int, skipped: int}
     */
    private function insertInventories(array $rows): array
    {
        $categoryIds = DB::table('categories')->pluck('id')->flip();
        $companyIds = DB::table('companies')->pluck('id')->flip();
        $existingIds = DB::table('inventories')->pluck('id')->flip();
        $written = 0;
        $skipped = 0;
        $now = now();
        $pending = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $categoryId = (int) ($row['category_id'] ?? 0);

            if ($id === 0 || isset($existingIds[$id])) {
                continue;
            }

            if (! isset($categoryIds[$categoryId])) {
                $skipped++;

                continue;
            }

            $companyId = (int) ($row['company_id'] ?? 0);
            $deleted = ($row['is_deleted'] ?? '0') === '1';
            $pending[] = [
                'id' => $id,
                'company_id' => isset($companyIds[$companyId]) ? $companyId : null,
                'category_id' => $categoryId,
                'code' => $this->limited($row['code'] ?? null, 32),
                'dimension_1' => $this->limited($row['dimension_1'] ?? null, 32),
                'dimension_2' => $this->limited($row['dimension_2'] ?? null, 32),
                'dimension_3' => $this->limited($row['dimension_3'] ?? null, 32),
                'density' => $this->limited($row['density'] ?? null, 32),
                'factory_name' => $this->limited($row['factory_name'] ?? null, 64),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => $deleted ? $now : null,
            ];
            $existingIds[$id] = true;
        }

        foreach (array_chunk($pending, 200) as $chunk) {
            DB::table('inventories')->insert($chunk);
            $written += count($chunk);
        }

        $this->syncSequence('inventories');

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * @return list<string>
     */
    private function legacyList(?string $value, string $separator = ','): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            explode($separator, $value),
        ), fn (string $part) => $part !== ''));
    }

    private function syncSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))",
        );
    }

    private function importOrganizationsOnly(string $path): int
    {
        $file = $path.'/organizations.csv';
        if (! is_file($file)) {
            $this->error("Missing organizations.csv in {$path}");

            return self::FAILURE;
        }

        $count = $this->insertOrganizations($this->csv($file));
        $this->info("Imported {$count} organization members.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function insertOrganizations(array $rows): int
    {
        $userIds = DB::table('users')->pluck('id')->flip();
        $fallbackCompanyId = DB::table('products')->whereNotNull('company_id')->value('company_id');
        $count = 0;

        DB::transaction(function () use ($rows, $userIds, $fallbackCompanyId, &$count): void {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $userId = (int) ($row['user_id'] ?? 0);
                $createdAt = $this->timestamp($row['created_at'] ?? null) ?? now();

                DB::table('organization_members')->updateOrInsert(
                    ['id' => $id],
                    [
                        'company_id' => $fallbackCompanyId,
                        'user_id' => $userIds->has($userId) ? $userId : null,
                        'name' => $this->blankToNull($row['name'] ?? null),
                        'title' => $this->blankToNull($row['title'] ?? null),
                        'position' => $id,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ],
                );

                $this->rememberOrganizationPhoto($id, $row['photo'] ?? null);
                $count++;
            }

            $this->syncSequence('organization_members');
        });

        return $count;
    }

    private function rememberOrganizationPhoto(int $memberId, ?string $fileName): void
    {
        $fileName = $this->blankToNull($fileName);
        if ($fileName === null || ! Schema::hasTable('media')) {
            return;
        }

        $existing = DB::table('media')
            ->where('model_type', OrganizationMember::class)
            ->where('model_id', $memberId)
            ->where('collection', OrganizationMember::PHOTO)
            ->first();

        if ($existing !== null && $existing->path !== null) {
            return;
        }

        $now = now();
        DB::table('media')->updateOrInsert(
            [
                'model_type' => OrganizationMember::class,
                'model_id' => $memberId,
                'collection' => OrganizationMember::PHOTO,
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

    private function importCustomOrdersOnly(string $path): int
    {
        $ordersFile = $path.'/custom_orders.csv';
        $itemsFile = $path.'/custom_order_items.csv';
        if (! is_file($ordersFile) || ! is_file($itemsFile)) {
            $this->error("Missing custom_orders.csv or custom_order_items.csv in {$path}");

            return self::FAILURE;
        }

        $counts = $this->insertCustomOrders($this->csv($ordersFile), $this->csv($itemsFile));
        $this->info("Imported {$counts['orders']} custom orders and {$counts['items']} lines.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string|null>>  $orders
     * @param  array<int, array<string, string|null>>  $items
     * @return array{orders: int, items: int}
     */
    private function insertCustomOrders(array $orders, array $items): array
    {
        $companyIds = DB::table('companies')->pluck('id')->flip();
        $clientIds = Schema::hasTable('clients') ? DB::table('clients')->pluck('id')->flip() : collect();
        $userIds = DB::table('users')->pluck('id')->flip();
        $factoryIds = Schema::hasTable('factories') ? DB::table('factories')->pluck('id')->flip() : collect();
        $importedOrders = 0;
        $importedItems = 0;

        DB::transaction(function () use ($orders, $items, $companyIds, $clientIds, $userIds, $factoryIds, &$importedOrders, &$importedItems): void {
            foreach ($orders as $row) {
                $orderedAt = $this->timestamp($row['datetime'] ?? null) ?? now();
                $deleted = ($row['is_deleted'] ?? '0') === '1';
                $companyId = (int) ($row['company_id'] ?? 0);
                $clientId = (int) ($row['client_id'] ?? 0);
                $userId = (int) ($row['created_by'] ?? 0);

                DB::table('custom_orders')->updateOrInsert(
                    ['id' => (int) $row['id']],
                    [
                        'company_id' => $companyIds->has($companyId) ? $companyId : null,
                        'client_id' => $clientIds->has($clientId) ? $clientId : null,
                        'user_id' => $userIds->has($userId) ? $userId : null,
                        'delivery_method' => DeliveryMethod::fromLegacy($row['delivery_type'] ?? null)->value,
                        'status' => ($row['status'] ?? '0') === '1'
                            ? CustomOrderStatus::Closed->value
                            : CustomOrderStatus::Open->value,
                        'notes' => $this->blankToNull($row['description'] ?? null),
                        'ordered_at' => $orderedAt,
                        'created_at' => $orderedAt,
                        'updated_at' => $orderedAt,
                        'deleted_at' => $deleted ? $orderedAt : null,
                    ],
                );
                $importedOrders++;
            }

            $orderIds = DB::table('custom_orders')->pluck('id')->flip();

            foreach ($items as $row) {
                $orderId = (int) ($row['custom_order_id'] ?? 0);
                $productName = $this->blankToNull($row['product'] ?? null);
                if (! $orderIds->has($orderId) || $productName === null) {
                    continue;
                }

                $createdAt = $this->timestamp($row['datetime'] ?? null) ?? now();
                $deleted = ($row['is_deleted'] ?? '0') === '1';
                $factoryId = (int) ($row['factory_id'] ?? 0);

                DB::table('custom_order_items')->updateOrInsert(
                    ['id' => (int) $row['id']],
                    [
                        'custom_order_id' => $orderId,
                        'factory_id' => $factoryIds->has($factoryId) ? $factoryId : null,
                        'product_name' => $productName,
                        'length' => $this->blankToNull($row['length'] ?? null),
                        'quantity' => $this->money($row['quantity'] ?? null),
                        'unit_price' => $this->money($row['price'] ?? null),
                        'due_on' => $this->optionalDate($row['due_date'] ?? null),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'deleted_at' => $deleted ? $createdAt : null,
                    ],
                );
                $importedItems++;
            }

            foreach (['custom_orders', 'custom_order_items'] as $table) {
                DB::statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))",
                );
            }
        });

        return [
            'orders' => $importedOrders,
            'items' => $importedItems,
        ];
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

        $header = fgetcsv($handle, null, ',', '"', '\\');
        $rows = [];
        if ($header === false) {
            fclose($handle);

            return [];
        }

        while (($line = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($header, array_pad($line, count($header), null));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeInChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function reportProgress(string $message): void
    {
        $this->info($message);
        flush();
    }

    private function clip(string $value): string
    {
        return mb_substr($value, 0, 255);
    }

    private function limited(?string $value, int $length): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $length);
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

    private function legacyDay(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (InvalidFormatException) {
                continue;
            }
        }

        return null;
    }

    private function timestamp(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
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
