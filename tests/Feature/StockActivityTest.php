<?php

namespace Tests\Feature;

use App\Enums\StockActivityPlace;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StockActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_product_page_lists_recent_stock_activity_for_that_product(): void
    {
        Permission::findOrCreate('stock.view');
        $user = User::factory()->create();
        $user->givePermissionTo('stock.view');
        $product = Product::factory()->create();
        $other = Product::factory()->create();

        StockActivity::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'place' => StockActivityPlace::Store,
            'previous_quantity' => 10,
            'new_quantity' => 12,
            'recorded_at' => '2024-06-01 09:15:00',
        ]);
        StockActivity::factory()->create([
            'product_id' => $product->id,
            'place' => StockActivityPlace::Pallet,
            'previous_quantity' => 3,
            'new_quantity' => 1,
            'recorded_at' => '2024-06-02 11:00:00',
        ]);
        StockActivity::factory()->create([
            'product_id' => $other->id,
            'previous_quantity' => 80,
            'new_quantity' => 90,
        ]);

        $this->actingAs($user)
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activities.0.place', 'Palet')
                ->where('activities.0.previous_quantity', 3)
                ->where('activities.0.new_quantity', 1)
                ->where('activities.0.difference', -2)
                ->where('activities.0.recorded_at', '02.06.2024 11:00')
                ->where('activities.1.place', 'Mağaza')
                ->where('activities.1.difference', 2)
                ->where('activities.1.user.name', $user->name)
                ->where('activities', fn ($rows) => count($rows) === 2));
    }

    public function test_adjusting_stock_records_the_place_that_changed(): void
    {
        Permission::findOrCreate('stock.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('stock.manage');
        $product = Product::factory()->create([
            'quantity_piece' => 10,
            'quantity_pallet' => 4,
        ]);

        $this->actingAs($user)
            ->post(route('products.adjust-stock', $product), [
                'quantity_piece_delta' => 2,
                'quantity_pallet_delta' => 0,
                'note' => 'sayım',
            ])
            ->assertRedirect(route('products.edit', $product));

        $activity = StockActivity::query()->where('product_id', $product->id)->first();

        $this->assertNotNull($activity);
        $this->assertSame(StockActivityPlace::Store, $activity->place);
        $this->assertSame(10, $activity->previous_quantity);
        $this->assertSame(12, $activity->new_quantity);
        $this->assertSame('sayım', $activity->note);
        $this->assertSame(1, StockActivity::query()->count());
    }

    public function test_legacy_import_keeps_ids_and_skips_missing_products(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $directory = storage_path('framework/testing/stock-activity-import');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/stock_activities.csv', implode("\n", [
            'id,created_by,product_id,prev_quantity,new_quantity,company_id,datetime,type',
            "15,{$user->id},{$product->id},4,9,{$company->id},2024-03-02 08:00:00,2",
            '16,1,999999,1,2,2,2024-03-03 08:00:00,0',
            "17,888888,{$product->id},1,2,99999,2024-03-04 08:00:00,1",
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'stock-activities',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'stock-activities',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $pallet = StockActivity::query()->find(15);
        $warehouse = StockActivity::query()->find(17);

        $this->assertNotNull($pallet);
        $this->assertSame(StockActivityPlace::Pallet, $pallet->place);
        $this->assertSame(4, $pallet->previous_quantity);
        $this->assertSame(9, $pallet->new_quantity);
        $this->assertSame($company->id, $pallet->company_id);
        $this->assertSame($user->id, $pallet->user_id);
        $this->assertNull(StockActivity::query()->find(16));
        $this->assertNotNull($warehouse);
        $this->assertSame(StockActivityPlace::Warehouse, $warehouse->place);
        $this->assertNull($warehouse->user_id);
        $this->assertNull($warehouse->company_id);
        $this->assertSame(2, StockActivity::query()->count());
    }

    public function test_the_activity_list_shows_every_product_and_can_focus_one(): void
    {
        Permission::findOrCreate('stock.view');
        $user = User::factory()->create();
        $user->givePermissionTo('stock.view');
        $first = Product::factory()->create(['name' => 'Profil A']);
        $second = Product::factory()->create(['name' => 'Profil B']);

        StockActivity::factory()->create([
            'product_id' => $first->id,
            'previous_quantity' => 1,
            'new_quantity' => 4,
        ]);
        StockActivity::factory()->create([
            'product_id' => $second->id,
            'place' => StockActivityPlace::Warehouse,
            'previous_quantity' => 8,
            'new_quantity' => 5,
        ]);

        $this->actingAs($user)
            ->get(route('stock-activities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('product', null)
                ->where('activities.0.product.name', 'Profil B')
                ->where('activities.0.place', 'Depo')
                ->where('activities.0.difference', -3)
                ->where('activities.1.product.name', 'Profil A')
                ->where('activities', fn ($rows) => count($rows) === 2));

        $this->actingAs($user)
            ->get(route('stock-activities.index', ['product_id' => $first->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('product.name', 'Profil A')
                ->where('activities', fn ($rows) => count($rows) === 1 && $rows[0]['product']['name'] === 'Profil A'));
    }

    public function test_a_company_user_does_not_see_another_companys_activities(): void
    {
        Permission::findOrCreate('stock.view');
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('stock.view');
        $product = Product::factory()->create();

        StockActivity::factory()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'previous_quantity' => 2,
            'new_quantity' => 3,
        ]);
        StockActivity::factory()->create([
            'company_id' => $other->id,
            'product_id' => $product->id,
            'previous_quantity' => 20,
            'new_quantity' => 30,
        ]);

        $this->actingAs($user)
            ->get(route('stock-activities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activities', fn ($rows) => count($rows) === 1 && $rows[0]['difference'] === 1));
    }

    public function test_the_activity_list_requires_stock_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stock-activities.index'))
            ->assertForbidden();
    }
}
