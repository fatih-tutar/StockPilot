<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function userWithShipmentsPermission(): User
    {
        foreach (['shipments.view', 'shipments.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['shipments.view', 'shipments.manage']);

        return $user;
    }

    public function test_shipments_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('shipments.index'))
            ->assertForbidden();
    }

    public function test_shipments_index_is_displayed(): void
    {
        $user = $this->userWithShipmentsPermission();
        $client = Client::factory()->create();

        Shipment::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'number' => 'S-2026-0099',
        ]);

        $this->actingAs($user)
            ->get(route('shipments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Shipments/Index')
                ->has('shipments.data', 1)
                ->where('shipments.data.0.number', 'S-2026-0099'));
    }

    public function test_shipment_can_be_created_with_items(): void
    {
        $user = $this->userWithShipmentsPermission();
        $client = Client::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post(route('shipments.store'), [
            'client_id' => $client->id,
            'quote_id' => null,
            'status' => ShipmentStatus::Scheduled->value,
            'ship_date' => now()->toDateString(),
            'delivery_date' => now()->addDay()->toDateString(),
            'vehicle_plate' => '34 SP 10',
            'driver_name' => 'Test Şoför',
            'shipping_address' => 'Istanbul',
            'notes' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity_piece' => 12,
                    'quantity_pallet' => 1,
                ],
            ],
        ]);

        $shipment = Shipment::query()->first();

        $this->assertNotNull($shipment);
        $response->assertRedirect(route('shipments.edit', $shipment));
        $this->assertSame(ShipmentStatus::Scheduled, $shipment->status);
        $this->assertSame('34 SP 10', $shipment->vehicle_plate);
        $this->assertCount(1, $shipment->items);
        $this->assertSame(12, $shipment->items->first()->quantity_piece);
    }
}
