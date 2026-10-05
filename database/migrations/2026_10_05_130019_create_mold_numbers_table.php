<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mold_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('factory_id')->constrained()->restrictOnDelete();
            $table->string('number', 32)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'factory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mold_numbers');
    }
};
