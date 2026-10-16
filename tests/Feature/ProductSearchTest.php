<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shipment_user_gets_matching_product_names(): void
    {
        Permission::findOrCreate('shipments.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('shipments.manage');
        $match = Product::factory()->create(['name' => '20x20 beyaz', 'is_active' => true]);
        Product::factory()->create(['name' => 'Başka Profil', 'is_active' => true]);
        $removed = Product::factory()->create(['name' => '20x20 eski', 'is_active' => true]);
        $removed->delete();

        $this->actingAs($user)
            ->getJson(route('products.search', ['term' => '20x20']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $match->id)
            ->assertJsonPath('0.name', '20x20 beyaz');

        $this->actingAs($user)
            ->getJson(route('products.search', ['term' => '   ']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_product_search_returns_at_most_ten_names(): void
    {
        Permission::findOrCreate('shipments.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('shipments.manage');

        foreach (range(1, 11) as $number) {
            Product::factory()->create(['name' => sprintf('Arama %02d', $number), 'is_active' => true]);
        }

        $this->actingAs($user)
            ->getJson(route('products.search', ['term' => 'Arama']))
            ->assertOk()
            ->assertJsonCount(10)
            ->assertJsonMissing(['name' => 'Arama 11']);
    }

    public function test_product_search_hides_another_companys_products(): void
    {
        Permission::findOrCreate('shipments.manage');
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('shipments.manage');
        $own = Product::factory()->create([
            'company_id' => $company->id,
            'name' => 'Ortak Profil',
            'is_active' => true,
        ]);
        Product::factory()->create([
            'company_id' => $other->id,
            'name' => 'Ortak Kutu',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson(route('products.search', ['term' => 'Ortak']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $own->id);
    }

    public function test_product_search_skips_inactive_products(): void
    {
        Permission::findOrCreate('quotes.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('quotes.manage');
        Product::factory()->create(['name' => 'Pasif Profil', 'is_active' => false]);

        $this->actingAs($user)
            ->getJson(route('products.search', ['term' => 'Pasif']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_a_user_without_order_access_cannot_search_products(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Gizli Profil', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('products.search', ['term' => 'Gizli']))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_from_product_search(): void
    {
        $this->get(route('products.search', ['term' => '20']))
            ->assertRedirect(route('login'));
    }
}
