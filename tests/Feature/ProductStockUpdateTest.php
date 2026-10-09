<?php

namespace Tests\Feature;

use App\Enums\StockActivityPlace;
use App\Models\Product;
use App\Models\StockActivity;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductStockUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_piece_count_is_stored_as_a_store_activity(): void
    {
        $user = $this->stockUser(['columns.piece', 'columns.pallet', 'columns.alkop']);
        $product = Product::factory()->create([
            'quantity_piece' => 10,
            'warehouse_quantity' => 4,
            'quantity_pallet' => 2,
        ]);

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.set-stock', $product), [
                'quantity_piece' => 12,
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertSame(12, $product->quantity_piece);
        $this->assertSame(4, $product->warehouse_quantity);
        $this->assertSame(2, $product->quantity_pallet);

        $activity = StockActivity::query()->where('product_id', $product->id)->first();
        $this->assertNotNull($activity);
        $this->assertSame(StockActivityPlace::Store, $activity->place);
        $this->assertSame(10, $activity->previous_quantity);
        $this->assertSame(12, $activity->new_quantity);
        $this->assertSame(1, StockActivity::query()->count());
        $this->assertSame(2, StockMovement::query()->value('quantity_piece_delta'));
    }

    public function test_a_pallet_change_is_recorded_with_the_warehouse_total(): void
    {
        $user = $this->stockUser(['columns.piece', 'columns.pallet', 'columns.alkop']);
        $product = Product::factory()->create([
            'quantity_piece' => 10,
            'warehouse_quantity' => 4,
            'quantity_pallet' => 2,
        ]);

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.set-stock', $product), [
                'quantity_pallet' => 5,
                'warehouse_quantity' => 8,
            ])
            ->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame(10, $product->quantity_piece);
        $this->assertSame(8, $product->warehouse_quantity);
        $this->assertSame(5, $product->quantity_pallet);

        $activity = StockActivity::query()->where('product_id', $product->id)->first();
        $this->assertNotNull($activity);
        $this->assertSame(StockActivityPlace::Warehouse, $activity->place);
        $this->assertSame(6, $activity->previous_quantity);
        $this->assertSame(13, $activity->new_quantity);
        $this->assertSame(0, StockActivity::query()->where('place', StockActivityPlace::Pallet)->count());
        $this->assertSame(3, StockMovement::query()->value('quantity_pallet_delta'));
    }

    public function test_a_blank_quantity_is_left_unchanged(): void
    {
        $user = $this->stockUser(['columns.piece', 'columns.pallet', 'columns.alkop']);
        $product = Product::factory()->create([
            'quantity_piece' => 10,
            'warehouse_quantity' => 4,
            'quantity_pallet' => 2,
        ]);

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.set-stock', $product), [])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasErrors([
                'quantity_piece' => 'En az bir yeni adet girin.',
            ]);

        $product->refresh();
        $this->assertSame(10, $product->quantity_piece);
        $this->assertSame(0, StockActivity::query()->count());
    }

    public function test_a_hidden_stock_column_cannot_be_changed(): void
    {
        $user = $this->stockUser(['columns.piece']);
        $product = Product::factory()->create([
            'quantity_piece' => 10,
            'warehouse_quantity' => 4,
            'quantity_pallet' => 2,
        ]);

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.set-stock', $product), [
                'quantity_piece' => 11,
                'warehouse_quantity' => 99,
                'quantity_pallet' => 99,
            ])
            ->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame(11, $product->quantity_piece);
        $this->assertSame(4, $product->warehouse_quantity);
        $this->assertSame(2, $product->quantity_pallet);
        $this->assertSame(StockActivityPlace::Store, StockActivity::query()->first()->place);
    }

    public function test_a_user_without_stock_access_cannot_set_quantities(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->post(route('products.set-stock', $product), [
                'quantity_piece' => 3,
            ])
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function stockUser(array $permissions): User
    {
        Permission::findOrCreate('stock.manage');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['stock.manage', ...$permissions]);

        return $user;
    }
}
