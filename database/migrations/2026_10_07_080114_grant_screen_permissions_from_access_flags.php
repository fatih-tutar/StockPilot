<?php

use App\Support\AccessRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AccessRoles::grantStoredFlags();
    }

    public function down(): void
    {
        //
    }
};
