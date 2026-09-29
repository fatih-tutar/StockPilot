<?php

namespace Database\Seeders;

use App\Enums\CategoryColumnGroup;
use App\Models\CategoryColumnDefinition;
use Illuminate\Database\Seeder;

class CategoryColumnDefinitionSeeder extends Seeder
{
    /**
     * Seed the column catalog. Category assignments stay out of the repo.
     */
    public function run(): void
    {
        $columns = [
            ['name' => 'product_code', 'label' => 'Ürün Kodu', 'group' => CategoryColumnGroup::List, 'sort_order' => 1],
            ['name' => 'quantity', 'label' => 'Adet', 'group' => CategoryColumnGroup::List, 'sort_order' => 2],
            ['name' => 'pallet', 'label' => 'Palet', 'group' => CategoryColumnGroup::List, 'sort_order' => 3],
            ['name' => 'warehouse_quantity', 'label' => 'Depo Adet', 'group' => CategoryColumnGroup::List, 'sort_order' => 4],
            ['name' => 'shelf', 'label' => 'Raf', 'group' => CategoryColumnGroup::List, 'sort_order' => 5],
            ['name' => 'unit_weight', 'label' => 'Birim Kg', 'group' => CategoryColumnGroup::List, 'sort_order' => 6],
            ['name' => 'total', 'label' => 'Toplam', 'group' => CategoryColumnGroup::List, 'sort_order' => 7],
            ['name' => 'purchase_price', 'label' => 'Alış', 'group' => CategoryColumnGroup::List, 'sort_order' => 8],
            ['name' => 'sales_price', 'label' => 'Satış', 'group' => CategoryColumnGroup::List, 'sort_order' => 9],
            ['name' => 'factory', 'label' => 'Fabrika', 'group' => CategoryColumnGroup::List, 'sort_order' => 10],
            ['name' => 'order_quantity', 'label' => 'Sipariş Adedi', 'group' => CategoryColumnGroup::Form, 'sort_order' => 11],
            ['name' => 'warning_count', 'label' => 'Uyarı Adedi', 'group' => CategoryColumnGroup::Form, 'sort_order' => 12],
            ['name' => 'warehouse_warning_count', 'label' => 'Depo Uyarı Adedi', 'group' => CategoryColumnGroup::Form, 'sort_order' => 13],
            ['name' => 'order_weight', 'label' => 'Sipariş Kilo', 'group' => CategoryColumnGroup::Form, 'sort_order' => 14],
            ['name' => 'size_measure', 'label' => 'Boy Ölçüsü', 'group' => CategoryColumnGroup::Form, 'sort_order' => 15],
            ['name' => 'customer_name', 'label' => 'Müşteri İsmi', 'group' => CategoryColumnGroup::Form, 'sort_order' => 16],
            ['name' => 'due_date', 'label' => 'Termin', 'group' => CategoryColumnGroup::Form, 'sort_order' => 17],
            ['name' => 'manual_sales', 'label' => 'Manuel Satış', 'group' => CategoryColumnGroup::Form, 'sort_order' => 18],
            ['name' => 'offer_button', 'label' => 'Teklif Butonu', 'group' => CategoryColumnGroup::Action, 'sort_order' => 19],
            ['name' => 'order_button', 'label' => 'Sipariş Butonu', 'group' => CategoryColumnGroup::Action, 'sort_order' => 20],
            ['name' => 'shipment_button', 'label' => 'Sevkiyat Butonu', 'group' => CategoryColumnGroup::Action, 'sort_order' => 21],
            ['name' => 'edit_button', 'label' => 'Düzenle Butonu', 'group' => CategoryColumnGroup::Action, 'sort_order' => 22],
        ];

        foreach ($columns as $column) {
            CategoryColumnDefinition::query()->updateOrCreate(
                ['name' => $column['name']],
                $column,
            );
        }
    }
}
