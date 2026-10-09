<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
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
}
