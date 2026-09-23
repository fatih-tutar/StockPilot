<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = Category::query()->updateOrCreate(
            ['name' => 'Extrusion Profiles'],
            ['description' => 'Standard aluminum profiles', 'sort_order' => 1],
        );

        $sheets = Category::query()->updateOrCreate(
            ['name' => 'Sheets & Panels'],
            ['description' => 'Flat stock', 'sort_order' => 2],
        );

        $accessories = Category::query()->updateOrCreate(
            ['name' => 'Accessories'],
            ['description' => 'Hardware and fittings', 'sort_order' => 3],
        );

        Product::query()->updateOrCreate(
            ['sku' => 'PROF-40x40'],
            [
                'category_id' => $profiles->id,
                'name' => 'Profile 40x40',
                'description' => 'Demo extrusion profile',
                'quantity_piece' => 120,
                'quantity_pallet' => 8,
                'low_stock_threshold' => 20,
                'is_active' => true,
            ],
        );

        Product::query()->updateOrCreate(
            ['sku' => 'SHEET-2MM'],
            [
                'category_id' => $sheets->id,
                'name' => 'Sheet 2mm',
                'description' => 'Demo sheet stock',
                'quantity_piece' => 45,
                'quantity_pallet' => 3,
                'low_stock_threshold' => 15,
                'is_active' => true,
            ],
        );

        Product::query()->updateOrCreate(
            ['sku' => 'ACC-CLIP'],
            [
                'category_id' => $accessories->id,
                'name' => 'Mounting Clip',
                'description' => 'Demo accessory',
                'quantity_piece' => 8,
                'quantity_pallet' => 0,
                'low_stock_threshold' => 25,
                'is_active' => true,
            ],
        );
    }
}
