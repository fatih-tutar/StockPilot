<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('users')->whereNull('email')->count();
        if ($missing > 0) {
            throw new RuntimeException($missing.' kullanıcı kaydının e-posta adresi boş. Sütunu zorunlu yapmadan önce bu adresleri doldurun.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }
};
