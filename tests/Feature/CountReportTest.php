<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CountReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function userWithReportPermission(): User
    {
        Permission::findOrCreate('count_reports.view');

        $user = User::factory()->create();
        $user->givePermissionTo('count_reports.view');

        return $user;
    }

    public function test_guest_is_redirected_from_the_count_report(): void
    {
        $this->get(route('count-reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_count_report_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('count-reports.index'))
            ->assertForbidden();
    }

    public function test_caglayan_report_lists_piece_counts_under_the_parent_category(): void
    {
        $user = $this->userWithReportPermission();
        $main = Category::factory()->create(['name' => 'Profil']);
        $sub = Category::factory()->create(['name' => 'Kutu', 'parent_id' => $main->id]);
        $root = Category::factory()->create(['name' => 'Köksüz']);

        Product::factory()->create([
            'category_id' => $sub->id,
            'name' => '40x40 kutu',
            'quantity_piece' => 12,
            'quantity_pallet' => 3,
            'warehouse_quantity' => 8,
            'shelf' => 'A1',
            'sort_order' => 1,
        ]);
        Product::factory()->create([
            'category_id' => $root->id,
            'name' => 'Köksüz ürün',
        ]);
        $removed = Product::factory()->create([
            'category_id' => $sub->id,
            'name' => 'Silinmiş ürün',
        ]);
        $removed->delete();

        $this->actingAs($user)
            ->get(route('count-reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CountReports/Index')
                ->where('place', 'caglayan')
                ->where('title', 'Çağlayan Sayım Raporu')
                ->has('groups', 1)
                ->where('groups.0.main', 'Profil')
                ->where('groups.0.sub', 'Kutu')
                ->has('groups.0.products', 1)
                ->where('groups.0.products.0.name', '40x40 kutu')
                ->where('groups.0.products.0.piece', 12));
    }

    public function test_alkop_report_keeps_pallet_warehouse_and_shelf(): void
    {
        $user = $this->userWithReportPermission();
        $main = Category::factory()->create(['name' => 'Boru']);
        $sub = Category::factory()->create(['name' => 'İnce', 'parent_id' => $main->id]);

        Product::factory()->create([
            'category_id' => $sub->id,
            'name' => '30x30 boru',
            'quantity_piece' => 4,
            'quantity_pallet' => 2,
            'warehouse_quantity' => 9,
            'shelf' => 'D4',
        ]);

        $this->actingAs($user)
            ->get(route('count-reports.index', ['place' => 'alkop']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CountReports/Index')
                ->where('place', 'alkop')
                ->where('title', 'Alkop Sayım Raporu')
                ->where('groups.0.products.0.pallet', 2)
                ->where('groups.0.products.0.warehouse', 9)
                ->where('groups.0.products.0.shelf', 'D4'));
    }
}
