<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->date('recorded_on');
            $table->decimal('store_incoming', 12, 3)->default(0);
            $table->decimal('store_outgoing', 12, 3)->default(0);
            $table->decimal('warehouse_incoming', 12, 3)->default(0);
            $table->decimal('warehouse_outgoing', 12, 3)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('recorded_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_flows');
    }
};
