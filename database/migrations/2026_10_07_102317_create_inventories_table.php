<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32)->nullable();
            $table->string('dimension_1', 32)->nullable();
            $table->string('dimension_2', 32)->nullable();
            $table->string('dimension_3', 32)->nullable();
            $table->string('density', 32)->nullable();
            $table->string('factory_name', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
