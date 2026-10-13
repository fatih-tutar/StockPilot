<?php

use App\Models\CategoryColumnDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CategoryColumnDefinition::query()
            ->where('name', 'order_button')
            ->update(['label' => 'Fabrika siparişi butonu']);

        CategoryColumnDefinition::query()
            ->where('name', 'shipment_button')
            ->update(['label' => 'Müşteri siparişi butonu']);
    }

    public function down(): void
    {
        CategoryColumnDefinition::query()
            ->where('name', 'order_button')
            ->update(['label' => 'Sipariş Butonu']);

        CategoryColumnDefinition::query()
            ->where('name', 'shipment_button')
            ->update(['label' => 'Sevkiyat Butonu']);
    }
};
