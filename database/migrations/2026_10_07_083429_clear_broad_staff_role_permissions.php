<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['staff', 'supervisor'] as $name) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();

            if ($role === null) {
                continue;
            }

            $role->syncPermissions([]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
