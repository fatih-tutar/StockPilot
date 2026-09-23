<?php

namespace Database\Seeders;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuoteSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@stockpilot.test')->first();
        $client = Client::query()->where('name', 'Nova Build Ltd')->first();
        $products = Product::query()->where('is_active', true)->orderBy('id')->take(2)->get();

        if (! $admin || ! $client || $products->isEmpty()) {
            return;
        }

        $quote = Quote::query()->updateOrCreate(
            ['number' => 'Q-'.now()->year.'-0001'],
            [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'status' => QuoteStatus::Draft,
                'quote_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'currency' => 'TRY',
                'tax_rate' => 20,
                'notes' => 'Demo toptan teklif',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ],
        );

        $quote->items()->delete();

        foreach ($products as $index => $product) {
            $quantityPiece = ($index + 1) * 10;
            $unitPrice = 100 + ($index * 25);

            $quote->items()->create([
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity_piece' => $quantityPiece,
                'quantity_pallet' => $index + 1,
                'unit_price' => $unitPrice,
                'line_total' => QuoteItem::calculateLineTotal($quantityPiece, $unitPrice),
                'sort_order' => $index,
            ]);
        }

        $quote->recalculateTotals();

        $harbor = Client::query()->where('name', 'Harbor Fabrication')->first();
        if ($harbor && $products->isNotEmpty()) {
            $sent = Quote::query()->updateOrCreate(
                ['number' => 'Q-'.now()->year.'-0002'],
                [
                    'client_id' => $harbor->id,
                    'user_id' => $admin->id,
                    'status' => QuoteStatus::Sent,
                    'quote_date' => now()->subDays(3)->toDateString(),
                    'valid_until' => now()->addDays(10)->toDateString(),
                    'currency' => 'TRY',
                    'tax_rate' => 20,
                    'notes' => null,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                ],
            );

            $sent->items()->delete();
            $product = $products->first();
            $sent->items()->create([
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity_piece' => 25,
                'quantity_pallet' => 2,
                'unit_price' => 150,
                'line_total' => QuoteItem::calculateLineTotal(25, 150),
                'sort_order' => 0,
            ]);
            $sent->recalculateTotals();
        }
    }
}
