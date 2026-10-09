<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryColumnDefinition;
use App\Models\Client;
use App\Models\Product;
use App\Models\QuoteItem;
use App\Models\User;
use Database\Seeders\CategoryColumnDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CategoryColumnPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(CategoryColumnDefinitionSeeder::class);
    }

    public function test_root_category_columns_apply_to_the_child_product_list(): void
    {
        Permission::findOrCreate('stock.manage');
        Permission::findOrCreate('columns.piece');

        $user = User::factory()->create();
        $user->givePermissionTo(['stock.manage', 'columns.piece']);

        $parent = Category::factory()->create(['name' => 'Profil']);
        $child = Category::factory()->create(['name' => 'Boru', 'parent_id' => $parent->id]);
        $shelf = CategoryColumnDefinition::query()->where('name', 'shelf')->firstOrFail();
        $quantity = CategoryColumnDefinition::query()->where('name', 'quantity')->firstOrFail();
        $code = CategoryColumnDefinition::query()->where('name', 'product_code')->firstOrFail();

        $this->actingAs($user)
            ->put(route('categories.update', $parent), [
                'name' => 'Profil',
                'parent_id' => null,
                'sort_order' => 0,
                'column_ids' => [$shelf->id, $quantity->id],
            ])
            ->assertRedirect();

        $this->assertTrue($parent->fresh()->columnDefinitions()->whereKey($shelf->id)->exists());
        $this->assertSame(0, $child->fresh()->columnDefinitions()->count());

        Product::factory()->create([
            'category_id' => $child->id,
            'name' => '30x30 boru',
            'sku' => 'GIZLI',
            'shelf' => 'A1',
            'quantity_piece' => 4,
        ]);

        $this->actingAs($user)
            ->get(route('products.index', ['category_id' => $child->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sheet.columns.0.label', 'Adet')
                ->where('sheet.columns.1.label', 'Raf')
                ->where('products.data.0.cells.shelf', 'A1')
                ->where('products.data.0.cells.quantity', 4)
                ->missing('products.data.0.cells.product_code'));

        $this->assertFalse(
            $parent->columnDefinitions()->whereKey($code->id)->exists(),
        );
    }

    public function test_editing_a_child_category_leaves_the_parent_columns_unchanged(): void
    {
        Permission::findOrCreate('stock.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('stock.manage');
        $parent = Category::factory()->create(['name' => 'Profil']);
        $child = Category::factory()->create(['name' => 'Boru', 'parent_id' => $parent->id]);
        $shelf = CategoryColumnDefinition::query()->where('name', 'shelf')->firstOrFail();
        $quantity = CategoryColumnDefinition::query()->where('name', 'quantity')->firstOrFail();
        $parent->columnDefinitions()->sync([$shelf->id]);

        $this->actingAs($user)
            ->put(route('categories.update', $child), [
                'name' => 'Boru',
                'parent_id' => $parent->id,
                'sort_order' => 0,
                'column_ids' => [$quantity->id],
            ])
            ->assertRedirect();

        $this->assertTrue($parent->fresh()->columnDefinitions()->whereKey($shelf->id)->exists());
        $this->assertFalse($parent->columnDefinitions()->whereKey($quantity->id)->exists());
        $this->assertSame(0, $child->fresh()->columnDefinitions()->count());
    }

    public function test_hidden_quantity_stays_off_when_the_person_cannot_see_piece_counts(): void
    {
        $user = User::factory()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $quantity = CategoryColumnDefinition::query()->where('name', 'quantity')->firstOrFail();
        $parent->columnDefinitions()->sync([$quantity->id]);
        Product::factory()->create([
            'category_id' => $child->id,
            'name' => 'Gizli adet',
            'quantity_piece' => 9,
        ]);

        $this->actingAs($user)
            ->get(route('products.index', ['category_id' => $child->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sheet.columns', [])
                ->where('products.data.0.quantity_piece', null));
    }

    public function test_offer_button_creates_a_draft_quote_for_the_product(): void
    {
        Permission::findOrCreate('quotes.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('quotes.manage');
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $offer = CategoryColumnDefinition::query()->where('name', 'offer_button')->firstOrFail();
        $parent->columnDefinitions()->sync([$offer->id]);
        $product = Product::factory()->create([
            'category_id' => $child->id,
            'name' => 'Teklif profil',
            'unit_weight_kg' => 2,
            'sale_price' => 10,
        ]);
        $client = Client::factory()->create(['name' => 'Deneme Firma']);

        $this->actingAs($user)
            ->post(route('products.quote', $product), [
                'client_name' => 'Deneme Firma',
                'quantity_piece' => 3,
                'unit_price' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $item = QuoteItem::query()->where('product_id', $product->id)->first();
        $this->assertNotNull($item);
        $this->assertSame($client->id, $item->quote->client_id);
        $this->assertSame(60.0, (float) $item->line_total);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->post(route('products.quote', $product), [
                'client_name' => 'Deneme Firma',
                'quantity_piece' => 1,
                'unit_price' => 10,
            ])
            ->assertForbidden();
    }

    public function test_product_form_saves_a_column_that_the_category_shows(): void
    {
        Permission::findOrCreate('stock.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('stock.manage');
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $shelf = CategoryColumnDefinition::query()->where('name', 'shelf')->firstOrFail();
        $parent->columnDefinitions()->sync([$shelf->id]);
        $product = Product::factory()->create([
            'category_id' => $child->id,
            'name' => 'Raflı',
            'shelf' => null,
        ]);

        $this->actingAs($user)
            ->put(route('products.update', $product), [
                'category_id' => $child->id,
                'name' => 'Raflı',
                'shelf' => 'B4',
                'sku' => 'YAZILMAMALI',
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('B4', $product->shelf);
        $this->assertSame('YAZILMAMALI', $product->sku);
    }

    public function test_order_and_warning_counts_stay_on_the_form_and_off_the_list(): void
    {
        Permission::findOrCreate('columns.piece');
        Permission::findOrCreate('factories.view');
        $user = User::factory()->create();
        $user->givePermissionTo(['columns.piece', 'factories.view']);
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $parent->columnDefinitions()->sync(
            CategoryColumnDefinition::query()
                ->whereIn('name', ['quantity', 'factory', 'order_quantity', 'warning_count', 'warehouse_warning_count'])
                ->pluck('id'),
        );
        $product = Product::factory()->create([
            'category_id' => $child->id,
            'name' => 'Liste disi adet',
        ]);

        $this->actingAs($user)
            ->get(route('products.index', ['category_id' => $child->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sheet.columns.0.name', 'quantity')
                ->where('sheet.columns.1.name', 'factory')
                ->missing('sheet.columns.2'));

        $this->actingAs($user)
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('fields.2', 'order_quantity')
                ->where('fields.3', 'warning_count')
                ->where('fields.4', 'warehouse_warning_count'));
    }
}
