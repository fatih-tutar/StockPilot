<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable()->after('name');
            $table->decimal('profit_margin', 5, 2)->nullable()->after('description');
            $table->unsignedBigInteger('company_id')->nullable()->after('profit_margin');

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->dropColumn(['image', 'profit_margin', 'company_id']);
        });
    }
};
