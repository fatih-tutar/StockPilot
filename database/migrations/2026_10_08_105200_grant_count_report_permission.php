<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('count_reports.view');
        Role::findOrCreate('admin')->givePermissionTo($permission);

        User::query()->orderBy('id')->each(function (User $user) use ($permission): void {
            $flags = $user->access_flags ?? [];

            if (! filter_var($flags['count_report'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            $user->givePermissionTo($permission);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'count_reports.view')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
