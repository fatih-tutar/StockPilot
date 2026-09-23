<?php

namespace Database\Seeders;

use App\Enums\ShipmentStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@stockpilot.test')->first();
        $client = Client::query()->where('name', 'Nova Build Ltd')->first();
        $quote = Quote::query()->where('number', 'Q-'.now()->year.'-0001')->first();
        $products = Product::query()->where('is_active', true)->orderBy('id')->take(2)->get();

        if (! $admin || ! $client || $products->isEmpty()) {
            return;
        }

        $shipment = Shipment::query()->updateOrCreate(
            ['number' => 'S-'.now()->year.'-0001'],
            [
                'client_id' => $client->id,
                'quote_id' => $quote?->id,
                'user_id' => $admin->id,
                'status' => ShipmentStatus::Scheduled,
                'ship_date' => now()->addDay()->toDateString(),
                'delivery_date' => now()->addDays(2)->toDateString(),
                'vehicle_plate' => '34 SP 01',
                'driver_name' => 'Ali Yılmaz',
                'shipping_address' => $client->address,
                'notes' => 'Demo sevkiyat',
            ],
        );

        $shipment->items()->delete();

        foreach ($products as $index => $product) {
            $shipment->items()->create([
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity_piece' => ($index + 1) * 5,
                'quantity_pallet' => $index + 1,
                'sort_order' => $index,
            ]);
        }

        $harbor = Client::query()->where('name', 'Harbor Fabrication')->first();
        if ($harbor && $products->isNotEmpty()) {
            $inTransit = Shipment::query()->updateOrCreate(
                ['number' => 'S-'.now()->year.'-0002'],
                [
                    'client_id' => $harbor->id,
                    'quote_id' => null,
                    'user_id' => $admin->id,
                    'status' => ShipmentStatus::InTransit,
                    'ship_date' => now()->subDay()->toDateString(),
                    'delivery_date' => now()->addDay()->toDateString(),
                    'vehicle_plate' => '35 SP 02',
                    'driver_name' => 'Mehmet Demir',
                    'shipping_address' => $harbor->address,
                    'notes' => null,
                ],
            );

            $inTransit->items()->delete();
            $product = $products->first();
            $inTransit->items()->create([
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity_piece' => 20,
                'quantity_pallet' => 2,
                'sort_order' => 0,
            ]);
        }
    }
}
