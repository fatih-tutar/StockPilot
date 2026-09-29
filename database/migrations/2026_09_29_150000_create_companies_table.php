<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('letterhead')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('price_list_visible')->default(true);
            $table->decimal('usd_rate', 12, 4)->nullable();
            $table->decimal('lme_rate', 12, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
