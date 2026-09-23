<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed baseline roles and demo users for StockPilot.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.manage',
            'stock.view',
            'stock.manage',
            'quotes.manage',
            'shipments.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $admin = Role::findOrCreate('admin');
        $staff = Role::findOrCreate('staff');

        $admin->syncPermissions($permissions);
        $staff->syncPermissions([
            'stock.view',
            'quotes.manage',
            'shipments.manage',
        ]);

        $adminUser = User::query()->updateOrCreate(
            ['email' => 'admin@stockpilot.test'],
            [
                'name' => 'StockPilot Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
        $adminUser->syncRoles(['admin']);

        $staffUser = User::query()->updateOrCreate(
            ['email' => 'staff@stockpilot.test'],
            [
                'name' => 'StockPilot Staff',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
        $staffUser->syncRoles(['staff']);
    }
}
