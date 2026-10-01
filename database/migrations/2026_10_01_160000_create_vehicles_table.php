<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('license_plate')->nullable();
            $table->string('driver_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_delivery_vehicle')->default(false);
            $table->date('casco_expires_on')->nullable();
            $table->date('insurance_expires_on')->nullable();
            $table->date('inspection_due_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('license_plate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
