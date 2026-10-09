<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_quote_user_gets_matching_client_names(): void
    {
        Permission::findOrCreate('quotes.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('quotes.manage');
        $match = Client::factory()->create(['name' => 'Alkor Metal']);
        Client::factory()->create(['name' => 'Başka Firma']);
        $removed = Client::factory()->create(['name' => 'Alkor Eski']);
        $removed->delete();

        $this->actingAs($user)
            ->getJson(route('clients.search', ['term' => 'alk']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $match->id)
            ->assertJsonPath('0.name', 'Alkor Metal');

        $this->actingAs($user)
            ->getJson(route('clients.search', ['term' => '   ']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_client_search_returns_at_most_ten_names(): void
    {
        Permission::findOrCreate('quotes.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('quotes.manage');

        foreach (range(1, 11) as $number) {
            Client::factory()->create(['name' => sprintf('Arama %02d', $number)]);
        }

        $this->actingAs($user)
            ->getJson(route('clients.search', ['term' => 'Arama']))
            ->assertOk()
            ->assertJsonCount(10)
            ->assertJsonMissing(['name' => 'Arama 11']);
    }

    public function test_client_search_hides_another_companys_clients(): void
    {
        Permission::findOrCreate('quotes.manage');
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('quotes.manage');
        $own = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Ortak Metal',
        ]);
        Client::factory()->create([
            'company_id' => $other->id,
            'name' => 'Ortak Demir',
        ]);

        $this->actingAs($user)
            ->getJson(route('clients.search', ['term' => 'Ortak']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $own->id);
    }

    public function test_a_shipment_user_can_search_clients(): void
    {
        Permission::findOrCreate('shipments.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('shipments.manage');
        $match = Client::factory()->create(['name' => 'Sevk Metal']);

        $this->actingAs($user)
            ->getJson(route('clients.search', ['term' => 'Sevk']))
            ->assertOk()
            ->assertJsonPath('0.id', $match->id)
            ->assertJsonPath('0.name', 'Sevk Metal');
    }

    public function test_a_user_without_quote_or_client_access_cannot_search_clients(): void
    {
        $user = User::factory()->create();
        Client::factory()->create(['name' => 'Gizli Firma']);

        $this->actingAs($user)
            ->getJson(route('clients.search', ['term' => 'Gizli']))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_from_client_search(): void
    {
        $this->get(route('clients.search', ['term' => 'Al']))
            ->assertRedirect(route('login'));
    }
}
