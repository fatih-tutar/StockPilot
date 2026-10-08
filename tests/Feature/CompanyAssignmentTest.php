<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompanyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_records_use_the_signed_in_users_company(): void
    {
        Permission::findOrCreate('clients.manage');

        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('clients.manage');

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'name' => 'Yeni Müşteri',
                'company_id' => $other->id,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'name' => 'Yeni Müşteri',
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create(['company_id' => $other->id]);
        $movement = StockMovement::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity_piece_delta' => 1,
            'company_id' => $other->id,
        ]);

        $this->assertSame($company->id, $product->company_id);
        $this->assertSame($company->id, $product->category->company_id);
        $this->assertSame($company->id, $movement->company_id);
    }

    public function test_a_company_user_does_not_see_another_companys_records(): void
    {
        $this->withoutVite();
        Permission::findOrCreate('stock.view');

        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('stock.view');
        $ownCategory = Category::factory()->create([
            'name' => 'Bizim kategori',
            'company_id' => $company->id,
        ]);
        $otherCategory = Category::factory()->create([
            'name' => 'Baska kategori',
            'company_id' => $other->id,
        ]);
        $ownProduct = Product::factory()->create([
            'name' => 'Bizim urun',
            'company_id' => $company->id,
            'category_id' => $ownCategory->id,
        ]);
        Product::factory()->create([
            'name' => 'Baska urun',
            'company_id' => $other->id,
            'category_id' => $otherCategory->id,
        ]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'categories',
                fn ($categories) => collect($categories)->pluck('id')->all() === [$ownCategory->id],
            ));

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products.data.0.name', 'Bizim urun')
                ->where('products.data.0.id', $ownProduct->id)
                ->has('products.data', 1));
    }
}
