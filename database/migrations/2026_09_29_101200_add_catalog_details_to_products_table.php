<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('warehouse_quantity')->default(0)->after('quantity_pallet');
            $table->unsignedInteger('warehouse_low_stock_threshold')->nullable()->after('low_stock_threshold');
            $table->string('shelf', 50)->nullable()->after('sku');
            $table->decimal('unit_weight_kg', 10, 3)->nullable()->after('description');
            $table->string('length_measure')->nullable()->after('unit_weight_kg');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('length_measure');
            $table->decimal('sale_price', 12, 2)->default(0)->after('purchase_price');
            $table->unsignedBigInteger('factory_id')->nullable()->after('sale_price');
            $table->string('customer_name')->nullable()->after('factory_id');
            $table->date('due_on')->nullable()->after('customer_name');
            $table->unsignedInteger('pack_quantity')->nullable()->after('due_on');
            $table->unsignedBigInteger('company_id')->nullable()->after('pack_quantity');
            $table->unsignedInteger('default_order_quantity')->default(0)->after('company_id');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn([
                'warehouse_quantity',
                'warehouse_low_stock_threshold',
                'shelf',
                'unit_weight_kg',
                'length_measure',
                'purchase_price',
                'sale_price',
                'factory_id',
                'customer_name',
                'due_on',
                'pack_quantity',
                'company_id',
                'default_order_quantity',
                'sort_order',
            ]);
        });
    }
};
