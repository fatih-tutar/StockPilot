<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_visit_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('customer_name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->date('visited_on')->nullable();
            $table->date('planned_on')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city', 'district']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_visits');
    }
};
