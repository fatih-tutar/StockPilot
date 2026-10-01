<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('factory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->string('shelf', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('unit_weight_kg', 10, 3)->nullable();
            $table->string('length_measure')->nullable();
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('pack_quantity')->nullable();
            $table->unsignedInteger('default_order_quantity')->default(0);
            $table->unsignedInteger('quantity_piece')->default(0);
            $table->unsignedInteger('quantity_pallet')->default(0);
            $table->unsignedInteger('warehouse_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->unsignedInteger('warehouse_low_stock_threshold')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'name']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
