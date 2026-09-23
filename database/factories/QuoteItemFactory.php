<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    protected $model = QuoteItem::class;

    public function definition(): array
    {
        $quantityPiece = fake()->numberBetween(1, 50);
        $unitPrice = fake()->randomFloat(2, 10, 500);

        return [
            'quote_id' => Quote::factory(),
            'product_id' => Product::factory(),
            'description' => fake()->words(3, true),
            'quantity_piece' => $quantityPiece,
            'quantity_pallet' => fake()->numberBetween(0, 5),
            'unit_price' => $unitPrice,
            'line_total' => QuoteItem::calculateLineTotal($quantityPiece, $unitPrice),
            'sort_order' => 0,
        ];
    }
}
