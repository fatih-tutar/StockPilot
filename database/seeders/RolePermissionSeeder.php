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
            'clients.view',
            'clients.manage',
            'factories.view',
            'factories.manage',
            'factory_orders.view',
            'factory_orders.manage',
            'molds.view',
            'molds.manage',
            'catalog.view',
            'catalog.manage',
            'leaves.view',
            'leaves.manage',
            'goods_flows.view',
            'goods_flows.manage',
            'vehicles.view',
            'vehicles.manage',
            'custom_orders.view',
            'custom_orders.manage',
            'visits.view',
            'visits.manage',
            'work_tasks.view',
            'work_tasks.manage',
            'organizations.view',
            'organizations.manage',
            'quotes.view',
            'quotes.manage',
            'shipments.view',
            'shipments.manage',
            'count_reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin');
        $staff = Role::findOrCreate('staff');

        $admin->syncPermissions($permissions);
        $staff->syncPermissions([
            'stock.view',
            'stock.manage',
            'clients.view',
            'clients.manage',
            'factories.view',
            'factories.manage',
            'factory_orders.view',
            'factory_orders.manage',
            'molds.view',
            'molds.manage',
            'catalog.view',
            'catalog.manage',
            'leaves.view',
            'goods_flows.view',
            'goods_flows.manage',
            'vehicles.view',
            'vehicles.manage',
            'custom_orders.view',
            'custom_orders.manage',
            'visits.view',
            'visits.manage',
            'work_tasks.view',
            'work_tasks.manage',
            'organizations.view',
            'organizations.manage',
            'quotes.view',
            'quotes.manage',
            'shipments.view',
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
