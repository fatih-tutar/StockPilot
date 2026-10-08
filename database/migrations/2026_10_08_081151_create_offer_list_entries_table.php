<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('contact_name')->nullable();
            $table->text('product_quantity')->nullable();
            $table->string('price')->nullable();
            $table->string('factory_name')->nullable();
            $table->string('factory_price')->nullable();
            $table->foreignId('offered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_list_entries');
    }
};
