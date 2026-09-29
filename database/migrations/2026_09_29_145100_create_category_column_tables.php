<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_column_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('group');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_column_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'category_column_definition_id'], 'category_columns_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_columns');
        Schema::dropIfExists('category_column_definitions');
    }
};
