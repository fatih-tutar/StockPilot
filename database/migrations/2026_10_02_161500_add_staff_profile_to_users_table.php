<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 50)->nullable();
            $table->string('phone_2', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('title')->nullable();
            $table->date('hired_on')->nullable();
            $table->string('access_level')->nullable();
            $table->json('access_flags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone',
                'phone_2',
                'address',
                'title',
                'hired_on',
                'access_level',
                'access_flags',
                'is_active',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
