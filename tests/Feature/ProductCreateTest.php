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
        $category = Category::factory()->create();

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
}
