<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\Category;
use App\Models\CategoryColumnDefinition;
use App\Models\Client;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\CategoryColumnDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(CategoryColumnDefinitionSeeder::class);
    }

    public function test_the_product_shipment_form_stores_type_price_vehicle_and_note(): void
    {
        Permission::findOrCreate('shipments.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('shipments.manage');
        $product = $this->shippableProduct();
        $client = Client::factory()->create(['name' => 'Deneme Firma']);
        $vehicle = Vehicle::factory()->create([
            'name' => 'Kamyonet',
            'license_plate' => '34 AB 123',
            'driver_name' => 'Sürücü',
        ]);

        $this->actingAs($user)
            ->post(route('products.ship', $product), [
                'client_name' => 'Deneme Firma',
                'quantity_piece' => 4,
                'ship_type' => ShipmentType::OwnDelivery->value,
                'unit_price' => 15.5,
                'vehicle_id' => $vehicle->id,
                'notes' => 'Kapıdan teslim',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $shipment = Shipment::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($shipment);
        $this->assertSame(ShipmentStatus::Scheduled, $shipment->status);
        $this->assertSame(ShipmentType::OwnDelivery, $shipment->ship_type);
        $this->assertSame('34 AB 123', $shipment->vehicle_plate);
        $this->assertSame('Sürücü', $shipment->driver_name);
        $this->assertSame('Kapıdan teslim', $shipment->notes);

        $item = ShipmentItem::query()->where('shipment_id', $shipment->id)->first();
        $this->assertNotNull($item);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(4, $item->quantity_piece);
        $this->assertSame(0, $item->quantity_pallet);
        $this->assertSame('15.50', $item->unit_price);
    }

    public function test_a_second_product_joins_the_open_shipment_for_that_client(): void
    {
        Permission::findOrCreate('shipments.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('shipments.manage');
        $first = $this->shippableProduct('İlk profil');
        $second = $this->shippableProduct('İkinci profil');
        $client = Client::factory()->create(['name' => 'Deneme Firma']);

        $payload = [
            'client_name' => 'Deneme Firma',
            'quantity_piece' => 2,
            'ship_type' => ShipmentType::Cargo->value,
            'unit_price' => 10,
            'notes' => 'İlk not',
        ];

        $this->actingAs($user)
            ->post(route('products.ship', $first), $payload)
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('products.ship', $second), [
                ...$payload,
                'quantity_piece' => 7,
                'unit_price' => 20,
                'notes' => 'Bu not yazılmamalı',
            ])
            ->assertRedirect();

        $this->assertSame(1, Shipment::query()->where('client_id', $client->id)->count());
        $shipment = Shipment::query()->where('client_id', $client->id)->first();
        $this->assertSame('İlk not', $shipment->notes);
        $this->assertSame(2, $shipment->items()->count());
        $added = $shipment->items()->where('product_id', $second->id)->first();
        $this->assertSame(7, $added->quantity_piece);
    }

    public function test_the_shipment_form_requires_a_ship_type_and_a_known_client(): void
    {
        Permission::findOrCreate('shipments.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('shipments.manage');
        $product = $this->shippableProduct();

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.ship', $product), [
                'client_name' => 'Deneme Firma',
                'quantity_piece' => 1,
                'ship_type' => '',
                'unit_price' => 5,
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasErrors('ship_type');

        $this->actingAs($user)
            ->from(route('products.index'))
            ->post(route('products.ship', $product), [
                'client_name' => 'Olmayan Firma',
                'quantity_piece' => 1,
                'ship_type' => ShipmentType::Cargo->value,
                'unit_price' => 5,
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasErrors('client_name');

        $this->assertSame(0, Shipment::query()->count());
    }

    private function shippableProduct(string $name = 'Sevk profil'): Product
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $column = CategoryColumnDefinition::query()->where('name', 'shipment_button')->firstOrFail();
        $parent->columnDefinitions()->sync([$column->id]);

        return Product::factory()->create([
            'category_id' => $child->id,
            'name' => $name,
        ]);
    }
}
