<?php

namespace Tests\Feature;

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
}
