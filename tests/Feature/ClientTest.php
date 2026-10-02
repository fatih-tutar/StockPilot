<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CustomOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(): User
    {
        foreach (['clients.view', 'clients.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['clients.view', 'clients.manage']);

        return $user;
    }

    public function test_client_accepts_a_historical_email_that_is_not_a_valid_address(): void
    {
        $user = $this->manager();

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'name' => 'Alışık Metal',
                'email' => 'canan_ugurluuhotmail.com',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'name' => 'Alışık Metal',
            'email' => 'canan_ugurluuhotmail.com',
        ]);
    }

    public function test_client_edit_lists_that_clients_custom_orders(): void
    {
        $user = $this->manager();
        $client = Client::factory()->create();
        $order = CustomOrder::factory()->create([
            'client_id' => $client->id,
            'notes' => 'Artekten gelecek',
        ]);

        $this->actingAs($user)
            ->get(route('clients.edit', $client))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('client.custom_orders.0.id', $order->id)
                ->where('client.custom_orders.0.notes', 'Artekten gelecek'));
    }
}
