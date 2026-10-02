<?php

namespace Tests\Feature;

use App\Enums\CustomOrderStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomOrder;
use App\Models\Factory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(?int $companyId = null): User
    {
        foreach (['custom_orders.view', 'custom_orders.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->givePermissionTo(['custom_orders.view', 'custom_orders.manage']);

        return $user;
    }

    public function test_custom_orders_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('custom-orders.index'))
            ->assertForbidden();
    }

    public function test_custom_order_stores_the_signed_in_users_company_and_its_line(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->manager($company->id);
        $client = Client::factory()->create();
        $factory = Factory::factory()->create();

        $this->actingAs($user)
            ->post(route('custom-orders.store'), [
                'company_id' => $otherCompany->id,
                'client_id' => $client->id,
                'delivery_method' => 'we_ship',
                'status' => 'open',
                'notes' => 'Sevk adresi sonra netleşecek',
                'ordered_at' => '2026-06-23T16:38',
                'items' => [[
                    'product_name' => '50x100 kutu',
                    'length' => '6 metre',
                    'factory_id' => $factory->id,
                    'quantity' => 12.5,
                    'unit_price' => 185,
                    'due_on' => '2026-07-01',
                ]],
            ])
            ->assertRedirect();

        $order = CustomOrder::query()->where('client_id', $client->id)->first();

        $this->assertNotNull($order);
        $this->assertSame($company->id, $order->company_id);
        $this->assertSame($user->id, $order->user_id);
        $this->assertTrue($order->items()->where('product_name', '50x100 kutu')->where('factory_id', $factory->id)->exists());
    }

    public function test_open_custom_order_can_be_closed(): void
    {
        $user = $this->manager();
        $order = CustomOrder::factory()->create([
            'status' => CustomOrderStatus::Open,
        ]);

        $this->actingAs($user)
            ->post(route('custom-orders.close', $order))
            ->assertRedirect();

        $this->assertSame(CustomOrderStatus::Closed, $order->fresh()->status);
    }

    public function test_store_requires_a_line_item(): void
    {
        $user = $this->manager();
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->from(route('custom-orders.create'))
            ->post(route('custom-orders.store'), [
                'client_id' => $client->id,
                'delivery_method' => 'we_ship',
                'status' => 'open',
                'ordered_at' => '2026-06-23T16:38',
                'items' => [],
            ])
            ->assertRedirect(route('custom-orders.create'))
            ->assertSessionHasErrors(['items' => 'En az bir kalem ekleyin.']);
    }
}
