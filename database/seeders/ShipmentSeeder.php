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
            [
                'client_id' => $client->id,
                'notes' => 'Demo sevkiyat',
            ],
            [
                'quote_id' => $quote?->id,
                'user_id' => $admin->id,
                'status' => ShipmentStatus::Received,
                'ship_date' => now()->addDay()->toDateString(),
                'delivery_date' => now()->addDays(2)->toDateString(),
                'shipping_address' => $client->address,
            ],
        );

        $shipment->items()->delete();

        foreach ($products as $index => $product) {
            $shipment->items()->create([
                'product_id' => $product->id,
                'quantity_piece' => ($index + 1) * 5,
                'quantity_pallet' => $index + 1,
                'sort_order' => $index,
            ]);
        }

        $harbor = Client::query()->where('name', 'Harbor Fabrication')->first();
        if ($harbor && $products->isNotEmpty()) {
            $preparing = Shipment::query()->updateOrCreate(
                [
                    'client_id' => $harbor->id,
                    'notes' => 'Demo hazırlanan sipariş',
                ],
                [
                    'quote_id' => null,
                    'user_id' => $admin->id,
                    'prepared_by' => $admin->id,
                    'status' => ShipmentStatus::Preparing,
                    'ship_date' => now()->subDay()->toDateString(),
                    'delivery_date' => now()->addDay()->toDateString(),
                    'shipping_address' => $harbor->address,
                ],
            );

            $preparing->items()->delete();
            $product = $products->first();
            $preparing->items()->create([
                'product_id' => $product->id,
                'quantity_piece' => 20,
                'quantity_pallet' => 2,
                'sort_order' => 0,
            ]);
        }
    }
}
