<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\ShipmentStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWithOpsPermissions(): User
    {
        $permissions = [
            'stock.view',
            'clients.view',
            'quotes.view',
            'shipments.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_operational_summary(): void
    {
        $user = $this->userWithOpsPermissions();
        $client = Client::factory()->create(['is_active' => true]);

        Product::factory()->create([
            'name' => 'Low Stock Widget',
            'quantity_piece' => 2,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);

        Quote::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'status' => QuoteStatus::Draft,
            'number' => 'Q-2026-0100',
        ]);

        Shipment::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'status' => ShipmentStatus::Preparing,
        ]);

        Shipment::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'status' => ShipmentStatus::Received,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.low_stock_count', 1)
                ->where('stats.active_clients_count', 1)
                ->where('stats.open_quotes_count', 1)
                ->where('stats.active_shipments_count', 2)
                ->has('lowStockProducts', 1)
                ->has('openQuotes', 1)
                ->where('panelPageSize', 4)
                ->has('shipmentTabs', 3)
                ->where('shipmentTabs.0.status', 'received')
                ->where('shipmentTabs.1.status', 'preparing')
                ->where('shipmentTabs.2.status', 'invoiced')
                ->has('shipmentTabs.0.shipments', 1)
                ->has('shipmentTabs.1.shipments', 1)
                ->has('shipmentTabs.2.shipments', 0)
                ->where('shipmentTabs.0.shipments.0.client.name', $client->name)
                ->where('can.shipments_manage', false));
    }

    public function test_dashboard_loads_enough_shipments_for_show_more(): void
    {
        $user = $this->userWithOpsPermissions();
        $client = Client::factory()->create(['is_active' => true]);

        Shipment::factory()->count(6)->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'status' => ShipmentStatus::Received,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('shipmentTabs.0.shipments', 6)
                ->where('panelPageSize', 4));
    }
}
