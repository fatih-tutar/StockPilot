<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
        $product = Product::factory()->create(['name' => '20x20 beyaz']);
        $vehicle = Vehicle::factory()->create([
            'name' => 'Sevk Aracı',
            'license_plate' => '34 SP 10',
            'driver_name' => 'Test Şoför',
        ]);

        $response = $this->actingAs($user)
            ->from(route('shipments.index'))
            ->post(route('shipments.store'), [
                'client_id' => $client->id,
                'ship_type' => 'Tarafımızca sevk',
                'vehicle_id' => $vehicle->id,
                'notes' => 'Kapı önü',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity_piece' => 12,
                        'unit_price' => 25.5,
                    ],
                ],
            ]);

        $shipment = Shipment::query()->first();

        $this->assertNotNull($shipment);
        $response->assertRedirect(route('shipments.index'));
        $this->assertSame(ShipmentStatus::Scheduled, $shipment->status);
        $this->assertSame('Tarafımızca sevk', $shipment->ship_type->value);
        $this->assertSame($vehicle->id, $shipment->vehicle_id);
        $this->assertNull($shipment->vehicle_plate);
        $this->assertNull($shipment->driver_name);
        $this->assertSame('Kapı önü', $shipment->notes);
        $this->assertCount(1, $shipment->items);
        $this->assertSame(12, $shipment->items->first()->quantity_piece);
        $this->assertSame($product->id, $shipment->items->first()->product_id);
        $this->assertSame('25.50', $shipment->items->first()->unit_price);
        $response->assertSessionHas('success', 'Müşteri siparişi oluşturuldu.');
    }

    public function test_create_form_data_requires_manage_permission(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('shipments.view');
        $user->givePermissionTo('shipments.view');

        $this->actingAs($user)
            ->getJson(route('shipments.create-data'))
            ->assertForbidden();
    }

    public function test_create_form_data_lists_vehicles_and_ship_types(): void
    {
        $user = $this->userWithShipmentsPermission();
        Vehicle::factory()->create(['name' => 'Deneme Araç']);

        $this->actingAs($user)
            ->getJson(route('shipments.create-data'))
            ->assertOk()
            ->assertJsonPath('vehicles.0.name', 'Deneme Araç')
            ->assertJsonStructure(['vehicles', 'shipTypeOptions']);
    }

    public function test_shipment_editor_is_available_to_viewers(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('shipments.view');
        $user->givePermissionTo('shipments.view');
        $shipment = Shipment::factory()->create([
            'user_id' => $user->id,
            'number' => 'S-2026-0100',
        ]);

        $this->actingAs($user)
            ->getJson(route('shipments.editor', $shipment))
            ->assertOk()
            ->assertJsonPath('shipment.number', 'S-2026-0100')
            ->assertJsonStructure(['shipment', 'vehicles', 'shipTypeOptions'])
            ->assertJsonPath('shipment.client_name', $shipment->client->name);
    }

    public function test_shipment_editor_requires_view_permission(): void
    {
        $user = User::factory()->create();
        $shipment = Shipment::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson(route('shipments.editor', $shipment))
            ->assertForbidden();
    }

    public function test_legacy_import_keeps_shipment_lines_vehicle_and_archive_status(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $product = Product::factory()->create(['name' => 'Profil 40']);
        $vehicle = Vehicle::factory()->create([
            'license_plate' => '34 SP 10',
            'driver_name' => 'Deneme Şoför',
        ]);
        $directory = storage_path('framework/testing/shipments-import');

        File::ensureDirectoryExists($directory);
        File::put($directory.'/sevkiyat.csv', implode("\n", [
            'id,urunler,firma_id,adetler,kilolar,fiyatlar,olusturan,hazirlayan,faturaci,sevk_tipi,arac_id,aciklama,manuel,durum,nakliye_durumu,silik,saniye,sirket_id',
            "30,\"{$product->id},88\",{$client->id},\"6,2\",25,10-12,{$user->id},0,0,2,{$vehicle->id},Kapı önü,1,0,0,0,1609459200,{$company->id}",
            "31,{$product->id},{$client->id},1,0,9,{$user->id},0,0,0,0,,0,3,0,1,1609459300,{$company->id}",
            "32,{$product->id},999999,1,0,9,{$user->id},0,0,0,0,,0,0,0,0,1609459400,{$company->id}",
            "33,{$product->id},{$client->id},4,0,15,999999,0,0,4,0,,0,3,0,0,1609545600,{$company->id}",
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'shipments',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'shipments',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $open = Shipment::query()->with('items')->find(30);
        $this->assertNotNull($open);
        $this->assertSame('SV-30', $open->number);
        $this->assertSame($client->id, $open->client_id);
        $this->assertSame($user->id, $open->user_id);
        $this->assertSame($company->id, $open->company_id);
        $this->assertSame(ShipmentStatus::Scheduled, $open->status);
        $this->assertSame($vehicle->id, $open->vehicle_id);
        $this->assertSame('Tarafımızca sevk', $open->ship_type?->value);
        $this->assertSame('2021-01-01', $open->ship_date->toDateString());
        $this->assertStringContainsString('Kapı önü', (string) $open->notes);
        $this->assertStringContainsString('Manuel kayıt', (string) $open->notes);
        $this->assertCount(2, $open->items);
        $this->assertSame($product->id, $open->items[0]->product_id);
        $this->assertSame(6, $open->items[0]->quantity_piece);
        $this->assertSame('10.00', $open->items[0]->unit_price);
        $this->assertNull($open->items[1]->product_id);
        $this->assertSame(2, $open->items[1]->quantity_piece);
        $this->assertSame('12.00', $open->items[1]->unit_price);

        $removed = Shipment::withTrashed()->find(31);
        $this->assertNotNull($removed);
        $this->assertSame(ShipmentStatus::Cancelled, $removed->status);
        $this->assertSoftDeleted($removed);
        $this->assertNull(Shipment::withTrashed()->find(32));

        $archived = Shipment::query()->find(33);
        $this->assertNotNull($archived);
        $this->assertSame(ShipmentStatus::Delivered, $archived->status);
        $this->assertSame($user->id, $archived->user_id);
        $this->assertSame('Kargo Teslim', $archived->ship_type?->value);
        $this->assertSame(3, Shipment::withTrashed()->count());
    }
}
