<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryColumnDefinition;
use App\Models\Factory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CategoryColumnDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_product_returns_to_the_product_list(): void
    {
        $this->withoutVite();
        Permission::findOrCreate('stock.manage');

        $user = User::factory()->create();
        $user->givePermissionTo('stock.manage');
        $parent = Category::factory()->create(['name' => 'Profiller']);
        $category = Category::factory()->create([
            'name' => 'Boru',
            'parent_id' => $parent->id,
        ]);

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.store'), [
                'category_id' => $category->id,
                'name' => 'Yeni profil',
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success', 'Ürün oluşturuldu.');

        $this->assertDatabaseHas('products', [
            'name' => 'Yeni profil',
            'category_id' => $category->id,
        ]);
    }

    public function test_a_product_cannot_be_added_without_a_subcategory(): void
    {
        $this->withoutVite();
        Permission::findOrCreate('stock.manage');

        $user = User::factory()->create();
        $user->givePermissionTo('stock.manage');
        $parent = Category::factory()->create();

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.store'), [
                'name' => 'Kategorisiz',
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['category_id' => 'Alt kategori seçin.']);

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.store'), [
                'category_id' => $parent->id,
                'name' => 'Ana kategoriye',
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['category_id' => 'Ürün yalnızca bir alt kategoriye eklenebilir.']);

        $this->assertDatabaseMissing('products', ['name' => 'Kategorisiz']);
        $this->assertDatabaseMissing('products', ['name' => 'Ana kategoriye']);
    }

    public function test_the_product_list_offers_each_category_fields(): void
    {
        $this->withoutVite();
        $this->seed(CategoryColumnDefinitionSeeder::class);
        Permission::findOrCreate('stock.manage');
        Permission::findOrCreate('factories.view');

        $user = User::factory()->create();
        $user->givePermissionTo(['stock.manage', 'factories.view']);
        $parent = Category::factory()->create(['name' => 'Profiller']);
        $category = Category::factory()->create([
            'name' => 'Boru',
            'parent_id' => $parent->id,
        ]);
        $shelf = CategoryColumnDefinition::query()->where('name', 'shelf')->firstOrFail();
        $factoryColumn = CategoryColumnDefinition::query()->where('name', 'factory')->firstOrFail();
        $parent->columnDefinitions()->sync([$shelf->id, $factoryColumn->id]);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('categories', function ($categories) use ($category): bool {
                $match = collect($categories)->firstWhere('id', $category->id);

                return $match !== null && $match['fields'] === ['shelf', 'factory'];
            }));
    }

    public function test_optional_details_from_the_category_can_be_saved_with_the_product(): void
    {
        $this->withoutVite();
        $this->seed(CategoryColumnDefinitionSeeder::class);
        Permission::findOrCreate('stock.manage');
        Permission::findOrCreate('factories.view');
        Permission::findOrCreate('columns.purchase');

        $user = User::factory()->create();
        $user->givePermissionTo(['stock.manage', 'factories.view', 'columns.purchase']);
        $parent = Category::factory()->create(['name' => 'Profiller']);
        $category = Category::factory()->create([
            'name' => 'Boru',
            'parent_id' => $parent->id,
        ]);
        $shelf = CategoryColumnDefinition::query()->where('name', 'shelf')->firstOrFail();
        $factoryColumn = CategoryColumnDefinition::query()->where('name', 'factory')->firstOrFail();
        $purchase = CategoryColumnDefinition::query()->where('name', 'purchase_price')->firstOrFail();
        $parent->columnDefinitions()->sync([$shelf->id, $factoryColumn->id, $purchase->id]);
        $factory = Factory::factory()->create(['name' => 'Deneme Fabrika']);

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.store'), [
                'category_id' => $category->id,
                'name' => 'Detaylı profil',
                'sku' => 'BORU-1',
                'shelf' => 'C2',
                'factory_id' => $factory->id,
                'mold_number' => 'K-14',
                'purchase_price' => null,
                'customer_name' => 'Yazılmamalı',
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Detaylı profil')->firstOrFail();

        $this->assertModelExists($product);
        $this->assertSame('BORU-1', $product->sku);
        $this->assertSame('C2', $product->shelf);
        $this->assertSame('0.00', $product->purchase_price);
        $this->assertSame($factory->id, $product->factory_id);
        $this->assertNull($product->customer_name);
        $this->assertDatabaseHas('mold_numbers', [
            'product_id' => $product->id,
            'factory_id' => $factory->id,
            'number' => 'K-14',
        ]);
    }
}
