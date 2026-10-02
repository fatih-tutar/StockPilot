<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('factory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('length')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->date('due_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('due_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_order_items');
    }
};
